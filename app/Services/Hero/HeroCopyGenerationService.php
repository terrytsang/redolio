<?php

namespace App\Services\Hero;

use App\Models\AnalysisRun;
use App\Models\HeroCopyOption;
use App\Models\PositioningProfile;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\MetaModelClient;
use App\Services\Orchestration\ModelCallLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Generates up to three distinct hero-copy options from a positioning profile. The model WRITES and rates; this
 * service decides: every option goes through the hard factual gate, the editorial gate and the weighted score in
 * code, near-duplicates are dropped, and if nothing safe survives (or the model is down) a conservative option
 * built only from verified claims is offered instead. Options that fail a hard gate are stored as `rejected` so
 * the user can see why, and can never be selected.
 */
class HeroCopyGenerationService
{
    public function __construct(
        private readonly MetaModelClient $model,
        private readonly ModelCallLogger $logger,
        private readonly HeroCopyValidationService $validator,
        private readonly HeroCopyEvaluationService $evaluator,
        private readonly ConservativeHeroBuilder $conservative,
        private readonly HeroDestinations $destinations,
    ) {}

    /** Pick distinct strategies that suit the business; direct-positioning is always a candidate. */
    public function selectStrategies(PositioningProfile $profile, int $count): array
    {
        $haystack = mb_strtolower($profile->business_model.' '.$profile->business_category.' '.$profile->brand_voice);
        $scores = [];
        foreach (config('hero_strategies') as $id => $s) {
            $scores[$id] = collect($s['best_for'])->filter(fn ($b) => str_contains($haystack, str_replace('_', ' ', $b)) || str_contains($haystack, $b))->count() + ($id === 'direct-positioning' ? 1.5 : 0);
        }
        arsort($scores);

        return array_slice(array_keys($scores), 0, max(1, min($count, count($scores))));
    }

    /** @return Collection<int, HeroCopyOption> ranked, best first */
    public function generate(AnalysisRun $run, ?array $designSpecJson = null, ?string $variantId = null, ?string $designTone = null, int $count = 3, bool $useModel = true): Collection
    {
        $profile = $run->currentPositioningProfile();
        if (! $profile || ! in_array($profile->status, ['ready', 'needs_confirmation'], true)) {
            throw new \RuntimeException('A positioning profile must be ready before hero copy can be generated.');
        }
        $count = max(1, min($count, (int) config('hero_layout.default_options', 3)));
        $available = $this->destinations->forSpec($designSpecJson ?? $run->currentDesignSpec()?->design_spec_json);
        $businessName = (string) ($run->currentWebsiteDna()?->dna_json['business_name'] ?? 'The business');
        $variant = HeroLayoutConstraints::variant($variantId);
        $strategies = $this->selectStrategies($profile, $count);

        $candidates = [];
        $modelId = null;
        $modelNote = null;
        $relocate = [];
        if ($useModel) {
            try {
                [$candidates, $modelId, $relocate] = $this->callModel($run, $profile, $strategies, $available, $variant, $designTone);
            } catch (MetaApiException $e) {
                $modelNote = 'Hero copy generation was unavailable; a conservative option built from verified facts is offered.';
                Log::info('Hero copy model unavailable', ['run' => $run->public_id, 'reason' => $e->getMessage()]);
            }
        }

        $batch = HeroCopyOption::latestBatch($run->id) + 1;
        $built = [];
        foreach ($candidates as $c) {
            $built[] = $this->evaluate($c['copy'], $profile, $available, $variant, $c['scores'], $c['meta'] + ['source' => 'model']);
        }
        $built = $this->dropDuplicates($built);

        $viable = collect($built)->filter(fn ($b) => $b['validation']['factual_gate'] === 'pass')->count();
        if ($viable === 0) { // nothing safe: a conservative option, never "recommended"
            $fallback = $this->conservative->build($profile, $businessName, $available);
            if ($fallback) {
                $b = $this->evaluate($fallback, $profile, $available, $variant, null, ['source' => 'conservative', 'rationale' => $modelNote ?? 'Built only from verified or confirmed facts because no generated option passed the factual checks.', 'assumptions' => [], 'risks' => []]);
                if ($b['recommendation'] === 'recommended') {
                    $b['recommendation'] = 'needs_review';
                }
                $built[] = $b;
            }
        }

        HeroCopyOption::where('analysis_run_id', $run->id)->where('status', 'candidate')->update(['status' => 'superseded']);

        $rows = collect($built)->map(function ($b) use ($run, $profile, $batch) {
            return HeroCopyOption::create([
                'analysis_run_id' => $run->id, 'positioning_profile_id' => $profile->id, 'batch' => $batch,
                'strategy_id' => $b['copy']->strategyId, 'eyebrow' => $b['copy']->eyebrow, 'headline' => $b['copy']->headline, 'description' => $b['copy']->description,
                'primary_cta_json' => $b['copy']->primaryCta, 'secondary_cta_json' => $b['copy']->secondaryCta, 'trust_signals_json' => $b['copy']->trustSignals,
                'evidence_ids_json' => $b['copy']->evidenceIds, 'assumptions_json' => $b['meta']['assumptions'] ?? [], 'risks_json' => $b['meta']['risks'] ?? [],
                'score_breakdown_json' => $b['breakdown'], 'total_score' => $b['total'], 'recommendation' => $b['recommendation'],
                'hard_gate_status' => $b['validation']['factual_gate'], 'validation_json' => $b['validation'] + ['relocate' => $b['meta']['relocate'] ?? []],
                'rationale' => $b['meta']['rationale'] ?? null,
                'status' => $b['recommendation'] === 'rejected' ? 'rejected' : 'candidate', 'source' => $b['meta']['source'], 'created_at' => now(),
            ]);
        });

        $ranked = $this->evaluator->rank($rows, $profile, $variantId);
        $kept = $ranked->filter(fn ($o) => $o->status === 'candidate')->take($count)->pluck('id')->all();
        // Beyond the requested count, surplus candidates are retired rather than shown.
        $rows->filter(fn ($o) => $o->status === 'candidate' && ! in_array($o->id, $kept, true))->each->update(['status' => 'superseded']);

        return $ranked->map->fresh()->values();
    }

    /** @return array{copy: HeroCopy, validation: array, breakdown: array, total: float, recommendation: string, meta: array} */
    private function evaluate(HeroCopy $copy, PositioningProfile $profile, array $available, string $variant, ?array $scores, array $meta): array
    {
        $validation = $this->validator->validate($copy, $profile, $available, true);
        if ($scores === null) { // conservative option: deterministic neutral scoring, explicitly not a model judgement
            $scores = array_fill_keys(array_keys(config('hero_layout.weights')), ['score' => 72, 'rationale' => 'Conservative option built from verified facts; scored neutrally.']);
        }
        $s = $this->evaluator->score($scores);
        // Editorial warnings cost points in code, so a flawed option cannot ride the model's self-rating to "recommended".
        $penalty = 5 * collect($validation['issues'])->where('gate', 'editorial')->whereIn('severity', ['warn', 'fail'])->count();
        $total = max(0.0, round($s['total'] - $penalty, 1));

        return ['copy' => $copy, 'validation' => $validation, 'breakdown' => $s['breakdown'], 'total' => $total, 'recommendation' => $this->evaluator->recommendation($total, $validation), 'meta' => $meta];
    }

    /** Near-duplicate headlines/strategies collapse to the better-scored one (PRD: not three paraphrases). */
    private function dropDuplicates(array $built): array
    {
        usort($built, fn ($a, $b) => $b['total'] <=> $a['total']);
        $kept = [];
        foreach ($built as $b) {
            $dupe = collect($kept)->contains(fn ($k) => $k['validation']['factual_gate'] === 'pass' && $b['validation']['factual_gate'] === 'pass'
                && (TextTools::overlap($k['copy']->headline, $b['copy']->headline) >= 0.5 || $k['copy']->strategyId === $b['copy']->strategyId));
            if (! $dupe) {
                $kept[] = $b;
            }
        }

        return $kept;
    }

    /** @return array{0: array<int, array{copy: HeroCopy, scores: array, meta: array}>, 1: ?string, 2: array} */
    private function callModel(AnalysisRun $run, PositioningProfile $profile, array $strategies, array $available, string $variant, ?string $tone): array
    {
        $usable = $profile->evidence()->get()->filter->isFactual()->map(fn ($c) => ['claim_key' => $c->claim_key, 'field' => $c->field, 'value' => $c->claim_value, 'status' => $c->status])->values()->all();
        $diagnosis = app(HeroMessagingDiagnosisService::class);
        $input = [
            'positioning' => ['business_category' => $profile->business_category, 'business_model' => $profile->business_model, 'brand_voice' => $profile->brand_voice, 'conversion_goal' => $profile->conversion_goal, 'unknowns' => $profile->unknowns_json ?? []],
            'usable_claims' => $usable,
            'current_hero' => $profile->existing_hero_json,
            'diagnosis' => array_map(fn ($f) => ['check' => $f['check'], 'finding' => $f['finding']], $diagnosis->diagnose($diagnosis->siteHero($profile), $profile, $variant)['findings']),
            'strategies' => array_map(fn ($id) => ['strategy_id' => $id] + array_intersect_key(config("hero_strategies.{$id}"), array_flip(['name', 'purpose', 'formula', 'caution'])), $strategies),
            'layout' => ['variant' => $variant, 'preferred_h1_lines' => HeroLayoutConstraints::preferredH1Lines($variant, 'desktop'), 'copy_rule' => HeroLayoutConstraints::copyRule($variant), 'limits' => config('hero_layout.headline')],
            'design_direction_tone' => $tone,
            'available_destinations' => array_keys($available),
            'option_count' => count($strategies),
        ];
        $hash = hash('sha256', (string) json_encode(['stage' => 'hero_copy_options', 'run' => $run->public_id, 'profile' => $profile->id, 'v' => $profile->version, 'variant' => $variant, 'claims' => array_column($usable, 'claim_key')]));

        try {
            $response = $this->model->generateHeroCopyOptions($input, ['run_id' => $run->public_id]);
            $this->logger->logSuccess($run, 'hero_copy_options', $hash, $response);
        } catch (MetaApiException $e) {
            $this->logger->logFailure($run, 'hero_copy_options', $hash, $e->errorCode);

            throw $e;
        }

        $d = $response->data;
        $out = [];
        foreach (array_slice((array) ($d['copy_options'] ?? []), 0, 6) as $o) {
            if (! is_array($o) || empty($o['headline'])) {
                continue;
            }
            $cta = fn ($c) => is_array($c) && ! empty($c['label']) ? ['label' => TextTools::fit((string) $c['label'], 40), 'destination_key' => (string) ($c['destination_key'] ?? '')] + ($available[$c['destination_key'] ?? ''] ?? []) : null;
            $strategy = array_key_exists((string) ($o['strategy_id'] ?? ''), config('hero_strategies')) ? $o['strategy_id'] : 'direct-positioning';
            $copy = new HeroCopy(
                ! empty($o['eyebrow']) ? TextTools::fit((string) $o['eyebrow'], 60) : null, trim((string) $o['headline']), ! empty($o['description']) ? trim((string) $o['description']) : null,
                $cta($o['primary_cta'] ?? null), $cta($o['secondary_cta'] ?? null),
                array_values(array_filter(array_map(fn ($t) => is_array($t) && ! empty($t['text']) ? ['text' => TextTools::fit((string) $t['text'], 80), 'claim_key' => (string) ($t['claim_key'] ?? '')] : null, (array) ($o['trust_signals'] ?? [])))),
                array_values(array_filter((array) ($o['evidence_ids'] ?? []), 'is_string')), $strategy,
            );
            $out[] = ['copy' => $copy, 'scores' => (array) ($o['criterion_scores'] ?? []), 'meta' => [
                'rationale' => TextTools::fit((string) ($o['positioning_rationale'] ?? ''), 500),
                'assumptions' => array_values(array_filter((array) ($o['assumptions'] ?? []), 'is_string')), 'risks' => array_values(array_filter((array) ($o['risks'] ?? []), 'is_string')),
                'relocate' => (array) ($d['relocate'] ?? []),
            ]];
        }

        return [$out, $response->modelIdentifier, (array) ($d['relocate'] ?? [])];
    }
}
