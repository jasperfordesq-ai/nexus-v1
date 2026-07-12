<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupService;
use App\Services\GroupNotificationPreferenceService;

class GroupNotificationPrefController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function get(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) return $userId;
        if (!GroupService::isActiveMember($id, $userId) && !GroupService::canModify($id, $userId)) {
            return $this->respondWithError('FORBIDDEN', __('api.group_notification_member_required'), null, 403);
        }
        return $this->successResponse(GroupNotificationPreferenceService::get($userId, $id));
    }

    public function set(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) return $userId;
        if (!GroupService::isActiveMember($id, $userId) && !GroupService::canModify($id, $userId)) {
            return $this->respondWithError('FORBIDDEN', __('api.group_notification_member_required'), null, 403);
        }
        $frequency = request()->input('frequency');
        if (! is_string($frequency) || ! in_array($frequency, ['instant', 'digest', 'muted'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_frequency'), 'frequency', 422);
        }

        $emailEnabled = request()->input('email_enabled');
        if (! in_array($emailEnabled, [true, false, 0, 1, '0', '1'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_input'), 'email_enabled', 422);
        }
        $pushEnabled = request()->input('push_enabled');
        if (! in_array($pushEnabled, [true, false, 0, 1, '0', '1'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_input'), 'push_enabled', 422);
        }

        $preferences = GroupNotificationPreferenceService::set($userId, $id, [
            'frequency' => $frequency,
            'email_enabled' => $emailEnabled,
            'push_enabled' => $pushEnabled,
        ]);
        return $this->successResponse([
            'message' => __('api_controllers_3.group_notification_pref.preferences_updated'),
            'preferences' => $preferences,
        ]);
    }
}
