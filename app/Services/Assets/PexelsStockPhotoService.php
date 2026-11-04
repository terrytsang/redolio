<?php

namespace App\Services\Assets;

use App\Models\AnalysisRun;
use App\Models\WebsiteAsset;
use App\Services\Crawler\SafeHttpFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Real, documented Pexels API v1 integration (https://www.pexels.com/api/
 * documentation/) used only as a fallback — never a replacement for a
 * business's own real imagery. Free to use under the Pexels license (no
 * attribution legally required, but we store and can display it anyway).
 * Like the optional AI provider keys, this has no credentials until an
 * operator supplies PEXELS_API_KEY; isConfigured() lets callers skip the
 * whole feature cleanly rather than claim an unverified capability.
 */
class PexelsStockPhotoService
{
    public function __construct(private readonly SafeHttpFetcher $fetcher) {}

    public function isConfigured(): bool
    {
        return ! empty(config('stock_photos.pexels.api_key'));
    }

    /**
     * Searches Pexels and downloads the single best-matching photo,
     * storing it as a new WebsiteAsset the same way a crawled image would
     * be stored — so the rest of the asset pipeline (AssetResolver,
     * available_assets context, PreviewRenderer) treats it identically.
     */
    public function fetchOne(AnalysisRun $run, string $query, string $detectedRole): ?WebsiteAsset
    {
        $apiKey = config('stock_photos.pexels.api_key');
        if (empty($apiKey)) {
            return null;
        }

        try {
            $response = Http::withHeaders(['Authorization' => $apiKey])
                ->timeout(10)
                ->get('https://api.pexels.com/v1/search', [
                    'query' => $query,
                    'per_page' => 1,
                    'orientation' => 'landscape',
                ]);
        } catch (Throwable $e) {
            Log::info('Pexels search failed', ['run' => $run->public_id, 'query' => $query, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $photo = $response->json('photos.0');
        $imageUrl = $photo['src']['large'] ?? null;

        if (! is_array($photo) || ! $imageUrl) {
            return null;
        }

        try {
            $result = $this->fetcher->fetch($imageUrl);
        } catch (Throwable $e) {
            Log::info('Pexels image download failed', ['run' => $run->public_id, 'url' => $imageUrl, 'error' => $e->getMessage()]);

            return null;
        }

        if ($result->httpStatus < 200 || $result->httpStatus >= 300) {
            return null;
        }

        $mime = strtolower(trim(explode(';', $result->contentType)[0] ?? ''));
        $allowed = config('assets.allowed_mime_types', []);
        if (! isset($allowed[$mime]) || strlen($result->body) === 0) {
            return null;
        }

        $imageInfo = @getimagesizefromstring($result->body);
        if ($imageInfo === false) {
            return null;
        }

        $assetKey = 'stock'.substr(md5($imageUrl.$detectedRole), 0, 10);
        $extension = $allowed[$mime];
        $storedPath = "assets/{$run->public_id}/{$assetKey}.{$extension}";
        Storage::disk(config('assets.storage_disk', 'public'))->put($storedPath, $result->body);

        $photographer = $photo['photographer'] ?? 'Pexels';

        return WebsiteAsset::updateOrCreate(
            ['analysis_run_id' => $run->id, 'asset_key' => $assetKey],
            [
                'source_page_url' => $run->normalized_url,
                'original_url' => $imageUrl,
                'stored_path' => $storedPath,
                'asset_type' => 'hero_image',
                'asset_origin' => 'stock_photo',
                'attribution' => "Photo by {$photographer} on Pexels",
                'mime_type' => $mime,
                'file_size' => strlen($result->body),
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'detected_role' => $detectedRole,
                'relevance_score' => 0.4,
                'preservation_priority' => 'low',
                'recommended_usage' => 'Stock fallback image — no suitable original was found',
                'recommended_action' => 'reuse',
                'rights_status' => 'licensed_stock',
                'created_at' => now(),
            ]
        );
    }
}
