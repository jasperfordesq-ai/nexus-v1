<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | SMTP is the default mailer. The platform also supports Gmail API via
    | OAuth2 (set USE_GMAIL_API=true) with automatic SMTP fallback when the
    | Gmail API circuit breaker trips (3 consecutive failures = 5 min pause).
    |
    | Gmail API integration is handled by the legacy GmailApiService and is
    | not a native Laravel mail transport. See src/Services/GmailApiService.php.
    |
    */

    'default' => env('MAIL_MAILER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | Platform Email Provider (custom App\Core\Mailer path)
    |--------------------------------------------------------------------------
    |
    | Selects which provider the platform Mailer uses for tenants that have no
    | per-tenant email override. 'sendgrid' (default) preserves existing
    | behaviour; set to 'postmark' (with POSTMARK_SERVER_TOKEN configured) to
    | route platform mail through Postmark's message streams instead. SendGrid
    | remains wired as an automatic fallback when Postmark is the active driver.
    |
    */

    'platform_provider' => env('MAIL_PLATFORM_PROVIDER', 'sendgrid'),

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'host' => env('SMTP_HOST', env('MAIL_HOST', 'smtp.gmail.com')),
            'port' => env('SMTP_PORT', env('MAIL_PORT', 587)),
            'encryption' => env('SMTP_ENCRYPTION', env('MAIL_ENCRYPTION', 'tls')),
            'username' => env('SMTP_USER', env('MAIL_USERNAME')),
            'password' => env('SMTP_PASS', env('MAIL_PASSWORD')),
            // Hard cap SMTP connection/IO so a stalled relay cannot hang the
            // scheduler's newsletter loop indefinitely (has happened before).
            'timeout' => (int) env('SMTP_TIMEOUT', 30),
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    */

    'from' => [
        'address' => env('SMTP_FROM_EMAIL', env('MAIL_FROM_ADDRESS', 'noreply@project-nexus.ie')),
        'name' => env('SMTP_FROM_NAME', env('MAIL_FROM_NAME', 'Project NEXUS')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gmail API Configuration (Non-Laravel Transport)
    |--------------------------------------------------------------------------
    |
    | When USE_GMAIL_API=true, the platform's GmailApiService handles mail
    | delivery via Google's Gmail API with OAuth2 tokens. This bypasses
    | Laravel's mail system entirely. Token refresh is cached in Redis
    | (~1 refresh/hour) with a rate limit of 10 refreshes/hour.
    |
    */

    'gmail_api' => [
        'enabled' => (bool) env('USE_GMAIL_API', false),
        'client_id' => env('GMAIL_CLIENT_ID'),
        'client_secret' => env('GMAIL_CLIENT_SECRET'),
        'refresh_token' => env('GMAIL_REFRESH_TOKEN'),
        'sender_email' => env('GMAIL_SENDER_EMAIL'),
        'sender_name' => env('GMAIL_SENDER_NAME', 'Project NEXUS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | SendGrid Configuration
    |--------------------------------------------------------------------------
    |
    | Platform-wide SendGrid credentials. Per-tenant overrides live in the
    | email_settings table and are loaded by App\Core\Mailer.
    |
    */

    'sendgrid' => [
        'api_key' => env('SENDGRID_API_KEY'),
        'from_email' => env('SENDGRID_FROM_EMAIL'),
        'from_name' => env('SENDGRID_FROM_NAME'),
        'reply_to' => env('SENDGRID_REPLY_TO'),
        // Event Webhook authentication is read by SendGridWebhookController
        // directly from env('SENDGRID_WEBHOOK_VERIFICATION_KEY') — kept here
        // as a reference for ops without proxying through config().
    ],

    /*
    |--------------------------------------------------------------------------
    | Postmark Configuration (custom App\Core\Mailer path)
    |--------------------------------------------------------------------------
    |
    | Platform-wide Postmark credentials, used when mail.platform_provider is
    | 'postmark'. Sending is via the Postmark Email API (raw HTTP, no SDK
    | dependency). Postmark separates transactional and bulk mail into distinct
    | message streams with independent IP reputation, so the Mailer routes
    | newsletter/digest categories to the broadcast stream and everything else
    | to the transactional stream. From addresses use the same verified
    | project-nexus.net domain as SendGrid (category prefixes), so no per-address
    | sender signatures are required once the domain is verified in Postmark.
    |
    */

    'postmark' => [
        'server_token'         => env('POSTMARK_SERVER_TOKEN'),
        'from_email'           => env('POSTMARK_FROM_EMAIL'),
        'from_name'            => env('POSTMARK_FROM_NAME'),
        'reply_to'             => env('POSTMARK_REPLY_TO'),
        'from_domain'          => env('POSTMARK_FROM_DOMAIN', 'project-nexus.net'),
        'stream_transactional' => env('POSTMARK_STREAM_TRANSACTIONAL', 'outbound'),
        'stream_broadcast'     => env('POSTMARK_STREAM_BROADCAST', 'broadcast'),
        // Shared secret authenticating Postmark's event webhook
        // (Delivery/Bounce/SpamComplaint/Open), verified by PostmarkWebhookController.
        'webhook_secret'       => env('POSTMARK_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Error & Admin Alerts
    |--------------------------------------------------------------------------
    */

    'admin_email' => env('ADMIN_EMAIL', 'admin@project-nexus.ie'),
    'error_alert_email' => env('ERROR_ALERT_EMAIL', 'admin@project-nexus.ie'),
    'error_alert_from' => env('ERROR_ALERT_FROM', 'errors@project-nexus.ie'),

];
