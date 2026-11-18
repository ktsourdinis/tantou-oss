<?php

$communityConfig = [
    /*
    |--------------------------------------------------------------------------
    | Tantou Edition
    |--------------------------------------------------------------------------
    |
    | Tantou Community is the open-source, self-hosted Human Capacity OS.
    |
    */
    'edition' => 'community',

    /*
    |--------------------------------------------------------------------------
    | Routing Mode
    |--------------------------------------------------------------------------
    |
    | Single-tenant routing for self-hosted intranet and private deployments.
    |
    */
    'routing_mode' => 'single',

    /*
    |--------------------------------------------------------------------------
    | Base Domain
    |--------------------------------------------------------------------------
    */
    'base_domain' => 'localhost',

    /*
    |--------------------------------------------------------------------------
    | Marketing Landing Page
    |--------------------------------------------------------------------------
    |
    | Self-hosted installations direct users straight to workspace access.
    |
    */
    'hide_marketing' => true,
    'custom_domains_enabled' => false,

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Integration
    |--------------------------------------------------------------------------
    |
    | Global bot credentials and operating mode ('auto', 'webhook', 'polling').
    |
    */
    'telegram' => [
        'bot_token' => env('TANTOU_TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TANTOU_TELEGRAM_BOT_USERNAME'),
        'mode' => env('TANTOU_TELEGRAM_MODE', 'auto'),
        'webhook_secret' => env('TANTOU_TELEGRAM_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Billing Configuration
    |--------------------------------------------------------------------------
    |
    | Community self-hosted instances do not require commercial billing.
    |
    */
    'stripe' => [
        'key' => null,
        'secret' => null,
        'webhook_secret' => null,
        'prices' => [
            'team' => [
                'monthly' => null,
                'yearly' => null,
            ],
            'business' => [
                'monthly' => null,
                'yearly' => null,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Community Plan & Feature Matrix
    |--------------------------------------------------------------------------
    |
    | Self-hosted workspaces run with completely unlocked capacity quotas.
    |
    */
    'plans' => [
        'community' => [
            'name' => 'Community Self-Hosted',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'max_workers' => null,
            'max_departments' => null,
            'features' => [
                'public_dropbox' => true,
                'analytics' => true,
                'strict_b2b_isolation' => true,
                'custom_telegram_bot' => true,
                'gdpr_privacy_mode' => true,
                'custom_domain' => false,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform Superadmin Configuration
    |--------------------------------------------------------------------------
    */
    'superadmin_emails' => '',
];

if (is_file(__DIR__.'/tantou-cloud.php')) {
    return array_replace_recursive($communityConfig, require __DIR__.'/tantou-cloud.php');
}

return $communityConfig;
