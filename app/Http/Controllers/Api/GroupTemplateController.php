<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupTemplateService;

/**
 * GroupTemplateController — Pre-built group templates for quick group creation.
 */
class GroupTemplateController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/groups/templates
     *
     * List all available group templates.
     */
    public function index(): JsonResponse
    {
        $result = GroupTemplateService::getAll();

        return $this->successResponse($result);
    }
}
