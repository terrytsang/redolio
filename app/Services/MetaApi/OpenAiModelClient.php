<?php

namespace App\Services\MetaApi;

use App\DTO\MetaApi\MetaStageResponse;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\Exceptions\MetaApiNotConfiguredException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * OpenAI (ChatGPT) provider. Uses the real, publicly documented Chat
 * Completions API with `response_format: {type: json_schema}` for
 * structured output — unlike Meta Muse Spark, this is a stable, verified
 * public contract, so this implementation is real rather than a stub.
 * It simply has no credentials configured until an operator supplies
 * OPENAI_API_KEY, at which point it works without further code changes.
 */
class OpenAiModelClient extends AbstractModelClient
{
    protected function callStage(string $stage, array $input): MetaStageResponse
    {
        $apiKey = config('ai_providers.providers.openai.api_key');
        $model = config('ai_providers.providers.openai.model', 'gpt-4.1');

        if (empty($apiKey)) {
            throw new MetaApiNotConfiguredException('OpenAI provider selected but OPENAI_API_KEY is not configured.');
        }

        $timeout = (int) config('meta_api.timeout_seconds', 90);
        $schema = StageSchemas::for($stage, $input);
        $instruction = self::STAGE_INSTRUCTIONS[$stage] ?? '';

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $instruction],
                ['role' => 'user', 'content' => "Context (JSON):\n".json_encode($input, JSON_UNESCAPED_SLASHES)],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $schema['name'],
                    'schema' => $schema['schema'],
                    'strict' => true,
                ],
            ],
        ];

        $start = microtime(true);

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post('https://api.openai.com/v1/chat/completions', $payload);
        } catch (Throwable $e) {
            throw new MetaApiException("OpenAI request failed for stage [{$stage}]: {$e->getMessage()}", 'openai_transport_error', $e);
        }

        $latencyMs = (int) ((microtime(true) - $start) * 1000);

        if (! $response->successful()) {
            throw new MetaApiException("OpenAI returned status {$response->status()} for stage [{$stage}].", 'openai_http_error');
        }

        $body = $response->json();
        $content = $body['choices'][0]['message']['content'] ?? null;
        $data = is_string($content) ? json_decode($content, true) : null;

        if (! is_array($data)) {
            throw new MetaApiException("OpenAI response for stage [{$stage}] had no usable structured output.", 'openai_malformed_json');
        }

        $usage = $body['usage'] ?? null;

        return new MetaStageResponse(
            data: $data,
            modelIdentifier: $body['model'] ?? $model,
            inputTokens: is_array($usage) ? ($usage['prompt_tokens'] ?? null) : null,
            outputTokens: is_array($usage) ? ($usage['completion_tokens'] ?? null) : null,
            latencyMs: $latencyMs,
        );
    }

    /**
     * Real, publicly documented Chat Completions vision input: image_url
     * content parts with a base64 data: URI, alongside text parts in the
     * same user message — same documented contract as text-only stages.
     */
    protected function callVisionStage(string $stage, array $textInput, array $imagesByLabel): MetaStageResponse
    {
        $apiKey = config('ai_providers.providers.openai.api_key');
        $model = config('ai_providers.providers.openai.model', 'gpt-4.1');

        if (empty($apiKey)) {
            throw new MetaApiNotConfiguredException('OpenAI provider selected but OPENAI_API_KEY is not configured.');
        }

        $timeout = (int) config('meta_api.timeout_seconds', 90);
        $schema = StageSchemas::for($stage, $textInput);
        $instruction = self::STAGE_INSTRUCTIONS[$stage] ?? '';

        $content = [];
        foreach ($imagesByLabel as $label => $bytes) {
            $content[] = ['type' => 'text', 'text' => ucfirst($label).' screenshot:'];
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,'.base64_encode($bytes)]];
        }
        $content[] = ['type' => 'text', 'text' => "Context (JSON):\n".json_encode($textInput, JSON_UNESCAPED_SLASHES)];

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $instruction],
                ['role' => 'user', 'content' => $content],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => $schema['name'],
                    'schema' => $schema['schema'],
                    'strict' => true,
                ],
            ],
        ];

        $start = microtime(true);

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post('https://api.openai.com/v1/chat/completions', $payload);
        } catch (Throwable $e) {
            throw new MetaApiException("OpenAI vision request failed for stage [{$stage}]: {$e->getMessage()}", 'openai_transport_error', $e);
        }

        $latencyMs = (int) ((microtime(true) - $start) * 1000);

        if (! $response->successful()) {
            throw new MetaApiException("OpenAI returned status {$response->status()} for stage [{$stage}].", 'openai_http_error');
        }

        $body = $response->json();
        $responseContent = $body['choices'][0]['message']['content'] ?? null;
        $data = is_string($responseContent) ? json_decode($responseContent, true) : null;

        if (! is_array($data)) {
            throw new MetaApiException("OpenAI response for stage [{$stage}] had no usable structured output.", 'openai_malformed_json');
        }

        $usage = $body['usage'] ?? null;

        return new MetaStageResponse(
            data: $data,
            modelIdentifier: $body['model'] ?? $model,
            inputTokens: is_array($usage) ? ($usage['prompt_tokens'] ?? null) : null,
            outputTokens: is_array($usage) ? ($usage['completion_tokens'] ?? null) : null,
            latencyMs: $latencyMs,
        );
    }
}
