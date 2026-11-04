<?php

namespace App\Services\Assets;

use App\Models\AnalysisRun;
use App\Models\WebsiteAsset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Meta Models API image generation (Muse Image) — see
 * docs/meta-api-verified.md for the live-verified contract (2026-11-02):
 * POST {base_url}/images/generations with {model, prompt}, response
 * data[0].b64_json (WEBP). Same base URL/auth/credentials as the already-
 * verified Muse Spark text model used throughout this app — no separate
 * API key to configure. Used only as a fallback when a site has no
 * suitable original image and no stock-photo key is configured (or the
 * stock search failed), never to replace a real image.
 */
class MuseImageService
{
    private const MODEL = 'muse-image-1.0';

    public function isConfigured(): bool
    {
        return ! empty(config('meta_api.base_url')) && ! empty(config('meta_api.api_key'));
    }

    /**
     * One image for the editor's "Meta AI Image" tab. Returns the raw image bytes (the editor re-encodes and stores them like any
     * other picture), or throws a message that is safe to show the person.
     */
    public function generateBytes(string $prompt): string
    {
        $baseUrl = config('meta_api.base_url');
        $apiKey = config('meta_api.api_key');
        if (empty($baseUrl) || empty($apiKey)) {
            throw new \RuntimeException('AI image generation isn’t set up yet.');
        }
        try {
            $response = Http::withToken($apiKey)->timeout((int) config('meta_api.timeout_seconds', 90))
                ->post(rtrim($baseUrl, '/').'/images/generations', ['model' => self::MODEL, 'prompt' => $prompt]);
        } catch (Throwable $e) {
            Log::info('Muse Image generation failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('The image took too long to make. Please try again — this didn’t use one of your images.');
        }
        if (! $response->successful()) {
            Log::info('Muse Image generation returned an error status', ['status' => $response->status(), 'error' => $response->json('error.message')]);
            throw new \RuntimeException($response->status() === 429 ? 'The image maker is busy right now. Please try again in a minute — this didn’t use one of your images.' : 'We couldn’t make that image. Try describing it differently — this didn’t use one of your images.');
        }
        $bytes = base64_decode((string) $response->json('data.0.b64_json'), true);
        if ($bytes === false || $bytes === '' || @getimagesizefromstring($bytes) === false) {
            throw new \RuntimeException('We couldn’t make that image. Try describing it differently — this didn’t use one of your images.');
        }

        return $bytes;
    }

    public function generate(AnalysisRun $run, string $prompt, string $detectedRole): ?WebsiteAsset
    {
        $baseUrl = config('meta_api.base_url');
        $apiKey = config('meta_api.api_key');

        if (empty($baseUrl) || empty($apiKey)) {
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout((int) config('meta_api.timeout_seconds', 90))
                ->post(rtrim($baseUrl, '/').'/images/generations', [
                    'model' => self::MODEL,
                    'prompt' => $prompt,
                ]);
        } catch (Throwable $e) {
            Log::info('Muse Image generation failed', ['run' => $run->public_id, 'error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::info('Muse Image generation returned an error status', ['run' => $run->public_id, 'status' => $response->status()]);

            return null;
        }

        $b64 = $response->json('data.0.b64_json');
        if (! is_string($b64) || $b64 === '') {
            return null;
        }

        $bytes = base64_decode($b64, true);
        if ($bytes === false || strlen($bytes) === 0) {
            return null;
        }

        $outputFormat = (string) ($response->json('output_format') ?? 'webp');
        $mime = "image/{$outputFormat}";
        $allowed = config('assets.allowed_mime_types', []);
        if (! isset($allowed[$mime])) {
            return null;
        }

        $imageInfo = @getimagesizefromstring($bytes);
        if ($imageInfo === false) {
            return null;
        }

        $assetKey = 'aigen'.substr(md5($prompt.$detectedRole), 0, 9);
        $extension = $allowed[$mime];
        $storedPath = "assets/{$run->public_id}/{$assetKey}.{$extension}";
        Storage::disk(config('assets.storage_disk', 'public'))->put($storedPath, $bytes);

        return WebsiteAsset::updateOrCreate(
            ['analysis_run_id' => $run->id, 'asset_key' => $assetKey],
            [
                'source_page_url' => $run->normalized_url,
                'original_url' => 'ai-generated://muse-image-1.0',
                'stored_path' => $storedPath,
                'asset_type' => 'illustration',
                'asset_origin' => 'ai_generated',
                'attribution' => 'AI-generated illustration (Meta Muse Image)',
                'mime_type' => $mime,
                'file_size' => strlen($bytes),
                'width' => $imageInfo[0],
                'height' => $imageInfo[1],
                'detected_role' => $detectedRole,
                'relevance_score' => 0.4,
                'preservation_priority' => 'low',
                'recommended_usage' => 'AI-generated fallback illustration — no suitable original was found',
                'recommended_action' => 'reuse',
                'rights_status' => 'ai_generated',
                'created_at' => now(),
            ]
        );
    }
}
