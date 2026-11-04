<?php

namespace App\Services\Recommendation;

/**
 * Deterministic decision-support scoring (PRD v1.5 scoring_model). Scores rank
 * candidates and produce short evidence-based reasons; they are not objective
 * design-quality measurements and are never shown as numbers to users.
 */
class DirectionScorer
{
    public const WEIGHTS = [
        'business_fit' => 0.25, 'audience_fit' => 0.20, 'brand_continuity' => 0.20,
        'conversion_goal_fit' => 0.15, 'asset_fit' => 0.10, 'content_fit' => 0.10,
    ];

    /** @return array{total: float, factors: array<string, float>, reasons: string[], cautions: string[]} */
    public function score(array $direction, ProjectSignals $s): array
    {
        $reasons = $cautions = [];

        // business_fit: industry/offering terms vs who the direction is recommended for
        $terms = ProjectSignals::words($direction['recommended_for']);
        $hits = array_values(array_filter($terms, fn ($t) => str_contains($s->business, $t)));
        $business = $s->hasDna ? min(1.0, 0.3 + 0.25 * count($hits)) : 0.5;
        $hits && $reasons[] = 'Suits businesses like yours ('.implode(', ', array_slice($hits, 0, 3)).').';

        $avoid = array_values(array_filter(ProjectSignals::words($direction['avoid_when']), fn ($t) => strlen($t) > 4 && str_contains($s->business, $t)));
        if ($avoid) {
            $business = max(0.1, $business - 0.25);
            $cautions[] = 'Less suited when '.rtrim($direction['avoid_when'][0] ?? '', '.').'.';
        }

        // audience_fit
        $audWords = ProjectSignals::words(array_merge($direction['recommended_for'], [$direction['positioning']]));
        $audHits = array_values(array_filter($audWords, fn ($t) => $s->audience !== '' && str_contains($s->audience, $t)));
        $audience = $s->audience === '' ? 0.5 : min(1.0, 0.3 + 0.25 * count($audHits));
        $audHits && $reasons[] = 'Matches your audience ('.implode(', ', array_slice($audHits, 0, 2)).').';

        // brand_continuity
        $brandWords = ProjectSignals::words(array_merge($direction['visual_traits'], [$direction['positioning']]));
        $brandHits = array_values(array_filter($brandWords, fn ($t) => $s->personality !== '' && str_contains($s->personality, $t)));
        $brand = $s->personality === '' ? 0.5 : min(1.0, 0.3 + 0.25 * count($brandHits));
        $brandHits && $reasons[] = 'Keeps your brand feel ('.implode(', ', array_slice($brandHits, 0, 2)).').';

        // conversion_goal_fit
        $goal = $s->goal === 'unknown' ? 0.5 : (in_array($s->goal, $direction['conversion_goals'], true) ? 1.0 : 0.3);
        $goal === 1.0 && $reasons[] = "Built to drive your main goal: {$s->goal}.";

        // asset_fit: image-led directions need real photography
        $images = $s->realImageCount;
        $asset = match ($direction['image_dependence']) {
            'high' => min(1.0, $images / 6),
            'medium' => min(1.0, 0.4 + $images / 10),
            default => 0.8,
        };
        if ($direction['image_dependence'] === 'high') {
            if ($images >= 4) {
                $reasons[] = "Your {$images} real photos suit this image-led direction.";
            } else {
                $cautions[] = "Image-led — your site has {$images} usable photo".($images === 1 ? '' : 's').', so results depend on stock or generated imagery.';
            }
        }

        // content_fit: how much content exists vs how much the direction needs
        $richness = min(1.0, ($s->offeringCount + count($s->trustTypes) * 2) / 8);
        $target = ['low' => 0.3, 'medium' => 0.6, 'high' => 0.9][$direction['content_depth']];
        $content = $s->hasDna ? max(0.0, 1 - abs($richness - $target)) : 0.5;

        $factors = [
            'business_fit' => $business, 'audience_fit' => $audience, 'brand_continuity' => $brand,
            'conversion_goal_fit' => $goal, 'asset_fit' => $asset, 'content_fit' => $content,
        ];
        $total = 0.0;
        foreach (self::WEIGHTS as $name => $w) {
            $total += $w * $factors[$name];
        }

        return ['total' => round($total, 3), 'factors' => array_map(fn ($v) => round($v, 2), $factors), 'reasons' => $reasons, 'cautions' => $cautions];
    }
}
