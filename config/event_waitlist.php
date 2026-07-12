<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

$previousKeys = json_decode(
    (string) env('EVENTS_WAITLIST_ENVELOPE_PREVIOUS_KEYS', '{}'),
    true,
);

return [
    'envelope' => [
        'cipher_version' => 'aes-256-gcm-v1',
        'active_key_version' => env(
            'EVENTS_WAITLIST_ENVELOPE_KEY_VERSION',
            'app-key-v1',
        ),
        'active_key' => env('EVENTS_WAITLIST_ENVELOPE_KEY'),
        'previous_keys' => is_array($previousKeys) ? $previousKeys : [],
        'fallback_to_app_key' => env(
            'EVENTS_WAITLIST_ENVELOPE_FALLBACK_APP_KEY',
            true,
        ),
    ],
];
