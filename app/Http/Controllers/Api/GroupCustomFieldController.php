<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

/**
 * Fail-closed adapter for the unfinished group custom-field capability.
 *
 * Re-enable only with a tenant-authoritative field-definition, validation,
 * lifecycle, and end-user rendering contract.
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

        return $this->unavailable();
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

        return $this->unavailable();
    }

    private function unavailable(): JsonResponse
    {
        return $this->respondWithError(
            'CAPABILITY_UNAVAILABLE',
            __('api.service_unavailable'),
            null,
            410,
        );
    }
}
