<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'pusher' => [
        'app_id' => env('PUSHER_APP_ID'),
        'key' => env('PUSHER_KEY', env('PUSHER_APP_KEY')),
        'secret' => env('PUSHER_SECRET', env('PUSHER_APP_SECRET')),
        'cluster' => env('PUSHER_CLUSTER', env('PUSHER_APP_CLUSTER', 'eu')),
        'debug' => (bool) env('PUSHER_DEBUG', false),
    ],

    'sentry' => [
        'dsn' => env('SENTRY_DSN_PHP', env('SENTRY_LARAVEL_DSN')),
        'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),
        'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-3-sonnet-20240229'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_APP_ID'),
        'client_secret' => env('FACEBOOK_APP_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI'),
    ],

    'mailchimp' => [
        'api_key' => env('MAILCHIMP_API_KEY'),
        'list_id' => env('MAILCHIMP_LIST_ID'),
    ],

    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@project-nexus.ie'),
    ],

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'publishable' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'default_currency' => env('STRIPE_DEFAULT_CURRENCY', 'eur'),
    ],

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH', base_path('firebase-service-account.json')),
    ],

];
