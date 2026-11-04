<?php

namespace App\Services\Orchestration;

use App\Models\AnalysisRun;
use App\Models\DesignSpec;
use App\Models\InteractionEvent;
use App\Models\NextRedoRecommendation;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\MetaModelClient;
use App\Services\MetaApi\Validation\NextRedoHypothesisValidator;
use Illuminate\Support\Collection;

/**
 * PRD Phase 9 / FR-101: aggregates permitted preview interaction events
 * deterministically in Laravel, then asks the model for one labelled
 * hypothesis — never a proven-impact claim. Returns null when there is
 * no signal to aggregate; a hypothesis cannot be "linked to actual ...
 * signals" (UJ3) if none were recorded.
 */
class GenerateNextRedoRecommendationAction
{
    public function __construct(
        private readonly MetaModelClient $metaClient,
        private readonly NextRedoHypothesisValidator $validator,
        private readonly ModelCallLogger $modelCallLogger,
    ) {}

    public function execute(AnalysisRun $run, DesignSpec $designSpec): ?NextRedoRecommendation
    {
        $events = $run->interactionEvents()->where('design_spec_id', $designSpec->id)->get();

        if ($events->isEmpty()) {
            return null;
        }

        $signalSummary = $this->aggregate($events);

        $requestHash = hash('sha256', json_encode(['stage' => 'next_redo_optional', 'design_spec_id' => $designSpec->id, 'signal_summary' => $signalSummary]));

        try {
            $response = $this->metaClient->generateNextRedoHypothesis($signalSummary, ['run_id' => $run->public_id]);
            $this->modelCallLogger->logSuccess($run, 'next_redo_optional', $requestHash, $response);
        } catch (MetaApiException $e) {
            $this->modelCallLogger->logFailure($run, 'next_redo_optional', $requestHash, $e->errorCode);

            throw $e;
        }

        $validated = $this->validator->validate($response->data, $signalSummary);

        return NextRedoRecommendation::create([
            'analysis_run_id' => $run->id,
            'design_spec_id' => $designSpec->id,
            'signal_summary_json' => $validated['signal_summary'],
            'recommendation' => $validated['suggested_change'],
            'rationale' => $validated['reason'],
            'hypothesis_label' => $validated['status'],
            'created_at' => now(),
        ]);
    }

    /**
     * @param  Collection<int, InteractionEvent>  $events
     */
    private function aggregate($events): array
    {
        $byEventName = $events->countBy('event_name');

        $byElementKey = $events->whereNotNull('element_key')
            ->countBy('element_key')
            ->sortDesc();

        return [
            'total_events' => $events->count(),
            'distinct_sessions' => $events->pluck('session_token_hash')->filter()->unique()->count(),
            'counts_by_event_name' => $byEventName->all(),
            'top_elements' => $byElementKey->take(5)->map(fn ($count, $key) => ['element_key' => $key, 'count' => $count])->values()->all(),
            'data_label' => 'simulated_or_low_volume_preview_interactions',
        ];
    }
}
