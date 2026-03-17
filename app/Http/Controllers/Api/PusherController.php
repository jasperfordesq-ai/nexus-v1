<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Nexus\Core\TenantContext;
use Nexus\Services\PusherService;
use Nexus\Services\RealtimeService;
use Nexus\Services\FederationRealtimeService;

/**
 * PusherController — Eloquent-powered Pusher realtime auth and config.
 *
 * Fully migrated from legacy delegation to native Laravel.
 * IMPORTANT: auth() returns RAW Pusher JSON (not wrapped in respondWithData),
 * because the Pusher JS client expects the raw auth response format.
 */
class PusherController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * POST /api/v2/pusher/auth
     *
     * Authenticates private, presence, and federation channel subscriptions.
     * Returns RAW Pusher auth JSON — not wrapped in data envelope.
     */
    public function auth(): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            $socketId = $this->input('socket_id') ?? request()->request->get('socket_id');
            $channelName = $this->input('channel_name') ?? request()->request->get('channel_name');

            if (empty($socketId) || empty($channelName)) {
                return response()->json(['error' => 'Missing socket_id or channel_name'], 400);
            }

            // Handle presence channels
            if (str_starts_with($channelName, 'presence-')) {
                return $this->authPresence($channelName, $socketId, $userId);
            }

            // Handle federation private channels (cross-tenant)
            if (str_starts_with($channelName, 'private-federation.')) {
                return $this->authFederation($channelName, $socketId, $userId);
            }

            // Handle private channels
            if (str_starts_with($channelName, 'private-')) {
                return $this->authPrivate($channelName, $socketId, $userId);
            }

            // Public channels don't need auth
            return response()->json(['error' => 'Invalid channel type'], 400);
        } catch (\Throwable $e) {
            Log::error('[PusherAuth] Exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['error' => 'Server error'], 500);
        }
    }

    /**
     * GET /api/v2/pusher/config
     *
     * Returns Pusher configuration for frontend initialization.
     */
    public function config(): JsonResponse
    {
        $userId = $this->getOptionalUserId();
        $config = RealtimeService::getFrontendConfig();

        // Add user-specific channels if logged in
        if ($userId !== null) {
            $config['channels'] = [
                'user' => PusherService::getUserChannel($userId),
                'presence' => PusherService::getPresenceChannel(),
            ];
            $config['userId'] = $userId;
        }

        return $this->respondWithData($config);
    }

    /**
     * Authenticate private channel subscription.
     * Returns RAW Pusher auth JSON.
     */
    private function authPrivate(string $channelName, string $socketId, int $userId): JsonResponse
    {
        try {
            $auth = PusherService::authPrivateChannel($channelName, $socketId, $userId);

            if ($auth === null) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            // Return raw JSON string from Pusher SDK
            return response()->json(json_decode($auth, true));
        } catch (\Throwable $e) {
            Log::error('[PusherAuth] authPrivate error', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Auth error'], 500);
        }
    }

    /**
     * Authenticate presence channel subscription.
     * Returns RAW Pusher auth JSON.
     */
    private function authPresence(string $channelName, string $socketId, int $userId): JsonResponse
    {
        try {
            $userInfo = $this->getUserInfo($userId);

            $auth = PusherService::authPresenceChannel($channelName, $socketId, $userId, $userInfo);

            if ($auth === null) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            // Return raw JSON string from Pusher SDK
            return response()->json(json_decode($auth, true));
        } catch (\Throwable $e) {
            Log::error('[PusherAuth] authPresence error', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Auth error'], 500);
        }
    }

    /**
     * Authenticate federation channel subscription (cross-tenant).
     * Returns RAW Pusher auth JSON.
     */
    private function authFederation(string $channelName, string $socketId, int $userId): JsonResponse
    {
        try {
            $tenantId = TenantContext::getId();
            $auth = FederationRealtimeService::authFederationChannel($channelName, $socketId, $userId, $tenantId);

            if ($auth === null) {
                return response()->json(['error' => 'Forbidden'], 403);
            }

            // Return raw JSON string from Pusher SDK
            return response()->json(json_decode($auth, true));
        } catch (\Throwable $e) {
            Log::error('[PusherAuth] authFederation error', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Auth error'], 500);
        }
    }

    /**
     * Get user info for presence channel data.
     */
    private function getUserInfo(int $userId): array
    {
        $user = DB::table('users')
            ->select('id', 'first_name', 'last_name', 'avatar_url')
            ->where('id', $userId)
            ->first();

        if (!$user) {
            return ['id' => $userId];
        }

        $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

        return [
            'id' => $user->id,
            'name' => $name ?: 'User',
            'avatar' => $user->avatar_url ?? null,
        ];
    }
}
