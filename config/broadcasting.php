<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | Project NEXUS uses Pusher for real-time WebSocket broadcasting.
    | The React frontend connects via Pusher JS client.
    |
    */

    // Default to a no-op broadcaster when no Pusher key is configured. The
    // installed BroadcastManager has no channel() method, so registering a
    // channel in routes/channels.php eagerly resolves THIS default driver at
    // boot — and constructing the Pusher client with a null key throws a
    // TypeError that 500s every page. Falling back to 'null' keeps the app
    // bootable when broadcasting is unconfigured (dev / missing env / stale
    // config cache); production sets the key, so it still uses 'pusher'.
    'default' => env('BROADCAST_CONNECTION', env('PUSHER_KEY', env('PUSHER_APP_KEY')) ? 'pusher' : 'null'),

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_KEY', env('PUSHER_APP_KEY')),
            'secret' => env('PUSHER_SECRET', env('PUSHER_APP_SECRET')),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => array_filter([
                'cluster' => env('PUSHER_CLUSTER', env('PUSHER_APP_CLUSTER', 'eu')),
                'host' => env('PUSHER_HOST'),
                'port' => env('PUSHER_HOST') ? (int) env('PUSHER_PORT', 443) : null,
                'scheme' => env('PUSHER_HOST') ? env('PUSHER_SCHEME', 'https') : null,
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ], fn ($v) => $v !== null),
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
