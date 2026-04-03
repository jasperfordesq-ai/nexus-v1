<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupDataExportService;

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

        $data = GroupDataExportService::exportAll($id);

        if (empty($data)) {
            return $this->errorResponse('Group not found or no data to export', 404);
        }

        return $this->successResponse($data);
    }
}
