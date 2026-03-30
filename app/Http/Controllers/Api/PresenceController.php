<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\PresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PresenceController — Real-time user presence API.
 *
 * Handles heartbeat pings, bulk presence lookups, custom status,
 * and privacy toggles. All endpoints are tenant-scoped.
 */
class PresenceController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * POST /v2/presence/heartbeat
     *
     * Client sends this every 60 seconds while the tab is focused.
     * Rate-limited to 6 per minute per user (allows initial ping + tab-focus
     * events + the 60 s interval without triggering 429s).
     */
    public function heartbeat(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('presence_heartbeat', 6, 60);

        PresenceService::heartbeat($userId);

        return $this->respondWithData(['ok' => true]);
    }

    /**
     * GET /v2/presence/users?user_ids=1,2,3
     *
     * Returns bulk presence for the requested user IDs.
     * Limited to 100 user IDs per request.
     */
    public function users(Request $request): JsonResponse
    {
        $this->requireAuth();

        $userIdsParam = $request->query('user_ids', '');
        if (empty($userIdsParam)) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', __('api.missing_required_field', ['field' => 'user_ids']), 'user_ids');
        }

        $userIds = array_filter(
            array_map('intval', explode(',', $userIdsParam)),
            fn(int $id) => $id > 0
        );

        // Limit to 100 IDs per request
        $userIds = array_slice($userIds, 0, 100);

        if (empty($userIds)) {
            return $this->respondWithData([]);
        }

        $presenceData = PresenceService::getBulkPresence($userIds);

        return $this->respondWithData($presenceData);
    }

    /**
     * PUT /v2/presence/status
     *
     * Set the user's custom status.
     * Body: { "status": "dnd", "custom_status": "In a meeting", "emoji": "📅" }
     */
    public function setStatus(Request $request): JsonResponse
    {
        $userId = $this->requireAuth();

        $status = $request->input('status', 'online');
        $customStatus = $request->input('custom_status');
        $emoji = $request->input('emoji');

        $validStatuses = ['online', 'away', 'dnd', 'offline'];
        if (!in_array($status, $validStatuses, true)) {
            return $this->respondWithError('VALIDATION_INVALID', __('api.invalid_presence_status'), 'status');
        }

        PresenceService::setStatus($userId, $status, $customStatus, $emoji);

        return $this->respondWithData([
            'status' => $status,
            'custom_status' => $customStatus,
            'emoji' => $emoji,
        ]);
    }

    /**
     * PUT /v2/presence/privacy
     *
     * Toggle presence visibility.
     * Body: { "hide_presence": true }
     */
    public function setPrivacy(Request $request): JsonResponse
    {
        $userId = $this->requireAuth();

        $hidePresence = (bool) $request->input('hide_presence', false);

        PresenceService::setPrivacy($userId, $hidePresence);

        return $this->respondWithData(['hide_presence' => $hidePresence]);
    }

    /**
     * GET /v2/presence/online-count
     *
     * Returns the number of online users for the current tenant.
     */
    public function onlineCount(): JsonResponse
    {
        $this->requireAuth();
        $tenantId = $this->getTenantId();

        $count = PresenceService::getOnlineCount($tenantId);

        return $this->respondWithData(['online_count' => $count]);
    }
}
