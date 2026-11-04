<?php

return [
    // Fallback imagery source (PexelsStockPhotoService) used only when a
    // run's own crawled assets have no suitable hero/section image — real,
    // documented Pexels API v1 (https://www.pexels.com/api/documentation/),
    // free tier, no credentials shipped; an operator supplies their own key
    // via .env or the admin Settings page (same BYOK pattern as the AI
    // provider keys) and the feature is simply inactive until they do.
    'pexels' => [
        'api_key' => env('PEXELS_API_KEY'),
    ],
];
