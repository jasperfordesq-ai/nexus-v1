<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Nexus\Core\Env;

/**
 * Pusher Channels Configuration
 *
 * Environment variables required:
 * - PUSHER_APP_ID: Your Pusher app ID
 * - PUSHER_KEY: Your Pusher app key (public)
 * - PUSHER_SECRET: Your Pusher app secret
 * - PUSHER_CLUSTER: Pusher cluster (e.g., us2, eu, ap1)
 */

return [
    'app_id'  => Env::get('PUSHER_APP_ID', ''),
    'key'     => Env::get('PUSHER_KEY', ''),
    'secret'  => Env::get('PUSHER_SECRET', ''),
    'cluster' => Env::get('PUSHER_CLUSTER', 'us2'),
    'useTLS'  => true,
    'debug'   => Env::get('PUSHER_DEBUG', false),
];
