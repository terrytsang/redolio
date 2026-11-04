<?php

namespace App\Services\MetaApi;

use App\DTO\MetaApi\MetaStageResponse;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\Exceptions\MetaApiNotConfiguredException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Server-side Meta Model API adapter. See docs/meta-api-verified.md —
 * the exact request/response contract here is a placeholder until the
 * official API is verified; it is isolated entirely within this class so
 * fixing it later never touches domain/validation code (architecture_rule).
 */
class HttpMetaModelClient extends AbstractModelClient
{
    protected function callStage(string $stage, array $input): MetaStageResponse
    {
        $baseUrl = config('meta_api.base_url');
        $apiKey = config('meta_api.api_key');
        $modelId = config('meta_api.model_id');

        if (empty($baseUrl) || empty($apiKey) || empty($modelId)) {
            throw new MetaApiNotConfiguredException;
        }

        $maxAttempts = (int) config('meta_api.max_attempts', 3);
        $backoff = config('meta_api.backoff_seconds', [1, 2, 4]);
        $retryableStatuses = config('meta_api.retryable_statuses', [429, 500, 502, 503, 504]);
        $timeout = (int) config('meta_api.timeout_seconds', 90);

        $schema = StageSchemas::for($stage, $input);
        $instruction = self::STAGE_INSTRUCTIONS[$stage] ?? '';

        $payload = [
            'model' => $modelId,
            'input' => $instruction."\n\nContext (JSON):\n".json_encode($input, JSON_UNESCAPED_SLASHES),
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $schema['name'],
                    'schema' => $schema['schema'],
                ],
            ],
        ];

        if (in_array($stage, self::STAGES_WITH_WEB_SEARCH, true)) {
            // Live web_search tool (verified via docs/meta-api-verified.md
            // smoke test) — lets the model ground design-trend synthesis in
            // real current pages instead of only its training knowledge.
            $payload['tools'] = [['type' => 'web_search']];
        }

        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $start = microtime(true);

            try {
                $response = Http::baseUrl($baseUrl)
                    ->withHeaders($this->buildHeaders($apiKey))
                    ->timeout($timeout)
                    ->post('/responses', $payload);

                $latencyMs = (int) ((microtime(true) - $start) * 1000);

                if ($response->successful()) {
                    return $this->normalizeResponse($response->json(), $modelId, $latencyMs, $stage);
                }

                if (! in_array($response->status(), $retryableStatuses, true)) {
                    throw new MetaApiException(
                        "Meta API returned non-retryable status {$response->status()} for stage [{$stage}].",
                        'meta_api_http_error'
                    );
                }

                $lastException = new MetaApiException(
                    "Meta API returned retryable status {$response->status()} for stage [{$stage}].",
                    'meta_api_retryable_error'
                );
            } catch (MetaApiException $e) {
                throw $e;
            } catch (Throwable $e) {
                $lastException = new MetaApiException(
                    "Meta API request failed for stage [{$stage}]: {$e->getMessage()}",
                    'meta_api_transport_error',
                    $e
                );
            }

            if ($attempt < $maxAttempts) {
                sleep((int) ($backoff[$attempt - 1] ?? end($backoff)));
            }
        }

        throw $lastException ?? new MetaApiException("Meta API call failed for stage [{$stage}].");
    }

    private function buildHeaders(string $apiKey): array
    {
        // Placeholder auth header — confirm exact scheme in docs/meta-api-verified.md.
        return [
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    private function normalizeResponse(?array $body, string $modelId, int $latencyMs, string $stage): MetaStageResponse
    {
        if ($body === null) {
            throw new MetaApiException("Meta API returned an unparsable response for stage [{$stage}].", 'meta_api_malformed_json');
        }

        $data = $this->extractStructuredData($body);

        if (! is_array($data)) {
            throw new MetaApiException("Meta API response for stage [{$stage}] had no usable structured output.", 'meta_api_malformed_json');
        }

        $usage = $body['usage'] ?? null;

        return new MetaStageResponse(
            data: $data,
            modelIdentifier: $body['model'] ?? $modelId,
            inputTokens: is_array($usage) ? ($usage['input_tokens'] ?? $usage['prompt_tokens'] ?? null) : null,
            outputTokens: is_array($usage) ? ($usage['output_tokens'] ?? $usage['completion_tokens'] ?? null) : null,
            latencyMs: $latencyMs,
        );
    }

    /**
     * The Responses API wraps structured JSON output inside
     * output[].content[].text as a JSON-encoded string (per the
     * confirmed Quickstart response shape). Falls back to treating the
     * body itself as the structured object if no such envelope is
     * present, since this has not been exercised against every possible
     * response variant yet.
     */
    private function extractStructuredData(array $body): ?array
    {
        foreach (($body['output'] ?? []) as $item) {
            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach (($item['content'] ?? []) as $content) {
                if (! isset($content['text']) || ! is_string($content['text'])) {
                    continue;
                }

                $decoded = json_decode($content['text'], true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        if (isset($body['output_text']) && is_string($body['output_text'])) {
            $decoded = json_decode($body['output_text'], true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $body['data'] ?? $body['output'] ?? (array_is_list($body) ? null : $body);
    }
}
