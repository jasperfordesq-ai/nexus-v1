<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\VolunteerService;

/**
 * VolunteerController -- Volunteering opportunities and applications.
 */
class VolunteerController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly VolunteerService $volunteerService,
    ) {}

    /** GET /api/v2/volunteer/opportunities */
    public function opportunities(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        
        $result = $this->volunteerService->getOpportunities($tenantId, $page, $perPage);
        
        return $this->respondWithPaginatedCollection(
            $result['items'], $result['total'], $page, $perPage
        );
    }

    /** GET /api/v2/volunteer/{id} */
    public function show(int $id): JsonResponse
    {
        $opportunity = $this->volunteerService->getById($id, $this->getTenantId());
        
        if ($opportunity === null) {
            return $this->respondWithError('NOT_FOUND', 'Opportunity not found', null, 404);
        }
        
        return $this->respondWithData($opportunity);
    }

    /** POST /api/v2/volunteer/{id}/apply */
    public function apply(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('volunteer_apply', 10, 60);
        
        $data = $this->getAllInput();
        $application = $this->volunteerService->apply($id, $userId, $this->getTenantId(), $data);
        
        if ($application === null) {
            return $this->respondWithError('NOT_FOUND', 'Opportunity not found', null, 404);
        }
        
        return $this->respondWithData($application, null, 201);
    }

    /** GET /api/v2/volunteer/my-applications */
    public function myApplications(): JsonResponse
    {
        $userId = $this->requireAuth();
        $applications = $this->volunteerService->getUserApplications($userId, $this->getTenantId());
        
        return $this->respondWithData($applications);
    }

}
