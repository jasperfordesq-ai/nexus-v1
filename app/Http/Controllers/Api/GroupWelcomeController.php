<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupWelcomeService;

/**
 * GroupWelcomeController — Welcome message configuration for groups.
 */
class GroupWelcomeController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/groups/{id}/welcome
     *
     * Get the welcome message configuration for a group.
     */
    public function getConfig(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $config = GroupWelcomeService::getConfig($id);

        return $this->successResponse($config);
    }

    /**
     * PUT /api/v2/groups/{id}/welcome
     *
     * Set the welcome message configuration for a group.
     * Body: { enabled: bool, message: string }
     */
    public function setConfig(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $enabled = (bool) request()->input('enabled', false);
        $message = request()->input('message') ?? '';

        GroupWelcomeService::setConfig($id, $enabled, $message);

        // Re-fetch updated config to return
        $updated = GroupWelcomeService::getConfig($id);

        return $this->successResponse($updated);
    }
}
