<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupCustomFieldService;

/**
 * GroupCustomFieldController — Custom field values for groups.
 */
class GroupCustomFieldController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/groups/{id}/custom-fields
     *
     * Get all custom field values for a group.
     */
    public function getValues(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $values = GroupCustomFieldService::getValues($id);

        return $this->successResponse($values);
    }

    /**
     * PUT /api/v2/groups/{id}/custom-fields
     *
     * Set custom field values for a group.
     * Body: { fields: { key: value, ... } }
     */
    public function setValues(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $fields = request()->input('fields', []);

        if (!is_array($fields)) {
            return $this->errorResponse('A valid fields object is required', 422);
        }

        $result = GroupCustomFieldService::setValues($id, $fields);

        if ($result === null) {
            return $this->errorResponse('Failed to update custom fields', 400);
        }

        return $this->successResponse($result);
    }
}
