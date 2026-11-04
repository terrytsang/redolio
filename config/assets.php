<?php

return [
    // Phase A (website_intelligence_engine / asset_preservation_intelligence):
    // bounds how many images the asset inventory will download and store per
    // analysis run, and what it's willing to accept as a real image.
    'max_assets_per_run' => (int) env('ASSETS_MAX_PER_RUN', 15),
    'max_bytes_per_asset' => (int) env('ASSETS_MAX_BYTES', 5_000_000),
    'allowed_mime_types' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
    ],
    'storage_disk' => 'public',
];
