<?php

return [
    // The Meta Model API contract is verified in docs/meta-api-verified.md. The base URL and model default to the verified values;
    // the API KEY is never in the source code: set META_API_KEY in .env, or (easier) paste it in Admin -> Settings -> Meta Model API.
    // Without a key the app runs in demo/fixture mode.
    'base_url' => env('META_API_BASE_URL') ?: 'https://api.meta.ai/v1',
    'api_key' => env('META_API_KEY'),
    'model_id' => env('META_API_MODEL_ID') ?: 'muse-spark-1.3',
    'timeout_seconds' => (int) env('META_API_TIMEOUT_SECONDS', 90),
    'max_attempts' => (int) env('META_API_MAX_ATTEMPTS', 3),
    'backoff_seconds' => [1, 2, 4],
    'retryable_statuses' => [429, 500, 502, 503, 504],
    'json_repair_attempts' => 1,
    'prompt_version' => 'v1',
];
