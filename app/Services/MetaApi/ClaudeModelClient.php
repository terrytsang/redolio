<?php

namespace App\Services\MetaApi;

use App\DTO\MetaApi\MetaStageResponse;
use App\Services\MetaApi\Exceptions\MetaApiException;
use App\Services\MetaApi\Exceptions\MetaApiNotConfiguredException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Claude (Anthropic) provider. The Messages API has no native
 * "respond as this JSON schema" mode, so structured output is obtained
 * the standard documented way: a single tool definition shaped like our
 * schema, with `tool_choice` forcing that exact tool — the model's
 * `tool_use` block input is the structured object. This is a stable,
 * publicly documented pattern, so this implementation is real rather
 * than a stub; it just has no credentials until an operator supplies
 * CLAUDE_API_KEY.
 */
class ClaudeModelClient extends AbstractModelClient
{
    protected function callStage(string $stage, array $input): MetaStageResponse
    {
        $apiKey = config('ai_providers.providers.claude.api_key');
        $model = config('ai_providers.providers.claude.model', 'claude-sonnet-5');

        if (empty($apiKey)) {
            throw new MetaApiNotConfiguredException('Claude provider selected but CLAUDE_API_KEY is not configured.');
        }

        $timeout = (int) config('meta_api.timeout_seconds', 90);
        $schema = StageSchemas::for($stage, $input);
        $instruction = self::STAGE_INSTRUCTIONS[$stage] ?? '';
        $toolName = $schema['name'];

        $payload = [
            'model' => $model,
            'max_tokens' => 4096,
            'system' => $instruction,
            'messages' => [
                ['role' => 'user', 'content' => "Context (JSON):\n".json_encode($input, JSON_UNESCAPED_SLASHES)],
            ],
            'tools' => [[
                'name' => $toolName,
                'description' => "Return the structured {$toolName} result.",
                'input_schema' => $schema['schema'],
            ]],
            'tool_choice' => ['type' => 'tool', 'name' => $toolName],
        ];

        $start = microtime(true);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout($timeout)->post('https://api.anthropic.com/v1/messages', $payload);
        } catch (Throwable $e) {
            throw new MetaApiException("Claude request failed for stage [{$stage}]: {$e->getMessage()}", 'claude_transport_error', $e);
        }

        $latencyMs = (int) ((microtime(true) - $start) * 1000);

        if (! $response->successful()) {
            throw new MetaApiException("Claude returned status {$response->status()} for stage [{$stage}].", 'claude_http_error');
        }

        $body = $response->json();
        $data = null;
        foreach (($body['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'tool_use' && is_array($block['input'] ?? null)) {
                $data = $block['input'];
                break;
            }
        }

        if (! is_array($data)) {
            throw new MetaApiException("Claude response for stage [{$stage}] had no usable structured output.", 'claude_malformed_json');
        }

        $usage = $body['usage'] ?? null;

        return new MetaStageResponse(
            data: $data,
            modelIdentifier: $body['model'] ?? $model,
            inputTokens: is_array($usage) ? ($usage['input_tokens'] ?? null) : null,
            outputTokens: is_array($usage) ? ($usage['output_tokens'] ?? null) : null,
            latencyMs: $latencyMs,
        );
    }

    /**
     * Real, publicly documented Claude vision input: image content blocks
     * (base64) interleaved with text in a single user message — the same
     * stable contract as the text-only stages, just with image blocks
     * added before the JSON context.
     */
    protected function callVisionStage(string $stage, array $textInput, array $imagesByLabel): MetaStageResponse
    {
        $apiKey = config('ai_providers.providers.claude.api_key');
        $model = config('ai_providers.providers.claude.model', 'claude-sonnet-5');

        if (empty($apiKey)) {
            throw new MetaApiNotConfiguredException('Claude provider selected but CLAUDE_API_KEY is not configured.');
        }

        $timeout = (int) config('meta_api.timeout_seconds', 90);
        $schema = StageSchemas::for($stage, $textInput);
        $instruction = self::STAGE_INSTRUCTIONS[$stage] ?? '';
        $toolName = $schema['name'];

        $content = [];
        foreach ($imagesByLabel as $label => $bytes) {
            $content[] = ['type' => 'text', 'text' => ucfirst($label).' screenshot:'];
            $content[] = ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/png', 'data' => base64_encode($bytes)]];
        }
        $content[] = ['type' => 'text', 'text' => "Context (JSON):\n".json_encode($textInput, JSON_UNESCAPED_SLASHES)];

        $payload = [
            'model' => $model,
            'max_tokens' => 4096,
            'system' => $instruction,
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
            'tools' => [[
                'name' => $toolName,
                'description' => "Return the structured {$toolName} result.",
                'input_schema' => $schema['schema'],
            ]],
            'tool_choice' => ['type' => 'tool', 'name' => $toolName],
        ];

        $start = microtime(true);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout($timeout)->post('https://api.anthropic.com/v1/messages', $payload);
        } catch (Throwable $e) {
            throw new MetaApiException("Claude vision request failed for stage [{$stage}]: {$e->getMessage()}", 'claude_transport_error', $e);
        }

        $latencyMs = (int) ((microtime(true) - $start) * 1000);

        if (! $response->successful()) {
            throw new MetaApiException("Claude returned status {$response->status()} for stage [{$stage}].", 'claude_http_error');
        }

        $body = $response->json();
        $data = null;
        foreach (($body['content'] ?? []) as $block) {
            if (($block['type'] ?? null) === 'tool_use' && is_array($block['input'] ?? null)) {
                $data = $block['input'];
                break;
            }
        }

        if (! is_array($data)) {
            throw new MetaApiException("Claude response for stage [{$stage}] had no usable structured output.", 'claude_malformed_json');
        }

        $usage = $body['usage'] ?? null;

        return new MetaStageResponse(
            data: $data,
            modelIdentifier: $body['model'] ?? $model,
            inputTokens: is_array($usage) ? ($usage['input_tokens'] ?? null) : null,
            outputTokens: is_array($usage) ? ($usage['output_tokens'] ?? null) : null,
            latencyMs: $latencyMs,
        );
    }
}
