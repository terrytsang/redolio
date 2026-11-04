<?php

namespace App\Jobs;

use App\Models\AnalysisRun;
use App\Models\HeroGeneration;
use App\Models\User;
use App\Services\Hero\HeroCopyGenerationService;
use App\Services\Hero\HeroCopyRefinementService;
use App\Services\Hero\PositioningIntelligenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Runs the model-backed hero steps (positioning profile, copy options) off the request, with pollable status. */
class RunHeroGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    /** Seconds after which an unfinished job is treated as dead (timeout plus slack). */
    public const STALE_AFTER = 420;

    public function __construct(public readonly int $generationId) {}

    public function handle(PositioningIntelligenceService $positioning, HeroCopyGenerationService $copy, HeroCopyRefinementService $fit): void
    {
        $g = HeroGeneration::find($this->generationId);
        if (! $g || $g->status !== 'pending') {
            return;
        }
        $g->update(['status' => 'running']);
        $run = AnalysisRun::find($g->analysis_run_id);

        try {
            $result = match ($g->kind) {
                'positioning' => ['profile_id' => $positioning->build($run)->id],
                'options' => ['option_ids' => $copy->generate($run, variantId: $this->heroVariant($run))->pluck('id')->all()],
                'render' => $fit->run($run, User::find($run->user_id)),
                default => throw new \InvalidArgumentException("Unsupported hero job [{$g->kind}]."),
            };
            $g->update(['status' => 'complete', 'result_json' => $result, 'completed_at' => now()]);
        } catch (Throwable $e) {
            Log::warning('Hero generation failed', ['run' => $run?->public_id, 'kind' => $g->kind, 'error' => $e->getMessage()]);
            $g->update(['status' => 'failed', 'error_message' => 'We couldn’t finish this step. You can try again.', 'completed_at' => now()]);
        }
    }

    private function heroVariant(AnalysisRun $run): ?string
    {
        return collect($run->currentDesignSpec()?->design_spec_json['v15']['pages'][0]['sections'] ?? [])->firstWhere('component_id', 'hero')['variant_id'] ?? null;
    }

    /** Worker killed or timed out: record it, so the run is never stuck behind a job that will not finish. */
    public function failed(?Throwable $e): void
    {
        HeroGeneration::whereKey($this->generationId)->whereIn('status', ['pending', 'running'])
            ->update(['status' => 'failed', 'error_message' => 'This step took too long and was stopped. You can try again.', 'completed_at' => now()]);
    }
}
