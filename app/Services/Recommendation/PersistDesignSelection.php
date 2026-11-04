<?php

namespace App\Services\Recommendation;

use App\Jobs\GenerateDesignSpecJob;
use App\Models\AnalysisRun;
use App\Models\DesignDirection;
use App\Models\DesignSelection;
use App\Services\Blueprint\BlueprintCatalog;
use App\Services\Catalogue\DesignCatalogue;
use Illuminate\Validation\ValidationException;

/**
 * Persists a user-APPROVED direction (+ optional blueprint) with exact versions,
 * and the options that were offered, for this project only. The user's explicit
 * choice is required: nothing here picks a direction or blueprint on its own.
 */
class PersistDesignSelection
{
    public function __construct(
        private readonly BlueprintCatalog $blueprints,
        private readonly BlueprintRecommender $recommender,
        private readonly DesignCatalogue $catalogue,
    ) {}

    /**
     * @param  array{direction_key: string, blueprint_id?: ?string, category_id?: ?string, preferences?: array}  $input
     */
    public function handle(AnalysisRun $run, array $input, bool $dispatch): DesignSelection
    {
        $eligible = DesignDirection::selectable()->get()->filter->hasRenderableTemplate()->keyBy('direction_key');
        $direction = $eligible->get($input['direction_key'])
            ?? throw ValidationException::withMessages(['direction_key' => 'That design direction is not available.']);

        $blueprint = null;
        if (! empty($input['blueprint_id'])) {
            $blueprint = collect($this->recommender->eligible($direction->direction_key))->firstWhere('id', $input['blueprint_id'])
                ?? throw ValidationException::withMessages(['blueprint_id' => 'That layout is not available for this direction.']);
        }

        $categoryId = $input['category_id'] ?? null;
        if ($categoryId !== null && ! in_array($categoryId, array_column($this->catalogue->categories(), 'id'), true)) {
            throw ValidationException::withMessages(['category_id' => 'Unknown category.']);
        }

        $signals = ProjectSignals::fromRun($run);
        $recommendedDirections = $run->designDirectionRecommendations()->where('is_recommended', true)->pluck('direction_key')->all();
        $recommendedBlueprints = array_map(fn ($r) => $r['blueprint']['id'], $this->recommender->recommend($direction->direction_key, $signals));

        $selection = DesignSelection::create([
            'analysis_run_id' => $run->id,
            'direction_key' => $direction->direction_key,
            'direction_version' => $direction->version,
            'preferences_json' => $input['preferences'] ?? [],
            'selected_at' => now(),
            'status' => $dispatch ? 'generating' : 'selected',
            'category_id' => $categoryId,
            'blueprint_id' => $blueprint['id'] ?? null,
            'blueprint_version' => $blueprint['version'] ?? null,
            'catalogue_version' => $this->catalogue->version(),
            'selection_context_json' => [
                'recommended_direction_keys' => $recommendedDirections,
                'recommended_blueprint_ids' => $recommendedBlueprints,
                'direction_overrode_recommendation' => $recommendedDirections !== [] && ! in_array($direction->direction_key, $recommendedDirections, true),
                'blueprint_overrode_recommendation' => $blueprint !== null && $recommendedBlueprints !== [] && ! in_array($blueprint['id'], $recommendedBlueprints, true),
                'eligible_blueprint_ids' => array_column($this->recommender->eligible($direction->direction_key), 'id'),
            ],
        ]);

        $dispatch && GenerateDesignSpecJob::dispatch($selection->id);

        return $selection;
    }
}
