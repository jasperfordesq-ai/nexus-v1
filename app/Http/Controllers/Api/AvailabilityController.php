<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\MemberAvailabilityService;
use Illuminate\Http\JsonResponse;

/**
 * AvailabilityController — Member availability schedules for matching.
 */
class AvailabilityController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly MemberAvailabilityService $availabilityService,
    ) {}

    /** GET /api/v2/availability/me */
    public function getMyAvailability(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $availability = $this->availabilityService->getForUser($userId, $tenantId);

        return $this->respondWithData($availability);
    }

    /**
     * PUT /api/v2/availability
     *
     * Set the current user's availability schedule.
     * Body: slots (array of {day_of_week, start_time, end_time}), timezone.
     */
    public function setAvailability(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $data = $this->getAllInput();

        $result = $this->availabilityService->setAvailability($userId, $tenantId, $data);

        return $this->respondWithData($result);
    }

    /**
     * GET /api/v2/availability/compatible
     *
     * Find members with compatible availability windows.
     * Query params: day_of_week, start_time, end_time, skill_id.
     */
    public function findCompatible(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $filters = [
            'day_of_week' => $this->query('day_of_week'),
            'start_time' => $this->query('start_time'),
            'end_time' => $this->query('end_time'),
            'skill_id' => $this->queryInt('skill_id'),
        ];

        $members = $this->availabilityService->findCompatible($userId, $tenantId, $filters);

        return $this->respondWithData($members);
    }
}
