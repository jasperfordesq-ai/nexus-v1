<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupCollectionService;

class GroupCollectionController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function index(): JsonResponse
    {
        return $this->successResponse(GroupCollectionService::getAll());
    }

    public function show(int $id): JsonResponse
    {
        $col = GroupCollectionService::get($id);
        return $col ? $this->successResponse($col) : $this->errorResponse('Not found', 404);
    }
}
