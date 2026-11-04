<?php

namespace App\Services\Watch;

use App\Models\AnalysisRun;
use App\Models\PageView;
use App\Models\SiteWatch;
use App\Models\WatchReport;
use Illuminate\Support\Str;

/**
 * The Watch report: what a fresh scan of a watched website says compared with the scan before it. Everything here is
 * computed from the two scans' own results (their Website DNA and diagnosis) plus, when the site also has a published
 * REDO copy, REDO's own visit counts — nothing is guessed, and visits to the person's original site are never claimed
 * (REDO can't see them).
 */
class WatchReportBuilder
{
    /** @param AnalysisRun|null $previous the scan to compare with (null = this is the baseline) */
    public function build(SiteWatch $watch, AnalysisRun $run, ?AnalysisRun $previous): WatchReport
    {
        $now = $this->facts($run);
        $before = $previous ? $this->facts($previous) : null;

        $issues = $this->issueDelta($now['issues'], $before['issues'] ?? null);
        $changes = $before ? $this->changes($now, $before) : [];
        $visits = $this->visits($watch);

        $trend = ! $before ? 'baseline' : match (true) {
            $issues['high_new'] > 0 || count($issues['new']) > count($issues['resolved']) => 'attention',
            count($issues['resolved']) > count($issues['new']) => 'improved',
            default => 'same',
        };
        $summary = [
            'baseline' => ! $before,
            'previous_run' => $previous?->public_id,
            'business' => $now['name'],
            'changes' => $changes,
            'issues' => $issues,
            'issue_count' => count($now['issues']),
            'visits' => $visits,
            'actions' => $this->actions($now['issues'], $issues),
            'pages' => ['now' => $now['pages'], 'before' => $before['pages'] ?? null],
        ];

        return WatchReport::create([
            'site_watch_id' => $watch->id, 'analysis_run_id' => $run->id, 'status' => 'ok', 'trend' => $trend,
            'headline' => $this->headline($trend, $issues, $changes, count($now['issues'])), 'summary_json' => $summary, 'scanned_at' => now(),
        ]);
    }

    public function failed(SiteWatch $watch, ?AnalysisRun $run, string $reason): WatchReport
    {
        return WatchReport::create(['site_watch_id' => $watch->id, 'analysis_run_id' => $run?->id, 'status' => 'failed', 'headline' => 'The rescan couldn’t finish', 'summary_json' => ['error' => $reason], 'scanned_at' => now()]);
    }

    // ---------------------------------------------------------------- facts

    /** @return array{name: string, vp: string, ctas: array<int,string>, offerings: array<int,string>, trust: array<int,string>, pages: int, issues: array<int, array<string,mixed>>} */
    private function facts(AnalysisRun $run): array
    {
        $dna = $run->currentWebsiteDna()?->dna_json ?? [];
        $norm = fn ($l) => trim(preg_replace('/\s+/', ' ', (string) $l));

        return [
            'name' => (string) ($dna['business_name'] ?? ''),
            'vp' => $norm($dna['value_proposition']['text'] ?? ''),
            'ctas' => collect($dna['cta_inventory'] ?? [])->pluck('label')->map($norm)->filter()->unique()->values()->all(),
            'offerings' => collect($dna['offerings'] ?? [])->pluck('name')->map($norm)->filter()->unique()->values()->all(),
            'trust' => collect($dna['trust_signals'] ?? [])->pluck('description')->map($norm)->filter()->unique()->values()->all(),
            'pages' => (int) ($run->siteSnapshot?->pages()->count() ?? 0),
            'issues' => collect($run->currentDiagnosisReport()?->issues_json['issues'] ?? [])->map(fn ($i) => [
                'title' => $norm($i['title'] ?? ''), 'severity' => $i['severity'] ?? 'medium', 'category' => $i['category'] ?? '', 'solution' => Str::limit($norm($i['recommended_solution'] ?? ''), 220),
            ])->filter(fn ($i) => $i['title'] !== '')->values()->all(),
        ];
    }

    /** @return array<string, array{added: array<int,string>, removed: array<int,string>}|array<string, mixed>> */
    private function changes(array $now, array $before): array
    {
        $diff = fn (string $k) => ['added' => array_values(array_slice(array_diff($now[$k], $before[$k]), 0, 6)), 'removed' => array_values(array_slice(array_diff($before[$k], $now[$k]), 0, 6))];
        $out = [];
        foreach (['ctas' => 'Calls to action', 'offerings' => 'Products & services', 'trust' => 'Trust signals'] as $k => $label) {
            $d = $diff($k);
            ($d['added'] || $d['removed']) && $out[$k] = $d + ['label' => $label];
        }
        if ($now['vp'] !== '' && $before['vp'] !== '' && $this->similar($now['vp'], $before['vp']) < 0.6) {
            $out['value_proposition'] = ['label' => 'Main message', 'old' => Str::limit($before['vp'], 200), 'new' => Str::limit($now['vp'], 200)];
        }
        if ($before['pages'] && $now['pages'] !== $before['pages']) {
            $out['pages'] = ['label' => 'Pages found', 'old' => $before['pages'], 'new' => $now['pages']];
        }

        return $out;
    }

    /** @return array{new: array, resolved: array, still_open: int, high_new: int} */
    private function issueDelta(array $now, ?array $before): array
    {
        if ($before === null) {
            return ['new' => [], 'resolved' => [], 'still_open' => count($now), 'high_new' => 0];
        }
        $match = fn (array $a, array $list) => collect($list)->contains(fn ($b) => $this->similar($a['title'], $b['title']) >= 0.5 || ($a['category'] !== '' && $a['category'] === $b['category'] && $this->similar($a['title'], $b['title']) >= 0.35));
        $new = array_values(array_filter($now, fn ($i) => ! $match($i, $before)));
        $resolved = array_values(array_filter($before, fn ($i) => ! $match($i, $now)));

        return [
            'new' => array_map(fn ($i) => ['title' => $i['title'], 'severity' => $i['severity'], 'solution' => $i['solution']], array_slice($new, 0, 5)),
            'resolved' => array_map(fn ($i) => ['title' => $i['title'], 'severity' => $i['severity']], array_slice($resolved, 0, 5)),
            'still_open' => count($now) - count($new), 'high_new' => count(array_filter($new, fn ($i) => $i['severity'] === 'high')),
        ];
    }

    /** What to do next: new problems first (worst first), then the biggest ones still open. */
    private function actions(array $issues, array $delta): array
    {
        $rank = ['high' => 0, 'medium' => 1, 'low' => 2];
        $newTitles = array_column($delta['new'], 'title');
        $list = collect($issues)->sortBy(fn ($i) => [in_array($i['title'], $newTitles, true) ? 0 : 1, $rank[$i['severity']] ?? 3])->take(3);

        return $list->map(fn ($i) => ['title' => $i['title'], 'severity' => $i['severity'], 'solution' => $i['solution'], 'is_new' => in_array($i['title'], $newTitles, true)])->values()->all();
    }

    /** Visits to the person's published REDO copy of this site (REDO's own page-view counts), this period vs the one before. */
    private function visits(SiteWatch $watch): array
    {
        $run = AnalysisRun::where('user_id', $watch->user_id)->where('normalized_url', $watch->normalized_url)->whereNotNull('published_slug')->where('is_watch_scan', false)->latest('id')->first();
        if (! $run) {
            return ['published' => false];
        }
        $days = max(7, (int) $watch->frequency_days);
        $count = fn ($from, $to) => PageView::where('path', 'like', 'sites/'.$run->published_slug.'%')->whereBetween('created_at', [$from, $to]);
        $thisP = $count(now()->subDays($days), now());
        $prevP = $count(now()->subDays($days * 2), now()->subDays($days));

        return ['published' => true, 'slug' => $run->published_slug, 'days' => $days, 'views' => (clone $thisP)->count(), 'visitors' => (clone $thisP)->distinct('visitor_hash')->count('visitor_hash'), 'previous_views' => $prevP->count()];
    }

    private function headline(string $trend, array $issues, array $changes, int $total): string
    {
        if ($trend === 'baseline') {
            return "First scan done — {$total} thing".($total === 1 ? '' : 's').' to improve. Your next scan will show what changed.';
        }
        $bits = [];
        count($issues['new']) && $bits[] = count($issues['new']).' new '.(count($issues['new']) === 1 ? 'issue' : 'issues');
        count($issues['resolved']) && $bits[] = count($issues['resolved']).' fixed';
        $changes && $bits[] = count($changes).' '.(count($changes) === 1 ? 'change' : 'changes').' on the page';

        return $bits ? ucfirst(implode(' · ', $bits)) : 'No meaningful change since the last scan';
    }

    /** Word-overlap similarity 0..1 (good enough to tell "same finding, reworded" from "different finding"). */
    private function similar(string $a, string $b): float
    {
        $w = fn ($s) => array_values(array_unique(array_filter(preg_split('/\W+/u', mb_strtolower($s)), fn ($t) => mb_strlen($t) > 3)));
        $x = $w($a);
        $y = $w($b);
        if (! $x || ! $y) {
            return 0.0;
        }

        return count(array_intersect($x, $y)) / count(array_unique(array_merge($x, $y)));
    }
}
