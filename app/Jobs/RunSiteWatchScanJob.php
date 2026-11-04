<?php

namespace App\Jobs;

use App\Models\AnalysisRun;
use App\Models\SiteWatch;
use App\Services\Orchestration\AnalyzeWebsiteAction;
use App\Services\Watch\WatchReportBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/** One rescan of a watched website, then its Watch report. Scheduled scans are never counted as the person's own analyses. */
class RunSiteWatchScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly int $watchId) {}

    public function handle(AnalyzeWebsiteAction $analyze, WatchReportBuilder $reports): void
    {
        $watch = SiteWatch::find($this->watchId);
        if (! $watch || $watch->last_status === 'running' && $watch->updated_at?->gt(now()->subMinutes(20))) {
            return;
        }
        $watch->update(['last_status' => 'running', 'last_error' => null]);

        $previous = $watch->last_analysis_run_id ? AnalysisRun::find($watch->last_analysis_run_id) : null;
        // First scan of a site the person already analysed: compare with that analysis.
        $previous ??= AnalysisRun::where('user_id', $watch->user_id)->where('normalized_url', $watch->normalized_url)->whereIn('status', ['complete', 'partial'])->latest('id')->first();

        $run = AnalysisRun::create([
            'user_id' => $watch->user_id, 'submitted_url' => $watch->submitted_url, 'normalized_url' => $watch->normalized_url,
            'status' => 'pending', 'current_stage' => 'validating_url', 'source_mode' => 'live', 'is_watch_scan' => true,
        ]);

        try {
            $analyze->execute($run->fresh());
            $run = $run->fresh();
            if (! in_array($run->status, ['complete', 'partial'], true) || ! $run->currentDiagnosisReport()) {
                throw new \RuntimeException('We couldn’t read the website this time.');
            }
            $reports->build($watch, $run, $previous);
            $watch->update(['last_status' => 'ok', 'last_error' => null, 'last_analysis_run_id' => $run->id, 'last_scanned_at' => now()]);
        } catch (Throwable $e) {
            Log::warning('Site watch rescan failed', ['watch' => $watch->id, 'error' => $e->getMessage()]);
            $reports->failed($watch, $run, 'We couldn’t finish this rescan. We’ll try again at the next scheduled time.');
            $watch->update(['last_status' => 'failed', 'last_error' => 'The last rescan didn’t finish.', 'last_scanned_at' => now()]);
        }
    }

    public function failed(?Throwable $e): void
    {
        SiteWatch::whereKey($this->watchId)->where('last_status', 'running')->update(['last_status' => 'failed', 'last_error' => 'The last rescan didn’t finish.']);
    }
}
