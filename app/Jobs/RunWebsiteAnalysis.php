<?php

namespace App\Jobs;

use App\Models\AnalysisRun;
use App\Services\Orchestration\AnalyzeWebsiteAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunWebsiteAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly int $analysisRunId) {}

    public function handle(AnalyzeWebsiteAction $analyzeWebsiteAction): void
    {
        $run = AnalysisRun::find($this->analysisRunId);

        if (! $run) {
            return;
        }

        try {
            $analyzeWebsiteAction->execute($run);
        } catch (Throwable $e) {
            Log::error('Unhandled failure running website analysis', ['run' => $run->public_id, 'error' => $e->getMessage()]);

            if ($run->status !== 'complete') {
                $run->update([
                    'status' => 'failed',
                    'error_code' => 'unexpected_error',
                    'safe_error_message' => 'Something went wrong while analyzing this website. Please try again.',
                ]);
            }

            throw $e;
        }
    }
}
