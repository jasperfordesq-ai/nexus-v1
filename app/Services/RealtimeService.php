<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * RealtimeService — Laravel DI-based service for realtime/WebSocket operations.
 *
 * Provides Pusher broadcasting configuration and channel management.
 * Self-contained — no legacy delegation.
 */
class RealtimeService
{
    /**
     * Get the realtime/Pusher configuration (safe for frontend).
     */
    public function getConfig(): array
    {
        return [
            'driver'    => config('broadcasting.default', 'pusher'),
            'key'       => config('broadcasting.connections.pusher.key', ''),
            'cluster'   => config('broadcasting.connections.pusher.options.cluster', 'eu'),
            'encrypted' => true,
        ];
    }

    /**
     * Get Pusher configuration for frontend initialization.
     */
    public function getFrontendConfig(): array
    {
        return [
            'key'          => config('broadcasting.connections.pusher.key', ''),
            'cluster'      => config('broadcasting.connections.pusher.options.cluster', 'eu'),
            'authEndpoint' => '/api/pusher/auth',
            'enabled'      => ! empty(config('broadcasting.connections.pusher.key')),
        ];
    }

    /**
     * Broadcast an event to a channel.
     */
    public function broadcast(string $channel, string $event, array $data = []): bool
    {
        try {
            $pusher = new \Pusher\Pusher(
                config('broadcasting.connections.pusher.key'),
                config('broadcasting.connections.pusher.secret'),
                config('broadcasting.connections.pusher.app_id'),
                config('broadcasting.connections.pusher.options', [])
            );

            $pusher->trigger($channel, $event, $data);
            return true;
        } catch (\Throwable $e) {
            Log::error('RealtimeService::broadcast failed', [
                'channel' => $channel,
                'event'   => $event,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Build a private channel name scoped to a tenant.
     */
    public function tenantChannel(int $tenantId, string $suffix): string
    {
        return "private-tenant.{$tenantId}.{$suffix}";
    }
}
