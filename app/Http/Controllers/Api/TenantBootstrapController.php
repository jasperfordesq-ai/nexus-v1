<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\TenantService;

/**
 * TenantBootstrapController -- Tenant configuration bootstrap for SPA init.
 */
class TenantBootstrapController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly TenantService $tenantService,
    ) {}

    /** GET /api/v2/tenant/bootstrap */
    public function bootstrap(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $config = $this->tenantService->getBootstrapConfig($tenantId);
        
        if ($config === null) {
            return $this->respondWithError('NOT_FOUND', 'Tenant not found', null, 404);
        }
        
        return $this->respondWithData($config);
    }

    /** GET /api/v2/tenants */
    public function list(): JsonResponse
    {
        $tenants = $this->tenantService->getPublicList();
        
        return $this->respondWithData($tenants);
    }

}
