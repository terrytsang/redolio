<?php

namespace App\Services\Assets;

use App\DTO\Crawler\SiteSnapshotDto;
use App\Models\AnalysisRun;
use App\Models\WebsiteAsset;
use App\Services\Crawler\SafeHttpFetcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Builds the asset inventory (PRD asset_preservation_intelligence, Phase A)
 * from image candidates the crawler already observed. Every download goes
 * through SafeHttpFetcher, so it is subject to the exact same SSRF/DNS/
 * redirect/size protections as page fetches — an attacker-controlled image
 * URL gets no special treatment. Classification here is a deterministic
 * heuristic baseline; AssetClassificationService (Phase A's Meta stage)
 * may refine asset_type/preservation_priority afterward, but the app must
 * work correctly even if that model call never runs.
 */
class WebsiteAssetInventoryService
{
    public function __construct(private readonly SafeHttpFetcher $fetcher) {}

    public function build(AnalysisRun $run, SiteSnapshotDto $snapshot): void
    {
        $maxAssets = (int) config('assets.max_assets_per_run', 15);
        $candidates = $this->collectCandidates($snapshot);

        $stored = 0;
        foreach ($candidates as $candidate) {
            if ($stored >= $maxAssets) {
                break;
            }

            if ($this->storeAsset($run, $candidate)) {
                $stored++;
            }
        }
    }

    /**
     * Dedupes by resolved URL and orders favicons/logos/heading-adjacent
     * images first so the per-run cap keeps the most useful assets.
     *
     * @return array<int, array{url: string, type_hint: string, alt: ?string, width: ?int, height: ?int, dom_context: string, nearby_heading: ?string, page_url: string}>
     */
    private function collectCandidates(SiteSnapshotDto $snapshot): array
    {
        $seen = [];
        $favicons = [];
        $images = [];

        foreach ($snapshot->pages as $page) {
            if ($page->faviconUrl && ! isset($seen[$page->faviconUrl])) {
                $seen[$page->faviconUrl] = true;
                $favicons[] = [
                    'url' => $page->faviconUrl,
                    'type_hint' => 'favicon',
                    'alt' => null,
                    'width' => null,
                    'height' => null,
                    'dom_context' => 'head',
                    'nearby_heading' => null,
                    'page_url' => $page->url,
                ];
            }

            foreach ($page->imageCandidates as $candidate) {
                if (isset($seen[$candidate['src']])) {
                    continue;
                }
                $seen[$candidate['src']] = true;

                $images[] = [
                    'url' => $candidate['src'],
                    'type_hint' => 'image',
                    'alt' => $candidate['alt'],
                    'width' => $candidate['width'],
                    'height' => $candidate['height'],
                    'dom_context' => $candidate['dom_context'],
                    'nearby_heading' => $candidate['nearby_heading'],
                    'page_url' => $page->url,
                ];
            }
        }

        return array_merge($favicons, $images);
    }

    /**
     * @param  array{url: string, type_hint: string, alt: ?string, width: ?int, height: ?int, dom_context: string, nearby_heading: ?string, page_url: string}  $candidate
     */
    private function storeAsset(AnalysisRun $run, array $candidate): bool
    {
        $assetKey = 'a'.substr(md5($candidate['url']), 0, 12);

        try {
            $result = $this->fetcher->fetch($candidate['url']);
        } catch (Throwable $e) {
            Log::info('Asset download failed', ['run' => $run->public_id, 'url' => $candidate['url'], 'error' => $e->getMessage()]);

            return false;
        }

        if ($result->httpStatus < 200 || $result->httpStatus >= 300) {
            return false;
        }

        $mime = strtolower(trim(explode(';', $result->contentType)[0] ?? ''));
        $allowed = config('assets.allowed_mime_types', []);

        if (! isset($allowed[$mime])) {
            return false;
        }

        $maxBytes = (int) config('assets.max_bytes_per_asset', 5_000_000);
        if (strlen($result->body) > $maxBytes || strlen($result->body) === 0) {
            return false;
        }

        if ($mime === 'image/svg+xml') {
            // getimagesizefromstring() doesn't understand SVG (it's XML,
            // not a raster format) — logos are very commonly SVG, so this
            // would otherwise silently drop almost every vector logo.
            [$width, $height] = $this->svgDimensions($result->body);

            if ($width === null && ! str_contains($result->body, '<svg')) {
                return false; // Content-Type claimed SVG; the bytes are not actually one.
            }

            // <img src="asset.svg"> never executes embedded scripts in a
            // browser, but strip them anyway as defense in depth before
            // this ever gets stored.
            $body = (string) preg_replace('#<script\b[^>]*>.*?</script>#is', '', $result->body);
        } else {
            $imageInfo = @getimagesizefromstring($result->body);
            if ($imageInfo === false) {
                return false; // Content-Type claimed an image; the bytes are not actually one.
            }
            $width = $imageInfo[0];
            $height = $imageInfo[1];
            $body = $result->body;
        }

        $extension = $allowed[$mime];
        $storedPath = "assets/{$run->public_id}/{$assetKey}.{$extension}";
        Storage::disk(config('assets.storage_disk', 'public'))->put($storedPath, $body);

        $classification = $this->classify($candidate, $width ?? 0, $height ?? 0);

        // A picture with see-through areas (a logo, a cut-out) is later shown as it is, not on a coloured frame.
        $hasAlpha = false;
        if (in_array($mime, ['image/png', 'image/webp'], true) && ($gd = @imagecreatefromstring($body))) {
            imagealphablending($gd, false);
            $hasAlpha = \App\Support\ImageAlpha::hasTransparency($gd);
        }

        WebsiteAsset::updateOrCreate(
            ['analysis_run_id' => $run->id, 'asset_key' => $assetKey],
            [
                'source_page_url' => $candidate['page_url'],
                'original_url' => $candidate['url'],
                'stored_path' => $storedPath,
                'asset_type' => $classification['asset_type'],
                'mime_type' => $mime,
                'has_alpha' => $hasAlpha,
                'file_size' => strlen($body),
                'width' => $width,
                'height' => $height,
                'alt_text' => $candidate['alt'] ? Str::limit($candidate['alt'], 250, '') : null,
                'nearby_heading' => $candidate['nearby_heading'] ? Str::limit($candidate['nearby_heading'], 250, '') : null,
                'dom_context' => $candidate['dom_context'],
                'detected_role' => $classification['detected_role'],
                'relevance_score' => $classification['relevance_score'],
                'preservation_priority' => $classification['preservation_priority'],
                'recommended_usage' => $classification['recommended_usage'],
                'recommended_action' => $classification['recommended_action'],
                'rights_status' => 'unknown',
                'created_at' => now(),
            ]
        );

        return true;
    }

    /**
     * Deterministic heuristic classification — no model call required.
     * Conservative on purpose: when unsure, it labels something as
     * needing user review rather than guessing a strong recommendation.
     *
     * @param  array{url: string, type_hint: string, alt: ?string, width: ?int, height: ?int, dom_context: string, nearby_heading: ?string, page_url: string}  $candidate
     * @return array{asset_type: string, detected_role: string, relevance_score: float, preservation_priority: string, recommended_usage: string, recommended_action: string}
     */
    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function svgDimensions(string $svg): array
    {
        if (preg_match('/<svg\b[^>]*\bwidth="([\d.]+)[a-z%]*"[^>]*\bheight="([\d.]+)[a-z%]*"/is', $svg, $m)) {
            return [(int) round((float) $m[1]), (int) round((float) $m[2])];
        }

        if (preg_match('/<svg\b[^>]*\bviewBox="[\d.\-]+\s+[\d.\-]+\s+([\d.]+)\s+([\d.]+)"/is', $svg, $m)) {
            return [(int) round((float) $m[1]), (int) round((float) $m[2])];
        }

        return [null, null];
    }

    private function classify(array $candidate, int $actualWidth, int $actualHeight): array
    {
        if ($candidate['type_hint'] === 'favicon') {
            return [
                'asset_type' => 'favicon',
                'detected_role' => 'favicon',
                'relevance_score' => 0.5,
                'preservation_priority' => 'medium',
                'recommended_usage' => 'Browser tab icon',
                'recommended_action' => 'preserve',
            ];
        }

        $alt = strtolower((string) $candidate['alt']);
        $heading = strtolower((string) $candidate['nearby_heading']);
        $filename = strtolower(pathinfo(parse_url($candidate['url'], PHP_URL_PATH) ?? '', PATHINFO_FILENAME));
        $text = $alt.' '.$heading.' '.str_replace(['-', '_'], ' ', $filename);
        $inHeader = in_array($candidate['dom_context'], ['nav', 'header'], true);
        $aspectIsSquareish = $actualHeight > 0 && abs(($actualWidth / $actualHeight) - 1) < 0.3;

        if ($inHeader && (str_contains($text, 'logo') || str_contains(strtolower($candidate['url']), 'logo'))) {
            return [
                'asset_type' => 'logo',
                'detected_role' => 'primary_logo',
                'relevance_score' => 0.9,
                'preservation_priority' => 'high',
                'recommended_usage' => 'Site navigation / header branding',
                'recommended_action' => 'preserve',
            ];
        }

        if (str_contains($text, 'team') && $aspectIsSquareish) {
            return [
                'asset_type' => 'team_portrait',
                'detected_role' => 'team_member_photo',
                'relevance_score' => 0.75,
                'preservation_priority' => 'high',
                'recommended_usage' => 'Team / about section',
                'recommended_action' => 'preserve',
            ];
        }

        $portraitKeywords = ['portrait', 'founder', 'about', 'profile', 'headshot', 'avatar'];
        if ($aspectIsSquareish && collect($portraitKeywords)->contains(fn ($kw) => str_contains($text, $kw))) {
            return [
                'asset_type' => 'profile_portrait',
                'detected_role' => 'founder_or_profile_photo',
                'relevance_score' => 0.85,
                'preservation_priority' => 'high',
                'recommended_usage' => 'Hero or about-section profile photo',
                'recommended_action' => 'preserve',
            ];
        }

        if (str_contains($text, 'portfolio') || str_contains($text, 'project') || str_contains($text, 'case stud')) {
            return [
                'asset_type' => 'portfolio_image',
                'detected_role' => 'portfolio_item',
                'relevance_score' => 0.6,
                'preservation_priority' => 'medium',
                'recommended_usage' => 'Portfolio / work showcase section',
                'recommended_action' => 'reuse',
            ];
        }

        if ($inHeader && $actualWidth >= 600) {
            return [
                'asset_type' => 'hero_image',
                'detected_role' => 'hero_banner',
                'relevance_score' => 0.6,
                'preservation_priority' => 'medium',
                'recommended_usage' => 'Hero section background or feature image',
                'recommended_action' => 'needs_user_review',
            ];
        }

        if ($actualWidth <= 64 && $actualHeight <= 64) {
            return [
                'asset_type' => 'icon',
                'detected_role' => 'decorative_icon',
                'relevance_score' => 0.2,
                'preservation_priority' => 'low',
                'recommended_usage' => 'Decorative icon',
                'recommended_action' => 'omit',
            ];
        }

        return [
            'asset_type' => 'other',
            'detected_role' => 'uncategorized',
            'relevance_score' => 0.3,
            'preservation_priority' => 'low',
            'recommended_usage' => 'Unclassified image',
            'recommended_action' => 'needs_user_review',
        ];
    }
}
