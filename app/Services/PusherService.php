<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

/**
 * PusherService — Pusher realtime channel management and authentication.
 *
 * Wraps the Pusher PHP SDK (pusher/pusher-php-server ^7.2) for channel auth,
 * user/presence channel naming, and configuration access.
 *
 * Reads config from `config/broadcasting.php` (PUSHER_KEY, PUSHER_SECRET,
 * PUSHER_APP_ID, PUSHER_CLUSTER env vars). Gracefully no-ops when unconfigured.
 *
 * Self-contained native Laravel implementation — no legacy delegation.
 */
class PusherService
{
    /** Cached Pusher SDK instance. */
    private static ?Pusher $instance = null;

    public function __construct()
    {
    }

    /**
     * Get or create the Pusher SDK instance.
     *
     * Returns null if Pusher is not configured (missing key/secret/app_id).
     */
    public static function getInstance(): ?Pusher
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $key = config('broadcasting.connections.pusher.key', '');
        $secret = config('broadcasting.connections.pusher.secret', '');
        $appId = config('broadcasting.connections.pusher.app_id', '');

        if (empty($key) || empty($secret) || empty($appId)) {
            return null;
        }

        try {
            $options = config('broadcasting.connections.pusher.options', []);

            self::$instance = new Pusher($key, $secret, $appId, $options);

            return self::$instance;
        } catch (\Throwable $e) {
            Log::error('PusherService: Failed to create Pusher instance', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get Pusher configuration array (safe for internal use — includes secret).
     */
    public function getConfig(): array
    {
        return [
            'key' => config('broadcasting.connections.pusher.key', ''),
            'secret' => config('broadcasting.connections.pusher.secret', ''),
            'app_id' => config('broadcasting.connections.pusher.app_id', ''),
            'cluster' => config('broadcasting.connections.pusher.options.cluster', 'eu'),
            'encrypted' => true,
            'useTLS' => true,
        ];
    }

    /**
     * Get the Pusher public (app) key for frontend clients.
     */
    public function getPublicKey(): string
    {
        return config('broadcasting.connections.pusher.key', '');
    }

    /**
     * Get the Pusher cluster identifier.
     */
    public function getCluster(): string
    {
        return config('broadcasting.connections.pusher.options.cluster', 'eu');
    }

    /**
     * Check whether Pusher is configured with valid credentials.
     */
    public function isConfigured(): bool
    {
        return !empty(config('broadcasting.connections.pusher.key'))
            && !empty(config('broadcasting.connections.pusher.secret'))
            && !empty(config('broadcasting.connections.pusher.app_id'));
    }

    /**
     * Get the private channel name for a specific user.
     *
     * Convention: private-user.{userId}
     */
    public function getUserChannel(int $userId): string
    {
        return "private-user.{$userId}";
    }

    /**
     * Get the presence channel name for the current tenant.
     *
     * Convention: presence-tenant.{tenantId}
     */
    public function getPresenceChannel(): string
    {
        $tenantId = \App\Core\TenantContext::getId();
        return "presence-tenant.{$tenantId}";
    }

    /**
     * Authenticate a private channel subscription.
     *
     * Validates the user is allowed to subscribe to the channel, then returns
     * the Pusher auth JSON string.
     *
     * Private channel naming convention:
     *   - private-user.{userId} — only the owning user
     *   - private-tenant.{tenantId}.* — any user in that tenant
     *
     * @return string|null Pusher auth JSON string, or null if forbidden.
     */
    public function authPrivateChannel(string $channelName, string $socketId, int $userId): ?string
    {
        $pusher = self::getInstance();
        if ($pusher === null) {
            return null;
        }

        // Validate channel access
        if (!$this->canAccessPrivateChannel($channelName, $userId)) {
            return null;
        }

        try {
            // authorizeChannel() returns a JSON string directly
            return $pusher->authorizeChannel($channelName, $socketId);
        } catch (\Throwable $e) {
            Log::error('PusherService::authPrivateChannel failed', [
                'channel' => $channelName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Authenticate a presence channel subscription.
     *
     * Returns the Pusher auth JSON string with user data attached.
     *
     * @return string|null Pusher auth JSON string, or null if forbidden.
     */
    public function authPresenceChannel(string $channelName, string $socketId, int $userId, array $userInfo = []): ?string
    {
        $pusher = self::getInstance();
        if ($pusher === null) {
            return null;
        }

        try {
            // authorizePresenceChannel() takes user_info directly and returns JSON string
            return $pusher->authorizePresenceChannel(
                $channelName,
                $socketId,
                (string) $userId,
                $userInfo ?: ['id' => $userId]
            );
        } catch (\Throwable $e) {
            Log::error('PusherService::authPresenceChannel failed', [
                'channel' => $channelName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    /**
     * Check if a user can access a private channel.
     *
     * Access rules:
     *   - private-user.{N} — only user N
     *   - private-tenant.{T}.* — any user with tenant T as current tenant
     */
    private function canAccessPrivateChannel(string $channelName, int $userId): bool
    {
        // User channel: private-user.{userId}
        if (preg_match('/^private-user\.(\d+)$/', $channelName, $matches)) {
            return (int) $matches[1] === $userId;
        }

        // Tenant channel: private-tenant.{tenantId}.*
        if (preg_match('/^private-tenant\.(\d+)/', $channelName, $matches)) {
            $channelTenantId = (int) $matches[1];
            $currentTenantId = \App\Core\TenantContext::getId();
            return $channelTenantId === $currentTenantId;
        }

        // Unknown channel pattern — deny by default
        return false;
    }
}
