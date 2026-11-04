<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDesignSpecJob;
use App\Jobs\RunVisualQaJob;
use App\Models\AnalysisRun;
use App\Models\DesignDirection;
use App\Models\DesignSelection;
use App\Models\DesignSpec;
use App\Models\VisualQaReport;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\Orchestration\GenerateDesignSpecAction;
use App\Services\Rendering\PreviewRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class PreviewController extends Controller
{
    public function current(AnalysisRun $analysisRun, PreviewRenderer $renderer)
    {
        $spec = $analysisRun->currentDesignSpec();

        if (! $spec) {
            return redirect()->route('analyses.show', $analysisRun->public_id);
        }

        return $this->renderPreview($analysisRun, $spec, $renderer);
    }

    public function version(AnalysisRun $analysisRun, int $version, PreviewRenderer $renderer)
    {
        $spec = $analysisRun->designSpecs()->where('version', $version)->firstOrFail();

        return $this->renderPreview($analysisRun, $spec, $renderer);
    }

    /**
     * Regenerates the REDO for a different design direction and returns the
     * rendered fragments as JSON, so the preview page can swap them in
     * place (mega-menu direction switcher) without a full page redirect.
     */
    public function switchDirection(Request $request, AnalysisRun $analysisRun, GenerateDesignSpecAction $generateDesignSpecAction, PreviewRenderer $renderer)
    {
        $eligibleKeys = DesignDirection::selectable()->get()->filter->hasRenderableTemplate()->pluck('direction_key')->all();

        $validated = $request->validate([
            'direction_key' => ['required', 'string', 'in:'.implode(',', $eligibleKeys)],
        ]);

        $dna = $analysisRun->currentWebsiteDna();
        $diagnosis = $analysisRun->currentDiagnosisReport();

        if (! $dna || ! $diagnosis) {
            return response()->json(['message' => 'Website DNA and diagnosis must be ready before generating a redesign.'], 422);
        }

        $direction = DesignDirection::where('direction_key', $validated['direction_key'])->firstOrFail();
        $preferences = $analysisRun->currentDesignSelection()?->preferences_json ?? [
            'preserve_logo' => true,
            'preserve_images' => true,
            'preserve_wording' => true,
        ];

        $selection = DesignSelection::create([
            'analysis_run_id' => $analysisRun->id,
            'direction_key' => $direction->direction_key,
            'direction_version' => $direction->version,
            'preferences_json' => $preferences,
            'selected_at' => now(),
            'status' => 'generating',
        ]);

        try {
            $spec = $generateDesignSpecAction->execute($analysisRun, $dna, $diagnosis, $direction->direction_key, $preferences);
            $selection->update(['status' => 'complete', 'design_spec_id' => $spec->id]);
        } catch (MetaApiException $e) {
            $selection->update(['status' => 'failed', 'error_message' => 'Redesign generation is temporarily unavailable. Please try again shortly.']);

            return response()->json(['message' => 'Redesign generation is temporarily unavailable. Please try again shortly.'], 503);
        } catch (Throwable $e) {
            $selection->update(['status' => 'failed', 'error_message' => 'Something went wrong while generating this REDO. Please try again.']);
            report($e);

            return response()->json(['message' => 'Something went wrong while generating this REDO. Please try again.'], 500);
        }

        $spec->load('evaluationReport');
        $otherVersions = $analysisRun->designSpecs()->orderByDesc('version')->get(['id', 'version']);

        return response()->json([
            'version' => $spec->version,
            'directionName' => $direction->name,
            'url' => route('analyses.preview.version', [$analysisRun->public_id, $spec->version]),
            'toolbarHtml' => view('analyses.partials.preview-toolbar-meta', [
                'run' => $analysisRun,
                'spec' => $spec,
                'otherVersions' => $otherVersions,
            ])->render(),
            'redoPaneHtml' => view('analyses.partials.preview-redo-pane', [
                'renderedHtml' => $renderer->render($spec->design_spec_json, $analysisRun),
            ])->render(),
            'asideHtml' => view('analyses.partials.preview-aside', [
                'spec' => $spec,
                'evaluation' => $spec->evaluationReport,
                'diagnosis' => $analysisRun->currentDiagnosisReport(),
            ])->render(),
        ]);
    }

    /**
     * PRD v1.4 visual_qa: kicks off the real screenshot-based QA worker for
     * the given DesignSpec (desktop + mobile) and returns the report id so
     * the preview page can poll for results without a redirect.
     */
    public function runVisualQa(AnalysisRun $analysisRun, int $version)
    {
        $spec = $analysisRun->designSpecs()->where('version', $version)->firstOrFail();

        // Idempotent: a double-click or a retry returns the run already in flight instead of starting another.
        $inFlight = VisualQaReport::where('design_spec_id', $spec->id)->whereIn('status', ['pending', 'running'])
            ->where('created_at', '>', now()->subMinutes(15))->latest('id')->first();
        if ($inFlight) {
            return response()->json(['report_id' => $inFlight->id]);
        }

        // Cost control: browser time is bounded per project per day (refinement passes count too).
        $today = VisualQaReport::whereIn('design_spec_id', $analysisRun->designSpecs()->pluck('id'))->where('created_at', '>=', now()->startOfDay())->count();
        if ($today >= (int) config('redo.qa_max_reports_per_run_per_day', 20)) {
            return response()->json(['message' => 'The daily visual QA limit for this project has been reached. Try again tomorrow.'], 429);
        }

        $report = VisualQaReport::create([
            'design_spec_id' => $spec->id,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        RunVisualQaJob::dispatch($report->id);

        return response()->json(['report_id' => $report->id]);
    }

    public function visualQaStatus(AnalysisRun $analysisRun, VisualQaReport $report)
    {
        abort_unless($report->designSpec?->analysis_run_id === $analysisRun->id, 404);

        // A worker that died mid-run never reports back; don't leave the page spinning forever.
        if (in_array($report->status, ['pending', 'running'], true) && $report->created_at?->lt(now()->subMinutes(15))) {
            $report->update(['status' => 'failed', 'error_message' => 'Visual QA timed out. Please run it again.']);
        }

        return response()->json([
            'status' => $report->status,
            'report' => $report->report_json,
            'error_message' => $report->error_message,
        ]);
    }

    /**
     * PRD v1.4 refinement_policy: starts a bounded refinement explicitly
     * grounded in a Visual QA report's failed findings, reusing the same
     * async "generating" flow as the plain "Refine this design" action.
     */
    public function refineFromVisualQa(AnalysisRun $analysisRun, VisualQaReport $report): RedirectResponse
    {
        abort_unless($report->designSpec?->analysis_run_id === $analysisRun->id, 404);

        $spec = DesignSpec::find($report->design_spec_id);

        if (! $spec || $report->status !== 'complete') {
            return back()->with('status', 'This visual QA report is not ready to refine from.');
        }

        $failedFindings = collect($report->report_json['findings'] ?? [])
            ->where('status', 'failed')
            ->values()
            ->all();

        if (empty($failedFindings)) {
            return back()->with('status', 'No failed findings to refine from.');
        }

        $priorSelection = DesignSelection::where('design_spec_id', $spec->id)->latest('selected_at')->first();
        $directionKey = $priorSelection->direction_key ?? $spec->template_key;
        $preferences = $priorSelection->preferences_json ?? ['preserve_logo' => true, 'preserve_images' => true, 'preserve_wording' => true];

        $selection = DesignSelection::create([
            'analysis_run_id' => $analysisRun->id,
            'direction_key' => $directionKey,
            'direction_version' => $priorSelection->direction_version ?? 1,
            'preferences_json' => $preferences,
            'selected_at' => now(),
            'status' => 'generating',
            'refine_from_design_spec_id' => $spec->id,
            'visual_qa_findings_json' => $failedFindings,
        ]);

        GenerateDesignSpecJob::dispatch($selection->id);

        return redirect()->route('analyses.design-directions.generating', [$analysisRun->public_id, $selection->id]);
    }

    private function renderPreview(AnalysisRun $analysisRun, DesignSpec $spec, PreviewRenderer $renderer)
    {
        if (auth()->check() && $analysisRun->user_id === null) {
            $analysisRun->update(['user_id' => auth()->id()]);
        }

        return view('analyses.preview', [
            'run' => $analysisRun,
            'spec' => $spec,
            'renderedHtml' => $renderer->render($spec->design_spec_json, $analysisRun),
            'evaluation' => $spec->evaluationReport,
            'diagnosis' => $analysisRun->currentDiagnosisReport(),
            'siteSnapshot' => $analysisRun->siteSnapshot,
            'otherVersions' => $analysisRun->designSpecs()->orderByDesc('version')->get(['id', 'version']),
            'directions' => DesignDirection::selectable()->orderBy('name')->get()->filter->hasRenderableTemplate()->values(),
            'visualQaReport' => $spec->latestVisualQaReport(),
        ]);
    }
}
