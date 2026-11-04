<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\ApprovedDesign;
use App\Models\DesignSpec;
use App\Models\VisualQaReport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $analysisRuns = $user->analysisRuns()->where('is_watch_scan', false)->with(['siteSnapshot', 'websiteDnaVersions'])->latest()->limit(24)->get();
        $cards = $this->cards($analysisRuns);

        $siteWatches = $user->siteWatches()->with(['latestReport', 'reports' => fn ($q) => $q->limit(4)])->latest()->get();
        $plan = $user->currentPlan();

        $leads = $user->hasLeadGenAccess()
            ? $user->leads()->with('analysisRun')->latest()->limit(20)->get()
            : collect();

        return view('dashboard', [
            'analysisRuns' => $analysisRuns,
            'cards' => $cards,
            'siteWatches' => $siteWatches,
            'plan' => $plan,
            'leads' => $leads,
            'watching' => $siteWatches->where('is_enabled', true)->count(),
            'analysesUsed' => $user->analysesUsed(),
            'analysisLimit' => $plan?->analysis_limit,
            'user' => $user,
        ]);
    }

    /**
     * One card per run: the REDO (after) screenshot for the header, else the original site's, else nothing;
     * the site's own name and the address for the footer. Batched so 24 cards do not mean 100 queries.
     *
     * @return array<int, array{run: AnalysisRun, title: string, host: string, image: ?string, kind: string, href: string, status: string}>
     */
    private function cards($runs): array
    {
        $ids = $runs->pluck('id');
        $latestSpec = DesignSpec::whereIn('analysis_run_id', $ids)->orderByDesc('version')->get()->unique('analysis_run_id')->keyBy('analysis_run_id');
        $reports = VisualQaReport::whereIn('design_spec_id', $latestSpec->pluck('id'))->where('status', 'complete')->orderByDesc('id')->get()->unique('design_spec_id')->keyBy('design_spec_id');

        $approved = ApprovedDesign::whereIn('analysis_run_id', $ids)->orderByDesc('approved_at')->get()->unique('analysis_run_id')->keyBy('analysis_run_id');

        $out = [];
        foreach ($runs as $run) {
            $shots = $reports[$latestSpec[$run->id]->id ?? 0]->report_json['screenshot_urls'] ?? [];
            $after = $shots['desktop'] ?? ($shots ? $shots[array_key_first($shots)] : null);
            $before = $run->siteSnapshot?->realScreenshotUrl();
            $host = parse_url($run->normalized_url ?: $run->submitted_url, PHP_URL_HOST) ?: ($run->normalized_url ?: $run->submitted_url);
            $dna = $run->websiteDnaVersions->sortByDesc('id')->first()?->dna_json ?? [];
            $name = trim((string) ($dna['business_name'] ?? ''));

            $done = in_array($run->status, ['complete', 'partial'], true);
            $ap = $approved[$run->id] ?? null;
            $out[] = [
                // An approved design is a completed website; editing it again later is always possible and doesn't undo that.
                'completed' => $ap !== null,
                'unapproved_changes' => $ap !== null && isset($latestSpec[$run->id]) && $latestSpec[$run->id]->id !== $ap->design_spec_id,
                'editable' => isset($latestSpec[$run->id]), // older designs are upgraded to the editable layout engine the first time they are opened in the editor
                'pass' => app(\App\Services\Billing\ExportPassAllowance::class)->capFor(auth()->user(), $run),
                'run' => $run,
                'title' => $name !== '' ? Str::limit($name, 60) : Str::of($host)->replaceMatches('/^www\./', '')->toString(),
                'host' => Str::of($host)->replaceMatches('/^www\./', '')->toString(),
                'image' => $after ?: $before,
                'kind' => $after ? 'redo' : ($before ? 'original' : 'none'),
                'href' => ($done || $ap) && $latestSpec->has($run->id) ? route('analyses.reveal', $run->public_id) : route('analyses.show', $run->public_id),
                'status' => $run->status,
            ];
        }

        return $out;
    }
}
