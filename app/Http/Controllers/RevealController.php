<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\Plan;
use App\Services\Blueprint\BlueprintCatalog;
use App\Services\Catalogue\DesignCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The before/after "reveal": the original site next to the real, QA-captured screenshots of
 * the redesign, with an honest account of what changed (and what was left out and why).
 */
class RevealController extends Controller
{
    public function show(AnalysisRun $analysisRun, DesignCatalogue $catalogue, BlueprintCatalog $blueprints): View|RedirectResponse
    {
        $spec = $analysisRun->currentDesignSpec();
        if (! $spec) {
            return redirect()->route('analyses.show', $analysisRun->public_id);
        }

        $report = $spec->visualQaReports()->where('status', 'complete')->latest('id')->first();
        $pending = $spec->visualQaReports()->whereIn('status', ['pending', 'running'])->latest('id')->first();
        $after = $report?->report_json['screenshot_urls'] ?? [];

        $json = $spec->design_spec_json;
        $v15 = $json['v15'] ?? null;
        $evaluation = $spec->evaluationReport?->evaluation_json['items'] ?? [];
        $diagnosisIssues = collect($analysisRun->currentDiagnosisReport()?->issues_json['issues'] ?? [])->keyBy('issue_id');

        $findings = collect($report?->report_json['findings'] ?? []);
        $dna = $analysisRun->currentWebsiteDna()?->dna_json ?? [];
        $addressedIds = collect($evaluation)->where('status', 'addressed')->pluck('issue_id')->all();
        $issues = collect($analysisRun->currentDiagnosisReport()?->issues_json['issues'] ?? [])
            ->map(fn ($i) => ['title' => $i['title'] ?? '', 'severity' => $i['severity'] ?? 'low', 'category' => str_replace('_', ' ', $i['category'] ?? ''), 'evidence_ids' => $i['evidence_ids'] ?? [], 'addressed' => in_array($i['issue_id'] ?? null, $addressedIds, true)])
            ->sortBy(fn ($i) => ['high' => 0, 'medium' => 1][$i['severity']] ?? 2)->values()->all();

        $direction = $v15 ? $catalogue->direction($v15['design']['direction_id']) : null;
        $blueprint = $v15 ? $blueprints->find($v15['design']['blueprint_id']) : null;

        return view('analyses.reveal', [
            'run' => $analysisRun,
            'spec' => $spec,
            'before' => $analysisRun->siteSnapshot?->realScreenshotUrl(),
            'after' => $after,
            'report' => $report,
            'qaPending' => $pending,
            'v15' => $v15,
            'directionName' => $direction['name'] ?? null,
            'blueprintName' => $blueprint['name'] ?? null,
            'omitted' => $v15['content_mapping']['omitted_sections'] ?? [],
            'sectionCount' => count($v15['pages'][0]['sections'] ?? $json['sections'] ?? []),
            'addressed' => collect($evaluation)->where('status', 'addressed')->map(fn ($i) => $diagnosisIssues[$i['issue_id']]['title'] ?? null)->filter()->values()->all(),
            'issuesTotal' => count($evaluation),
            'qa' => ['passed' => $findings->where('status', 'passed')->count(), 'total' => $findings->count(), 'failed' => $findings->where('status', 'failed')->values()->all(), 'fixes' => $report?->report_json['fixes'] ?? []],
            'assetsPreserved' => count($v15['provenance']['asset_source_ids'] ?? []),
            'dna' => $dna,
            'issues' => $issues,
            'oldCta' => $dna['cta_inventory'][0]['label'] ?? null,
            'newCta' => $json['hero']['primary_cta_label'] ?? null,
            'plans' => Plan::whereIn('plan_key', ['starter', 'agency'])->orderBy('sort_order')->get(),
            'user' => auth()->user(),
            'editCount' => count($json['v16']['user_edits'] ?? []),
        ]);
    }
}
