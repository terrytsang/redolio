<?php

namespace App\Services\Recommendation;

use App\Models\AnalysisRun;
use App\Services\Catalogue\DesignCatalogue;

/**
 * The project facts recommendation scoring runs on, derived from Website DNA,
 * the visual profile and the asset inventory. Plain data: crawled text is
 * only ever matched against, never interpreted as instructions.
 */
class ProjectSignals
{
    private const STOP = ['the', 'and', 'for', 'with', 'that', 'this', 'from', 'your', 'our', 'are', 'you', 'who', 'all', 'their', 'have', 'been', 'into', 'more', 'than', 'also'];

    public function __construct(
        public readonly string $business,   // industry + summary + offerings, lower-cased
        public readonly string $audience,   // audience segments + needs
        public readonly string $personality, // brand personality + tone of voice
        public readonly string $goal,        // lead|booking|purchase|inquiry|awareness|unknown
        public readonly int $offeringCount,
        public readonly array $trustTypes,
        public readonly int $realImageCount,
        public readonly bool $hasProfile,
        public readonly bool $hasDna,
    ) {}

    public static function fromRun(AnalysisRun $run): self
    {
        $dna = $run->currentWebsiteDna()?->dna_json ?? [];
        $profile = $run->currentDesignProfile()?->profile_json ?? [];

        $offerings = (array) ($dna['offerings'] ?? []);
        $business = strtolower(trim(($dna['industry'] ?? '').' '.($dna['business_summary'] ?? '').' '.implode(' ', array_map(fn ($o) => ($o['name'] ?? '').' '.($o['description'] ?? ''), $offerings))));
        $audience = strtolower(implode(' ', array_map(fn ($a) => ($a['segment'] ?? '').' '.implode(' ', (array) ($a['needs'] ?? [])), (array) ($dna['target_audience'] ?? []))));
        $personality = strtolower(implode(' ', array_merge((array) ($profile['brand_personality'] ?? []), (array) ($dna['tone_of_voice'] ?? []))));

        $realImages = $run->websiteAssets()->get()->filter->isRealPhoto()->count();

        return new self(
            $business, $audience, $personality,
            (string) ($dna['primary_conversion_goal'] ?? 'unknown'),
            count($offerings),
            array_values(array_unique(array_column((array) ($dna['trust_signals'] ?? []), 'type'))),
            $realImages,
            $profile !== [],
            $dna !== [],
        );
    }

    /** Significant words of a phrase list. @return string[] */
    public static function words(string|array $text): array
    {
        $text = strtolower(is_array($text) ? implode(' ', $text) : $text);
        preg_match_all('/[a-z][a-z\-]{2,}/', $text, $m);

        return array_values(array_unique(array_diff($m[0], self::STOP)));
    }

    /** Which discovery category the business most resembles (null when nothing matches). */
    public function inferCategory(DesignCatalogue $catalogue): ?string
    {
        $best = null;
        $bestHits = 0;
        foreach ($catalogue->categories() as $c) {
            $hits = 0;
            foreach ($c['keywords'] ?? [] as $kw) {
                if ($this->business !== '' && str_contains($this->business, $kw)) {
                    $hits++;
                }
            }
            if ($hits > $bestHits) {
                [$best, $bestHits] = [$c['id'], $hits];
            }
        }

        return $best;
    }
}
