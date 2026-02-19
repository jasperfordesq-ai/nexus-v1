<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiAuth;
use Nexus\Services\PusherService;
use Nexus\Services\RealtimeService;
use Nexus\Services\FederationRealtimeService;

/**
 * Pusher Channel Authentication Controller
 *
 * Handles authentication for private and presence channels.
 * Pusher calls this endpoint when clients subscribe to secure channels.
 */
class PusherAuthController
{
    use ApiAuth;

    private function jsonResponse($data, $status = 200)
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
        exit;
    }

    private function getUserId()
    {
        // Use unified auth - returns null if not authenticated (doesn't exit)
        return $this->getAuthenticatedUserId();
    }

    /**
     * POST /api/pusher/auth
     * Authenticates private and presence channel subscriptions
     */
    public function auth()
    {
        try {
            $userId = $this->getUserId();

            if ($userId === null) {
                $this->jsonResponse(['error' => 'Unauthorized'], 401);
            }

            $socketId = $_POST['socket_id'] ?? null;
            $channelName = $_POST['channel_name'] ?? null;

            if (empty($socketId) || empty($channelName)) {
                $this->jsonResponse(['error' => 'Missing socket_id or channel_name'], 400);
            }

            // Handle presence channels
            if (strpos($channelName, 'presence-') === 0) {
                $this->authPresence($channelName, $socketId, $userId);
                return;
            }

            // Handle federation private channels (cross-tenant)
            if (strpos($channelName, 'private-federation.') === 0) {
                $this->authFederation($channelName, $socketId, $userId);
                return;
            }

            // Handle private channels
            if (strpos($channelName, 'private-') === 0) {
                $this->authPrivate($channelName, $socketId, $userId);
                return;
            }

            // Public channels don't need auth
            $this->jsonResponse(['error' => 'Invalid channel type'], 400);
        } catch (\Throwable $e) {
            error_log('[PusherAuth] Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->jsonResponse(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Authenticate private channel subscription
     */
    private function authPrivate(string $channelName, string $socketId, int $userId)
    {
        try {
            $auth = PusherService::authPrivateChannel($channelName, $socketId, $userId);

            if ($auth === null) {
                $this->jsonResponse(['error' => 'Forbidden'], 403);
            }

            // Return raw JSON from Pusher
            header('Content-Type: application/json');
            echo $auth;
            exit;
        } catch (\Throwable $e) {
            error_log('[PusherAuth] authPrivate error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Auth error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Authenticate presence channel subscription
     */
    private function authPresence(string $channelName, string $socketId, int $userId)
    {
        try {
            // Get user info for presence data
            $userInfo = $this->getUserInfo($userId);

            $auth = PusherService::authPresenceChannel($channelName, $socketId, $userId, $userInfo);

            if ($auth === null) {
                $this->jsonResponse(['error' => 'Forbidden'], 403);
            }

            // Return raw JSON from Pusher
            header('Content-Type: application/json');
            echo $auth;
            exit;
        } catch (\Throwable $e) {
            error_log('[PusherAuth] authPresence error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Auth error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Authenticate federation channel subscription (cross-tenant)
     */
    private function authFederation(string $channelName, string $socketId, int $userId)
    {
        try {
            $tenantId = TenantContext::getId();
            $auth = FederationRealtimeService::authFederationChannel($channelName, $socketId, $userId, $tenantId);

            if ($auth === null) {
                $this->jsonResponse(['error' => 'Forbidden'], 403);
            }

            // Return raw JSON from Pusher
            header('Content-Type: application/json');
            echo $auth;
            exit;
        } catch (\Throwable $e) {
            error_log('[PusherAuth] authFederation error: ' . $e->getMessage());
            $this->jsonResponse(['error' => 'Auth error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get user info for presence channel
     */
    private function getUserInfo(int $userId): array
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT id, first_name, last_name, avatar_url FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['id' => $userId];
        }

        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

        return [
            'id' => $user['id'],
            'name' => $name ?: 'User',
            'avatar' => $user['avatar_url'] ?? null,
        ];
    }

    /**
     * GET /api/pusher/config
     * Returns Pusher configuration for frontend initialization
     */
    public function config()
    {
        $userId = $this->getUserId();
        $config = RealtimeService::getFrontendConfig();

        // Add user-specific channels if logged in
        if ($userId !== null) {
            $config['channels'] = [
                'user' => PusherService::getUserChannel($userId),
                'presence' => PusherService::getPresenceChannel(),
            ];
            $config['userId'] = $userId;
        }

        $this->jsonResponse($config);
    }

    /**
     * GET /api/pusher/debug
     * Debug endpoint to check Pusher configuration status
     */
    public function debug()
    {
        try {
            $userId = $this->getUserId();
            $tenantId = TenantContext::getId();
            $config = PusherService::getConfig();

            $this->jsonResponse([
                'status' => 'ok',
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'pusher_configured' => PusherService::isConfigured(),
                'has_app_id' => !empty($config['app_id']),
                'has_key' => !empty($config['key']),
                'has_secret' => !empty($config['secret']),
                'cluster' => $config['cluster'] ?? 'not set',
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'status' => 'error',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }
}
