<?php

namespace App\Jobs;

use App\Models\DesignSpec;
use App\Models\VisualQaReport;
use App\Services\VisualQa\BlueprintRefiner;
use App\Services\VisualQa\BrowserService;
use App\Services\VisualQa\VisualQaEvaluator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PRD v1.4 visual_qa: builds the real static artifact, captures desktop +
 * mobile screenshots, and runs deterministic checks (AC-015). Node/
 * Playwright is an optional, separately-verified capability — if it isn't
 * available in this environment the report fails with a clear status
 * rather than silently claiming an unverified capability (AC-020).
 */
class RunVisualQaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $timeout = 150;

    public int $tries = 1;

    public function __construct(public readonly int $reportId) {}

    /**
     * Blueprint sites only: apply bounded, deterministic fixes and QA the new version again,
     * up to redo.qa_max_refinements passes. Everything the fixes cannot touch stays reported.
     */
    private function refineIfNeeded(VisualQaReport $report, DesignSpec $spec, array $findings, BlueprintRefiner $refiner): void
    {
        $attempt = (int) ($report->report_json['attempt'] ?? 1);
        if (! isset($spec->design_spec_json['v15']) || $attempt > (int) config('redo.qa_max_refinements', 2)) {
            return;
        }

        $refined = $refiner->refine($spec, $findings);
        if (! $refined) {
            return;
        }

        $next = VisualQaReport::create([
            'design_spec_id' => $refined['spec']->id,
            'status' => 'pending',
            'report_json' => ['attempt' => $attempt + 1, 'previous_report_id' => $report->id, 'refined_from_version' => $spec->version, 'fixes' => $refined['fixes']],
            'created_at' => now(),
        ]);
        $report->update(['report_json' => array_merge($report->report_json, ['refined_to_version' => $refined['spec']->version, 'fixes' => $refined['fixes']])]);

        self::dispatch($next->id);
    }

    /** Called when the worker is killed (timeout) or the job otherwise fails outside handle()'s own handling. */
    public function failed(?Throwable $exception): void
    {
        VisualQaReport::whereKey($this->reportId)->whereIn('status', ['pending', 'running'])
            ->update(['status' => 'failed', 'error_message' => 'Visual QA stopped before it finished. Please try again.']);
    }

    public function handle(VisualQaEvaluator $evaluator, BrowserService $browser, BlueprintRefiner $refiner): void
    {
        $report = VisualQaReport::find($this->reportId);

        if (! $report) {
            return;
        }

        $report->update(['status' => 'running']);

        $spec = DesignSpec::find($report->design_spec_id);
        $run = $spec?->analysisRun;

        if (! $spec || ! $run) {
            $report->update(['status' => 'failed', 'error_message' => 'The DesignSpec for this report no longer exists.']);

            return;
        }

        if (! $browser->isAvailable()) {
            $report->update([
                'status' => 'failed',
                'error_message' => $browser->id() === 'local'
                    ? 'Visual QA requires Node.js and Playwright, which are not available in this environment. Set REDO_BROWSER_PROVIDER=browserless to use a remote browser.'
                    : ($browser->id() === 'browserbase'
                        ? 'The Browserbase browser isn’t ready: it needs BROWSERBASE_API_KEY, Node.js and playwright-core (run npm install) on the server.'
                        : 'The remote browser provider is not configured.'),
            ]);

            return;
        }

        try {
            $result = $evaluator->evaluate($run, $spec);

            // Keep lineage written when this report was queued by a refinement pass.
            $report->update([
                'status' => 'complete',
                'report_json' => array_merge($report->report_json ?? [], $result),
            ]);

            $this->refineIfNeeded($report->fresh(), $spec, $result['findings'], $refiner);
        } catch (Throwable $e) {
            Log::error('Visual QA capture failed', ['report' => $report->id, 'error' => $e->getMessage()]);
            $report->update([
                'status' => 'failed',
                'error_message' => 'Something went wrong while running visual QA. Please try again.',
            ]);
        }
    }
}
