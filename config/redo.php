<?php

return [
    // 'saas' = redolio.com: multi-tenant, plans/billing UI, lead-gen tools,
    // admin seeded from ADMIN_EMAIL/ADMIN_PASSWORD.
    // 'selfhosted' = redoweb.org (MIT edition): single website, BYOK Meta
    // API key, no plans/billing, first-run setup wizard instead of .env
    // admin seeding.
    'edition' => env('REDO_EDITION', 'saas'),

    // Reading a customer's website with a real browser (content behind JavaScript or a bot challenge,
    // and the genuine "before" screenshot). The browser's traffic is forced through a policy proxy that
    // refuses internal addresses, but it still opens user-chosen URLs on THIS machine: on by default only
    // in the local environment; elsewhere enable it only on an isolated worker.
    'source_capture' => [
        'enabled' => (bool) env('REDO_SOURCE_CAPTURE', env('APP_ENV', 'production') === 'local'),
        'max_pages' => (int) env('REDO_SOURCE_CAPTURE_PAGES', 4),
        'max_seconds' => (int) env('REDO_SOURCE_CAPTURE_SECONDS', 75),
    ],

    // Scan capacity. A scan is a multi-minute background job, so under load the right primitive is the job queue
    // (with a live position + estimate and an opt-in "email me" fallback), not a browser-held waiting room.
    'capacity' => [
        'workers' => (int) env('REDO_SCAN_WORKERS', 2),          // queue workers that run scans concurrently
        'busy_threshold' => (int) env('REDO_BUSY_THRESHOLD', 4),  // scans in flight at/above which we call it "busy"
    ],

    // Visual QA. 'local' runs Playwright on this machine (dev / a worker host);
    // 'browserless' and 'browserbase' use a remote browser (required where browser binaries cannot run).
    'browser' => [
        'provider' => env('REDO_BROWSER_PROVIDER', 'local'),
        // Backup provider used automatically when the main one fails (e.g. Browserbase's free quota is finished). Empty = none.
        'fallback' => env('REDO_BROWSER_FALLBACK', ''),
        'browserless_url' => env('BROWSERLESS_URL', 'https://production-sfo.browserless.io'),
        'browserless_token' => env('BROWSERLESS_TOKEN', env('BROWSERLESS_API_KEY')),
        // 'browserbase' = Browserbase cloud browser (project id optional: the key's only project is used).
        'browserbase_api_key' => env('BROWSERBASE_API_KEY'),
        'browserbase_project_id' => env('BROWSERBASE_PROJECT_ID'),
        // Full path to Node.js when the web/queue user's PATH doesn't include it (e.g. /opt/nodejs22/bin/node on Opalstack).
        'node_binary' => env('REDO_NODE_BINARY', 'node'),
        'timeout_seconds' => (int) env('REDO_BROWSER_TIMEOUT', 60),
    ],

    // Cost and retention limits (documented in docs/v1.5-runbook.md).
    'qa_max_reports_per_run_per_day' => (int) env('REDO_QA_MAX_REPORTS_PER_DAY', 20),
    'retention' => [
        'qa_screenshot_days' => (int) env('REDO_RETAIN_QA_SCREENSHOT_DAYS', 30),
        'temp_hours' => (int) env('REDO_RETAIN_TEMP_HOURS', 24),
        'export_days_after_expiry' => (int) env('REDO_RETAIN_EXPORT_DAYS_AFTER_EXPIRY', 7),
    ],

    // Automatic, deterministic QA refinement passes per spec lineage (PRD: initially one or two).
    'qa_max_refinements' => (int) env('REDO_QA_MAX_REFINEMENTS', 2),

    // v1.5 catalogue directions are offered to users, and the legacy directions
    // they supersede are retired from selection (existing runs keep working).
    // Set false to roll back to the original six.
    'activate_v15_directions' => env('REDO_ACTIVATE_V15_DIRECTIONS', true),

    // Default admin portal account, seeded by database/seeders/AdminUserSeeder.
    // Only used in 'saas' edition — 'selfhosted' creates its admin via the
    // in-browser setup wizard instead.
    'admin_email' => env('ADMIN_EMAIL'),
    'admin_password' => env('ADMIN_PASSWORD'),

    // Export Pass (one-time HTML/CSS/JS download), in cents. The offer price applies while STRIPE_REDOLIO_EXPORT_OFFER_PRICE_ID is set.
    'export_pass' => [
        'list_cents' => (int) env('REDO_EXPORT_LIST_CENTS', 2500),
        'offer_cents' => (int) env('REDO_EXPORT_OFFER_CENTS', 1000),
        // What one pass includes for its website on the Free plan: downloads (the first plus two more) and saved edits after buying.
        'exports_included' => (int) env('REDO_EXPORT_PASS_DOWNLOADS', 3),
        'edits_included' => (int) env('REDO_EXPORT_PASS_EDITS', 2),
    ],

    // Where "email us" links in the product point (404 page, export instructions, error messages). Set it to your own address.
    'support_email' => env('REDO_SUPPORT_EMAIL', 'hello@example.com'),

    // Visitor analytics: look up each visitor's country and city (ipwho.is, cached a week, no IP stored).
    'geoip_enabled' => (bool) env('GEOIP_ENABLED', true),
];
