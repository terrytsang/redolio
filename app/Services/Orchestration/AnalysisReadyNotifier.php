<?php

namespace App\Services\Orchestration;

use App\Mail\AnalysisReadyMail;
use App\Models\AnalysisRun;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Emails the opted-in address exactly once. A mail failure never fails the scan. */
class AnalysisReadyNotifier
{
    public function send(AnalysisRun $run): void
    {
        if (! $run->notify_email || $run->notified_at) {
            return;
        }
        // Claim first so two racing callers cannot both send.
        if (AnalysisRun::whereKey($run->id)->whereNull('notified_at')->update(['notified_at' => now()]) === 0) {
            return;
        }
        try {
            Mail::to($run->notify_email)->send(new AnalysisReadyMail($run));
        } catch (Throwable $e) {
            AnalysisRun::whereKey($run->id)->update(['notified_at' => null]); // allow a later retry
            Log::warning('Ready email failed', ['run' => $run->public_id, 'error' => $e->getMessage()]);
        }
    }
}
