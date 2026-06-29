<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\NextPublicFrontendReadinessService;
use Illuminate\Http\JsonResponse;

class AdminNextPublicFrontendController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly NextPublicFrontendReadinessService $readiness,
    ) {
    }

    public function show(): JsonResponse
    {
        $this->requireAdmin();

        return $this->respondWithData($this->readiness->summary());
    }
}
