<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupDataExportService;
use App\Services\GroupService;

/**
 * GroupDataExportController — Full data export for groups.
 */
class GroupDataExportController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/groups/{id}/export
     *
     * Export all group data as JSON (members, discussions, files, settings, etc.).
     */
    public function exportAll(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $group = GroupService::getById($id, $userId, true);
        if (!$group) {
            return $this->respondWithError('NOT_FOUND', __('api.group_not_found'), null, 404);
        }

        if (!GroupService::canModify($id, $userId)) {
            return $this->respondWithError('FORBIDDEN', __('api.group_export_forbidden'), null, 403);
        }

        $data = GroupDataExportService::exportAll($id, $userId);

        if ($data === null) {
            return $this->respondWithError('NOT_FOUND', __('api.group_not_found'), null, 404);
        }

        return $this->successResponse($data);
    }
}
