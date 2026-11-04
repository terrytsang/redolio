<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisRun;
use App\Services\Catalogue\DesignCatalogue;
use App\Services\DesignDirection\DesignDirectionSelectorService;
use App\Services\Recommendation\BlueprintRecommender;
use App\Services\Recommendation\PersistDesignSelection;
use App\Services\Recommendation\ProjectSignals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON surface for the direction → blueprint selection flow. Access follows the
 * existing analysis routes (the run's unguessable public_id). Scores are
 * decision-support only, so they are deliberately not exposed — reasons are.
 */
class ProjectDesignController extends Controller
{
    public function recommendations(Request $request, AnalysisRun $analysisRun, DesignDirectionSelectorService $selector, BlueprintRecommender $blueprints, DesignCatalogue $catalogue): JsonResponse
    {
        if (! $analysisRun->currentWebsiteDna()) {
            return response()->json(['message' => 'Website DNA must complete before recommendations are available.'], 409);
        }

        $signals = ProjectSignals::fromRun($analysisRun);
        $recs = $selector->recommend($analysisRun);

        $directions = $recs->map(function ($rec) use ($catalogue) {
            $entry = $catalogue->direction($rec->direction_key);

            return [
                'direction_id' => $rec->direction_key,
                'name' => $entry['name'] ?? $rec->direction_key,
                'recommended' => $rec->is_recommended,
                'rationale' => $rec->rationale,
                'reasons' => $rec->score_json['reasons'] ?? [],
                'trade_offs' => $rec->score_json['cautions'] ?? [],
                'primary_category_ids' => $entry['primary_category_ids'] ?? [],
            ];
        })->values();

        $blueprintFor = $request->input('direction_id');
        $payload = [
            'suggested_category_id' => $signals->inferCategory($catalogue),
            'directions' => $directions,
            'catalogue_version' => $catalogue->version(),
        ];
        if (is_string($blueprintFor) && $directions->contains('direction_id', $blueprintFor)) {
            $payload['blueprints'] = array_map(fn ($r) => [
                'blueprint_id' => $r['blueprint']['id'],
                'name' => $r['blueprint']['name'],
                'version' => $r['blueprint']['version'],
                'reasons' => $r['reasons'],
                'missing_content' => $r['missing_content'],
                'trade_offs' => $r['trade_offs'],
            ], $blueprints->recommend($blueprintFor, $signals));
        }

        return response()->json($payload);
    }

    public function selection(Request $request, AnalysisRun $analysisRun, PersistDesignSelection $persist): JsonResponse
    {
        $data = $request->validate([
            'direction_id' => ['required', 'string'],
            'blueprint_id' => ['nullable', 'string', 'max:64'],
            'category_id' => ['nullable', 'string', 'max:32'],
        ]);

        $selection = $persist->handle($analysisRun, [
            'direction_key' => $data['direction_id'],
            'blueprint_id' => $data['blueprint_id'] ?? null,
            'category_id' => $data['category_id'] ?? null,
        ], dispatch: false);

        return response()->json(['data' => [
            'selection_id' => $selection->id,
            'direction_id' => $selection->direction_key,
            'direction_version' => $selection->direction_version,
            'blueprint_id' => $selection->blueprint_id,
            'blueprint_version' => $selection->blueprint_version,
            'category_id' => $selection->category_id,
            'catalogue_version' => $selection->catalogue_version,
            'status' => $selection->status,
        ]], 201);
    }
}
