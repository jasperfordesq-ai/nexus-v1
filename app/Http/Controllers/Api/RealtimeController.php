<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * RealtimeController -- Public realtime/WebSocket configuration endpoint.
 */
class RealtimeController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /** GET /api/v2/realtime/config */
    public function config(): JsonResponse
    {
        $pusherKey = config('broadcasting.connections.pusher.key', '');
        $pusherCluster = config('broadcasting.connections.pusher.options.cluster', 'eu');
        $wsHost = config('broadcasting.connections.pusher.options.host', '');
        $wsPort = (int) config('broadcasting.connections.pusher.options.port', 443);

        return $this->respondWithData([
            'driver' => 'pusher',
            'key' => $pusherKey,
            'cluster' => $pusherCluster,
            'ws_host' => $wsHost,
            'ws_port' => $wsPort,
            'force_tls' => true,
        ]);
    }
}
