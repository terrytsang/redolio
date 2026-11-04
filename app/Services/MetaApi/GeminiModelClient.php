<?php

namespace App\Services\MetaApi;

use App\DTO\MetaApi\MetaStageResponse;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\Exceptions\MetaApiNotConfiguredException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gemini (Google) provider. Uses the documented generateContent API with
 * `generationConfig.responseMimeType: application/json` and
 * `responseSchema` for structured output — a stable, publicly documented
 * contract, so this implementation is real rather than a stub; it just
 * has no credentials until an operator supplies GEMINI_API_KEY.
 */
class GeminiModelClient extends AbstractModelClient
{
    protected function callStage(string $stage, array $input): MetaStageResponse
    {
        $apiKey = config('ai_providers.providers.gemini.api_key');
        $model = config('ai_providers.providers.gemini.model', 'gemini-2.5-pro');

        if (empty($apiKey)) {
            throw new MetaApiNotConfiguredException('Gemini provider selected but GEMINI_API_KEY is not configured.');
        }

        $timeout = (int) config('meta_api.timeout_seconds', 90);
        $schema = StageSchemas::for($stage, $input);
        $instruction = self::STAGE_INSTRUCTIONS[$stage] ?? '';

        $payload = [
            'system_instruction' => ['parts' => [['text' => $instruction]]],
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => "Context (JSON):\n".json_encode($input, JSON_UNESCAPED_SLASHES)]],
            ]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema['schema'],
            ],
        ];

        $start = microtime(true);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout($timeout)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", $payload);
        } catch (Throwable $e) {
            throw new MetaApiException("Gemini request failed for stage [{$stage}]: {$e->getMessage()}", 'gemini_transport_error', $e);
        }

        $latencyMs = (int) ((microtime(true) - $start) * 1000);

        if (! $response->successful()) {
            throw new MetaApiException("Gemini returned status {$response->status()} for stage [{$stage}].", 'gemini_http_error');
        }

        $body = $response->json();
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $data = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($data)) {
            throw new MetaApiException("Gemini response for stage [{$stage}] had no usable structured output.", 'gemini_malformed_json');
        }

        $usage = $body['usageMetadata'] ?? null;

        return new MetaStageResponse(
            data: $data,
            modelIdentifier: $model,
            inputTokens: is_array($usage) ? ($usage['promptTokenCount'] ?? null) : null,
            outputTokens: is_array($usage) ? ($usage['candidatesTokenCount'] ?? null) : null,
            latencyMs: $latencyMs,
        );
    }

    /**
     * Real, publicly documented generateContent vision input: inlineData
     * parts (base64) alongside text parts in the same content entry —
     * same documented contract as text-only stages.
     */
    protected function callVisionStage(string $stage, array $textInput, array $imagesByLabel): MetaStageResponse
    {
        $apiKey = config('ai_providers.providers.gemini.api_key');
        $model = config('ai_providers.providers.gemini.model', 'gemini-2.5-pro');

        if (empty($apiKey)) {
            throw new MetaApiNotConfiguredException('Gemini provider selected but GEMINI_API_KEY is not configured.');
        }

        $timeout = (int) config('meta_api.timeout_seconds', 90);
        $schema = StageSchemas::for($stage, $textInput);
        $instruction = self::STAGE_INSTRUCTIONS[$stage] ?? '';

        $parts = [];
        foreach ($imagesByLabel as $label => $bytes) {
            $parts[] = ['text' => ucfirst($label).' screenshot:'];
            $parts[] = ['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode($bytes)]];
        }
        $parts[] = ['text' => "Context (JSON):\n".json_encode($textInput, JSON_UNESCAPED_SLASHES)];

        $payload = [
            'system_instruction' => ['parts' => [['text' => $instruction]]],
            'contents' => [[
                'role' => 'user',
                'parts' => $parts,
            ]],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema['schema'],
            ],
        ];

        $start = microtime(true);

        try {
            $response = Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->timeout($timeout)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", $payload);
        } catch (Throwable $e) {
            throw new MetaApiException("Gemini vision request failed for stage [{$stage}]: {$e->getMessage()}", 'gemini_transport_error', $e);
        }

        $latencyMs = (int) ((microtime(true) - $start) * 1000);

        if (! $response->successful()) {
            throw new MetaApiException("Gemini returned status {$response->status()} for stage [{$stage}].", 'gemini_http_error');
        }

        $body = $response->json();
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
        $data = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($data)) {
            throw new MetaApiException("Gemini response for stage [{$stage}] had no usable structured output.", 'gemini_malformed_json');
        }

        $usage = $body['usageMetadata'] ?? null;

        return new MetaStageResponse(
            data: $data,
            modelIdentifier: $model,
            inputTokens: is_array($usage) ? ($usage['promptTokenCount'] ?? null) : null,
            outputTokens: is_array($usage) ? ($usage['candidatesTokenCount'] ?? null) : null,
            latencyMs: $latencyMs,
        );
    }
}
