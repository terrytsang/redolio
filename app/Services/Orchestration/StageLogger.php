<?php

namespace App\Services\Orchestration;

use App\Models\AnalysisRun;
use App\Models\AnalysisStageLog;

/**
 * Records analysis stage progress (FR-010/UI-01) so the UI can distinguish
 * running, complete, partial and failed states per stage.
 */
class StageLogger
{
    public function start(AnalysisRun $run, string $stage): AnalysisStageLog
    {
        $run->update(['current_stage' => $stage, 'status' => 'running']);

        return AnalysisStageLog::create([
            'analysis_run_id' => $run->id,
            'stage' => $stage,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function complete(AnalysisStageLog $log, ?string $safeMessage = null, array $metadata = []): void
    {
        $log->update([
            'status' => 'complete',
            'completed_at' => now(),
            'safe_message' => $safeMessage,
            'metadata_json' => $metadata,
        ]);
    }

    public function partial(AnalysisStageLog $log, string $safeMessage, array $metadata = []): void
    {
        $log->update([
            'status' => 'partial',
            'completed_at' => now(),
            'safe_message' => $safeMessage,
            'metadata_json' => $metadata,
        ]);
    }

    public function fail(AnalysisRun $run, AnalysisStageLog $log, string $errorCode, string $safeMessage): void
    {
        $log->update([
            'status' => 'failed',
            'completed_at' => now(),
            'message_code' => $errorCode,
            'safe_message' => $safeMessage,
        ]);

        $run->update([
            'status' => 'failed',
            'error_code' => $errorCode,
            'safe_error_message' => $safeMessage,
            'completed_at' => now(),
        ]);
    }
}
