<?php

namespace App\Services\DesignDirection;

use App\Models\AnalysisRun;
use App\Models\DesignDirection;
use App\Models\DesignDirectionRecommendation;
use App\Services\Catalogue\DesignCatalogue;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\Recommendation\DirectionScorer;
use App\Services\Recommendation\ProjectSignals;
use App\Services\MetaApi\MetaModelClient;
use App\Services\MetaApi\Validation\DesignDirectionRankingValidator;
use App\Services\Orchestration\ModelCallLogger;
use Illuminate\Support\Collection;

/**
 * PRD upgrade v1.2 design_selection_algorithm: "Hybrid deterministic
 * candidate filtering plus Meta Model API ranking and rationale."
 * Deterministic filtering here means "only is_selectable=true recipes" —
 * only directions with real Blade templates (see
 * PreviewRenderer::TEMPLATE_VIEWS) are ever eligible, regardless of what
 * the catalogue grows to later (AT-32). The deterministic score also
 * serves as the fallback order if the Meta ranking call fails — the
 * selector always works.
 */
class DesignDirectionSelectorService
{
    public function __construct(
        private readonly MetaModelClient $metaClient,
        private readonly DesignDirectionRankingValidator $validator,
        private readonly ModelCallLogger $modelCallLogger,
        private readonly DirectionScorer $scorer,
        private readonly DesignCatalogue $catalogue,
    ) {}

    /**
     * @return Collection<int, DesignDirectionRecommendation>
     */
    public function recommend(AnalysisRun $run): Collection
    {
        $eligible = DesignDirection::selectable()->get()->filter->hasRenderableTemplate()->values();

        if ($eligible->isEmpty()) {
            return collect();
        }

        $dna = $run->currentWebsiteDna()?->dna_json ?? [];
        $diagnosis = $run->currentDiagnosisReport()?->issues_json ?? [];
        $visualProfile = $run->currentDesignProfile()?->profile_json ?? [];

        $details = $this->scoreDetails($eligible, $dna, $visualProfile, ProjectSignals::fromRun($run));
        $deterministicScores = array_map(fn ($d) => $d['total'], $details);

        $ranked = $this->rankWithMeta($run, $dna, $visualProfile, $diagnosis, $eligible, $deterministicScores, $details);

        $run->designDirectionRecommendations()->delete();

        return $ranked->map(fn ($entry, $index) => DesignDirectionRecommendation::create([
            'analysis_run_id' => $run->id,
            'direction_key' => $entry['direction_key'],
            'rank' => $index + 1,
            'score_json' => [
                'deterministic' => $deterministicScores[$entry['direction_key']] ?? 0,
                'model' => $entry['score'] ?? null,
                'factors' => $details[$entry['direction_key']]['factors'] ?? null,
                'reasons' => $details[$entry['direction_key']]['reasons'] ?? [],
                'cautions' => $details[$entry['direction_key']]['cautions'] ?? [],
            ],
            'rationale' => $entry['rationale'],
            'is_recommended' => $entry['is_recommended'],
            'created_at' => now(),
        ]));
    }

    /**
     * v1.5 directions are scored on the six weighted factors; directions without
     * a catalogue entry (admin-created / captured) keep the original keyword score.
     *
     * @param  Collection<int, DesignDirection>  $eligible
     * @return array<string, array{total: float, factors: ?array, reasons: array, cautions: array}>
     */
    private function scoreDetails(Collection $eligible, array $dna, array $visualProfile, ProjectSignals $signals): array
    {
        $legacy = $this->deterministicScores($eligible, $dna, $visualProfile);
        $out = [];
        foreach ($eligible as $direction) {
            $entry = $this->catalogue->direction($direction->direction_key);
            $out[$direction->direction_key] = $entry
                ? $this->scorer->score($entry, $signals)
                : ['total' => $legacy[$direction->direction_key], 'factors' => null, 'reasons' => [], 'cautions' => []];
        }

        return $out;
    }

    /**
     * @param  Collection<int, DesignDirection>  $eligible
     * @return array<string, float>
     */
    private function deterministicScores(Collection $eligible, array $dna, array $visualProfile): array
    {
        $industry = strtolower((string) ($dna['industry'] ?? ''));
        $summary = strtolower((string) ($dna['business_summary'] ?? ''));
        $personality = strtolower(implode(' ', (array) ($visualProfile['brand_personality'] ?? [])));
        $haystack = "{$industry} {$summary} {$personality}";

        $scores = [];
        foreach ($eligible as $direction) {
            $recipe = $direction->recipe_json;
            $score = 0.5; // neutral baseline so every eligible direction remains a real candidate

            foreach ((array) ($recipe['best_for'] ?? []) as $term) {
                if ($haystack !== '' && str_contains($haystack, strtolower((string) $term))) {
                    $score += 0.2;
                }
            }
            foreach ((array) ($recipe['brand_traits'] ?? []) as $trait) {
                if ($personality !== '' && str_contains($personality, strtolower((string) $trait))) {
                    $score += 0.1;
                }
            }

            $scores[$direction->direction_key] = round(min($score, 1.0), 2);
        }

        return $scores;
    }

    /**
     * @param  Collection<int, DesignDirection>  $eligible
     * @param  array<string, float>  $deterministicScores
     * @return Collection<int, array{direction_key: string, rank: int, score: float|null, rationale: string, is_recommended: bool}>
     */
    private function rankWithMeta(AnalysisRun $run, array $dna, array $visualProfile, array $diagnosis, Collection $eligible, array $deterministicScores, array $details = []): Collection
    {
        $eligibleKeys = $eligible->pluck('direction_key')->all();
        $eligiblePayload = $eligible->map(fn (DesignDirection $d) => [
            'direction_key' => $d->direction_key,
            'name' => $d->name,
            'description' => $d->recipe_json['description'] ?? '',
            'best_for' => $d->recipe_json['best_for'] ?? [],
            'brand_traits' => $d->recipe_json['brand_traits'] ?? [],
            'avoid_when' => $d->recipe_json['avoid_when'] ?? [],
        ])->all();

        $requestHash = hash('sha256', (string) json_encode(['stage' => 'design_direction_ranking', 'run' => $run->public_id, 'eligible' => $eligibleKeys]));

        try {
            $response = $this->metaClient->rankDesignDirections($dna, $visualProfile, $diagnosis, $eligiblePayload, ['run_id' => $run->public_id]);
            $this->modelCallLogger->logSuccess($run, 'design_direction_ranking', $requestHash, $response);

            return collect($this->validator->validate($response->data, $eligibleKeys));
        } catch (MetaApiException $e) {
            $this->modelCallLogger->logFailure($run, 'design_direction_ranking', $requestHash, $e->errorCode);

            // Fall back to the deterministic order — the selector must
            // always work, even when the Meta ranking call is unavailable.
            $fallback = collect($eligibleKeys)
                ->sortByDesc(fn ($key) => $deterministicScores[$key] ?? 0)
                ->values();

            return $fallback->map(fn ($key, $index) => [
                'direction_key' => $key,
                'rank' => $index + 1,
                'score' => $deterministicScores[$key] ?? null,
                'rationale' => ($details[$key]['reasons'] ?? []) !== []
                    ? implode(' ', array_slice($details[$key]['reasons'], 0, 2))
                    : 'Ranked by deterministic fit scoring — AI ranking was unavailable.',
                'is_recommended' => $index < 3,
            ]);
        }
    }
}
