<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupNotificationPreferenceService;

class GroupNotificationPrefController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function get(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) return $userId;
        return $this->successResponse(GroupNotificationPreferenceService::get($userId, $id));
    }

    public function set(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) return $userId;
        $data = request()->only(['frequency', 'email_enabled', 'push_enabled']);
        GroupNotificationPreferenceService::set($userId, $id, $data);
        return $this->successResponse(['message' => 'Preferences updated']);
    }
}
