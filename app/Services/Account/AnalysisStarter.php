<?php

namespace App\Services\Account;

use App\Exceptions\SsrfViolationException;
use App\Jobs\RunWebsiteAnalysis;
use App\Models\AnalysisRun;
use App\Models\User;
use App\Services\Crawler\UrlSubmissionValidator;
use Illuminate\Validation\ValidationException;

/** Validates a submitted address and starts the scan. Shared by the homepage form and by "sign up first, then scan". */
class AnalysisStarter
{
    public function __construct(private readonly UrlSubmissionValidator $urls) {}

    /** @throws ValidationException */
    public function normalize(string $url): string
    {
        try {
            return $this->urls->validateAndNormalize($url);
        } catch (SsrfViolationException) {
            throw ValidationException::withMessages(['url' => 'Please enter a valid, publicly reachable website address.']);
        }
    }

    /** @throws ValidationException */
    public function start(string $submitted, string $normalized, User $user, ?string $idempotencyKey = null): AnalysisRun
    {
        $existing = AnalysisRun::where('normalized_url', $normalized)->whereIn('status', ['pending', 'running'])->where('created_at', '>=', now()->subMinutes(2))->first();
        if ($existing && ($existing->user_id === null || $existing->user_id === $user->id)) {
            $existing->user_id === null && $existing->update(['user_id' => $user->id]);

            return $existing;
        }

        if (config('redo.edition') === 'selfhosted') {
            $owned = AnalysisRun::where('user_id', $user->id)->distinct()->pluck('normalized_url');
            if ($owned->isNotEmpty() && ! $owned->contains($normalized)) {
                throw ValidationException::withMessages(['url' => 'This self-hosted installation is licensed for one website. Re-run analysis on your existing site, or use REDOLIO Cloud for multiple sites.']);
            }
        }

        $limit = $user->analysisLimit();
        if (config('redo.edition') !== 'selfhosted' && $limit !== null && $user->analysesUsed() >= $limit) {
            $plan = $user->currentPlan();
            throw ValidationException::withMessages(['url' => "You’ve used all {$limit} website analyses on the {$plan->name} plan. Delete one you no longer need from your dashboard to free up a place, or upgrade for more."]);
        }

        $run = AnalysisRun::create([
            'user_id' => $user->id, 'submitted_url' => $submitted, 'normalized_url' => $normalized, 'idempotency_key' => $idempotencyKey ?: null,
            'status' => 'pending', 'current_stage' => 'validating_url', 'source_mode' => 'live',
        ]);
        RunWebsiteAnalysis::dispatch($run->id);

        return $run;
    }
}
