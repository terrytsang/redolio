<?php

namespace App\Services\Hero;

use App\Models\PositioningProfile;
use Illuminate\Support\Collection;

/**
 * Scores hero copy with the PRD's weighted criteria. The MODEL supplies a 0–100 rating and a rationale per criterion;
 * the weighting, the total, the thresholds and the ranking are all computed here. A score never overrides a hard gate,
 * and is never presented as a measured conversion prediction.
 */
class HeroCopyEvaluationService
{
    public const TIERS = ['recommended' => 3, 'needs_review' => 2, 'rewrite' => 1, 'rejected' => 0];

    /** @return array{breakdown: array<string, array{score: float, weight: int, weighted: float, rationale: string}>, total: float} */
    public function score(array $criterionScores): array
    {
        $breakdown = [];
        $total = 0.0;
        foreach (config('hero_layout.weights') as $criterion => $weight) {
            $raw = $criterionScores[$criterion] ?? [];
            $score = is_array($raw) ? (float) ($raw['score'] ?? 0) : (float) $raw;
            $score = max(0.0, min(100.0, $score));
            $weighted = $score * $weight / 100;
            $total += $weighted;
            $breakdown[$criterion] = ['score' => round($score, 1), 'weight' => $weight, 'weighted' => round($weighted, 1), 'rationale' => is_array($raw) ? TextTools::fit((string) ($raw['rationale'] ?? ''), 300) : ''];
        }

        return ['breakdown' => $breakdown, 'total' => round($total, 1)];
    }

    /** @param  array{factual_gate: string, editorial_gate: string, issues: array}  $validation */
    public function recommendation(float $total, array $validation): string
    {
        if ($validation['factual_gate'] === 'fail') {
            return 'rejected'; // a hard gate is never overridden by a score
        }
        $t = config('hero_layout.thresholds');
        $tier = $total >= $t['recommended'] ? 'recommended' : ($total >= $t['needs_review'] ? 'needs_review' : 'rewrite');

        if ($validation['editorial_gate'] === 'fail') {
            return 'rewrite';
        }
        $hasWarnings = collect($validation['issues'])->contains('severity', 'warn');
        if ($tier === 'recommended' && $hasWarnings) {
            return 'needs_review'; // warnings (e.g. an unconfirmed "trusted") mean a human should look
        }

        return $tier;
    }

    /**
     * Order by tier, then total, then the PRD tie-breakers: stronger factual support, clearer category recognition,
     * a simpler headline, and fit with the hero layout without reducing type.
     *
     * @param  Collection<int, \App\Models\HeroCopyOption>  $options
     */
    public function rank(Collection $options, PositioningProfile $profile, ?string $variantId = null): Collection
    {
        $claims = $profile->evidence()->get();
        $categoryText = trim($profile->business_category.' '.$claims->where('field', 'offering')->pluck('claim_value')->implode(' '));
        $variant = HeroLayoutConstraints::variant($variantId);

        $key = function ($o) use ($categoryText, $variant) {
            return [
                self::TIERS[$o->recommendation ?? 'rejected'] ?? 0,
                (float) $o->total_score,
                count($o->evidence_ids_json ?? []),
                TextTools::coverage($categoryText, $o->headline) > 0 ? 1 : 0,
                -TextTools::wordCount($o->headline),
                HeroLayoutConstraints::estimateLines($o->headline, $variant, 'desktop') <= HeroLayoutConstraints::preferredH1Lines($variant, 'desktop') ? 1 : 0,
            ];
        };

        return $options->sort(fn ($a, $b) => $key($b) <=> $key($a))->values();
    }
}
