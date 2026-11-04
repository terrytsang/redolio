<?php

return [
    'max_pages' => (int) env('CRAWLER_MAX_PAGES', 8),
    'max_depth' => (int) env('CRAWLER_MAX_DEPTH', 2),
    'connect_timeout_seconds' => 5,
    'page_timeout_seconds' => (int) env('CRAWLER_PAGE_TIMEOUT_SECONDS', 15),
    'max_redirects' => 5,
    'max_response_bytes' => (int) env('CRAWLER_MAX_RESPONSE_BYTES', 2_000_000),
    'max_total_text_characters' => (int) env('CRAWLER_MAX_TEXT_CHARS', 50_000),
    'max_concurrent_requests' => 2,
    'user_agent' => env('CRAWLER_USER_AGENT', 'REDOLIO Website Analysis Bot (+https://redolio.com/bot-policy)'),
];
