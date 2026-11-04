<?php

namespace App\Services\Assets;

use App\Models\AnalysisRun;
use App\Models\WebsiteDna;

/**
 * Best-effort: when a crawled site has no suitable hero-caliber image at
 * all (the symptom that prompted this — some sites have no real banner,
 * or only low-resolution icons/avatars), fetches a fallback image instead
 * of letting the redesign ship an empty "Feature image" placeholder box.
 * Prefers a free, licensed stock photo (Pexels — a real photo generally
 * reads as more credible on a business site) when configured; otherwise
 * falls back to an AI-generated illustration (Meta Muse Image), which
 * works out of the box since it reuses the same Meta API credentials
 * already required for the rest of the app. Never overrides or competes
 * with a real original image — only runs when the run genuinely has none.
 */
class FallbackAssetService
{
    private const MIN_HERO_WIDTH = 500;

    public function __construct(
        private readonly PexelsStockPhotoService $stockPhotos,
        private readonly MuseImageService $museImage,
    ) {}

    public function ensureHeroImagery(AnalysisRun $run, WebsiteDna $dna): void
    {
        if (! $this->stockPhotos->isConfigured() && ! $this->museImage->isConfigured()) {
            return;
        }

        if (! $this->hasUsableHeroAsset($run)) {
            $this->fetchFallback($run, 'hero_banner', $this->heroSearchQuery($dna), $this->heroIllustrationPrompt($dna));
        }

        if (! $this->hasUsableSectionAsset($run)) {
            $this->fetchFallback($run, 'section_image', $this->sectionSearchQuery($dna), $this->sectionIllustrationPrompt($dna));
        }
    }

    private function fetchFallback(AnalysisRun $run, string $role, string $stockQuery, string $illustrationPrompt): void
    {
        if ($this->stockPhotos->isConfigured() && $this->stockPhotos->fetchOne($run, $stockQuery, $role)) {
            return;
        }

        if ($this->museImage->isConfigured()) {
            $this->museImage->generate($run, $illustrationPrompt, $role);
        }
    }

    private function hasUsableHeroAsset(AnalysisRun $run): bool
    {
        return $run->websiteAssets()
            ->whereNotIn('asset_type', ['favicon', 'icon', 'logo'])
            ->whereIn('recommended_action', ['preserve', 'reuse', 'needs_user_review'])
            ->where('width', '>=', self::MIN_HERO_WIDTH)
            ->exists();
    }

    private function hasUsableSectionAsset(AnalysisRun $run): bool
    {
        return $run->websiteAssets()
            ->whereNotIn('asset_type', ['favicon', 'icon', 'logo'])
            ->whereIn('recommended_action', ['preserve', 'reuse', 'needs_user_review'])
            ->count() > 1;
    }

    private function heroSearchQuery(WebsiteDna $dna): string
    {
        $industry = $dna->dna_json['industry'] ?? null;

        return $industry ? "{$industry} business" : 'modern business workspace';
    }

    private function sectionSearchQuery(WebsiteDna $dna): string
    {
        $industry = $dna->dna_json['industry'] ?? null;

        return $industry ? "{$industry} team at work" : 'team collaborating office';
    }

    /**
     * House-rules-aligned prompt (docs/design-taste-skill.md): a clean,
     * specific editorial illustration, never a generic "AI slop" cliché
     * (no gratuitous gradients/glass/3D, no text or logos the model might
     * hallucinate, nothing implying it's a real photo of this business).
     */
    private function heroIllustrationPrompt(WebsiteDna $dna): string
    {
        $industry = $dna->dna_json['industry'] ?? 'small business';

        return "A clean, modern flat-vector editorial illustration representing a {$industry}. ".
            'Simple geometric shapes, a restrained two-to-three colour palette, confident whitespace, '.
            'no gradients, no 3D, no glass/blur effects, no readable text or logos, no people\'s faces '.
            'in close-up. Suitable as a wide hero banner image for a professional business website.';
    }

    private function sectionIllustrationPrompt(WebsiteDna $dna): string
    {
        $industry = $dna->dna_json['industry'] ?? 'small business';

        return 'A clean, modern flat-vector editorial illustration of people collaborating, themed around '.
            "a {$industry}. Simple geometric shapes, a restrained two-to-three colour palette, confident ".
            'whitespace, no gradients, no 3D, no glass/blur effects, no readable text or logos.';
    }
}
