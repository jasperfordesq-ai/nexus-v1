<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupService;
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

        if (!GroupService::canView($id, $userId)) {
            return $this->respondWithError('FORBIDDEN', __('api.group_custom_fields_forbidden'), null, 403);
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

        if (!GroupService::canModify($id, $userId)) {
            return $this->respondWithError('FORBIDDEN', __('api.group_admin_required'), null, 403);
        }

        $fields = request()->input('fields', []);

        if (!is_array($fields)) {
            return $this->errorResponse(__('api.group_custom_fields_object_required'), 422);
        }

        GroupCustomFieldService::setValues($id, $fields);

        // Re-fetch updated values to return
        $updated = GroupCustomFieldService::getValues($id);

        return $this->successResponse($updated);
    }
}
