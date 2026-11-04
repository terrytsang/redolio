<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Unsplash photo search in the editor's "Edit picture" (https://unsplash.com/developers). Leave empty to hide the search.
    'unsplash' => ['access_key' => env('UNSPLASH_ACCESS_KEY')],

    // Stripe billing for Starter / Agency (Checkout + webhook). Prices are Stripe price ids (price_...).
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'prices' => [
            'starter' => env('STRIPE_REDOLIO_STARTER_PRICE_ID', env('STRIPE_PRICE_STARTER')),
            'agency' => env('STRIPE_REDOLIO_AGENCY_PRICE_ID', env('STRIPE_PRICE_AGENCY')),
        ],
        // One-time Export Pass (HTML/CSS/JS zip download).
        'export_price' => env('STRIPE_REDOLIO_EXPORT_PRICE_ID', env('STRIPE_PRICE_EXPORT')),
        // Launch offer: when this price id is set, the Export Pass is sold at it (and shown with the regular price struck through).
        'export_offer_price' => env('STRIPE_REDOLIO_EXPORT_OFFER_PRICE_ID'),
    ],

    // "Sign in with Google" (Google Identity Services). Only the Web client ID is needed — no secret and no redirect URI; add the
    // site's origin (e.g. https://redolio.com) under "Authorized JavaScript origins" for that client in Google Cloud Console.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID', env('GOOGLE_WEB_CLIENT_ID')),
    ],
];
