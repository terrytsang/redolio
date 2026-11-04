<?php

use App\Services\MetaApi\ClaudeModelClient;
use App\Services\MetaApi\GeminiModelClient;
use App\Services\MetaApi\HttpMetaModelClient;
use App\Services\MetaApi\OpenAiModelClient;

return [
    // Which provider actually serves MetaModelClient calls. Overridable
    // per-installation from Admin -> Settings (stored in platform_settings),
    // without touching .env.
    'active' => env('AI_PROVIDER', 'meta'),

    'providers' => [
        'meta' => [
            'label' => 'Meta Model API',
            'client' => HttpMetaModelClient::class,
        ],
        'claude' => [
            'label' => 'Claude (Anthropic)',
            'client' => ClaudeModelClient::class,
            'api_key' => env('CLAUDE_API_KEY'),
            'model' => env('CLAUDE_MODEL', 'claude-sonnet-5'),
        ],
        'openai' => [
            'label' => 'ChatGPT (OpenAI)',
            'client' => OpenAiModelClient::class,
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4.1'),
        ],
        'gemini' => [
            'label' => 'Gemini (Google)',
            'client' => GeminiModelClient::class,
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.5-pro'),
        ],
    ],
];
