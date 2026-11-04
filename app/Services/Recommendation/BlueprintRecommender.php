<?php

namespace App\Services\Recommendation;

use App\Services\Blueprint\BlueprintCatalog;
use App\Services\Catalogue\DesignCatalogue;

/**
 * Recommends up to three READY page blueprints for a chosen direction:
 * filters out unsuitable ones, scores the rest, and reports what content or
 * assets the project is missing for each (nothing is ever invented to fill gaps).
 */
class BlueprintRecommender
{
    public function __construct(
        private readonly BlueprintCatalog $blueprints,
        private readonly DesignCatalogue $catalogue,
    ) {}

    /** @return array<int, array{blueprint: array, score: float, reasons: string[], missing_content: string[], trade_offs: string[]}> */
    public function recommend(string $directionId, ProjectSignals $s, int $limit = 3): array
    {
        $category = $s->inferCategory($this->catalogue);
        $out = [];

        foreach ($this->eligible($directionId) as $bp) {
            $reasons = $missing = $tradeOffs = [];
            $score = 0.4;

            if ($category && in_array($category, $bp['category_ids'], true)) {
                $score += 0.35;
                $reasons[] = 'Built for '.collect($this->catalogue->categories())->firstWhere('id', $category)['name'].' websites like yours.';
            }

            foreach ($bp['section_sequence'] as $section) {
                $gap = $this->contentGap($section, $s);
                if ($gap === null) {
                    continue;
                }
                $missing[] = $gap['message'];
                $score -= $section['optional'] ? 0.03 : 0.1;
                $gap['trade_off'] && $tradeOffs[] = $gap['trade_off'];
            }
            $missing || $reasons[] = 'Everything this layout needs was found on your site.';

            $out[] = ['blueprint' => $bp, 'score' => round(max(0.0, $score), 3), 'reasons' => $reasons, 'missing_content' => $missing, 'trade_offs' => $tradeOffs];
        }

        usort($out, fn ($a, $b) => [$b['score'], $a['blueprint']['id']] <=> [$a['score'], $b['blueprint']['id']]);

        return array_slice($out, 0, $limit);
    }

    /** Ready blueprints compatible with the direction (the only ones a user may select). */
    public function eligible(string $directionId): array
    {
        return array_values(array_filter(
            $this->blueprints->forDirection($directionId),
            fn ($bp) => $this->blueprints->effectiveStatus($bp) === 'ready'
        ));
    }

    /** @return array{message: string, trade_off: ?string}|null */
    private function contentGap(array $section, ProjectSignals $s): ?array
    {
        $optional = $section['optional'];
        $effect = $optional ? ' — this section will be left out unless you add it.' : ' — you will need to provide it before generation.';

        return match ($section['component_id']) {
            'testimonials' => in_array('testimonial', $s->trustTypes, true) ? null
                : ['message' => 'No testimonials found on your site'.$effect, 'trade_off' => 'Less social proof without real customer quotes.'],
            'gallery' => $s->realImageCount >= 3 ? null
                : ['message' => ($s->realImageCount === 0 ? 'No usable photos' : "Only {$s->realImageCount} usable photo".($s->realImageCount === 1 ? '' : 's')).' found for the gallery'.$effect, 'trade_off' => 'Gallery will rely on stock or generated imagery.'],
            'pricing' => ['message' => 'Pricing is never invented — add your real plans or this section is omitted.', 'trade_off' => null],
            'statistics' => ['message' => 'Verified figures (years, projects, clients) are needed; none are made up'.$effect, 'trade_off' => null],
            'trust-strip' => in_array('client_logo', $s->trustTypes, true) ? null
                : ['message' => 'No client logos found'.$effect, 'trade_off' => null],
            'process', 'schedule', 'faq' => $s->offeringCount >= 2 ? null
                : ['message' => "Not enough source content for the {$section['component_id']} section".$effect, 'trade_off' => null],
            default => null,
        };
    }
}
