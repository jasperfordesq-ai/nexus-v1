<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\JobVacancyService;
use Illuminate\Http\JsonResponse;

/**
 * JobVacanciesController — Community job vacancy listings.
 */
class JobVacanciesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly JobVacancyService $jobService,
    ) {}

    /** GET /api/v2/job-vacancies */
    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $q = $this->query('q');

        $result = $this->jobService->getAll($tenantId, $page, $perPage, $q);

        return $this->respondWithPaginatedCollection(
            $result['items'],
            $result['total'],
            $page,
            $perPage
        );
    }

    /** GET /api/v2/job-vacancies/{id} */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $job = $this->jobService->getById($id, $tenantId);

        if ($job === null) {
            return $this->respondWithError('NOT_FOUND', 'Job vacancy not found', null, 404);
        }

        return $this->respondWithData($job);
    }

    /** POST /api/v2/job-vacancies */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $this->rateLimit('job_create', 5, 60);

        $data = $this->getAllInput();

        $job = $this->jobService->create($userId, $tenantId, $data);

        return $this->respondWithData($job, null, 201);
    }

    /** POST /api/v2/job-vacancies/{id}/apply */
    public function apply(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $this->rateLimit('job_apply', 5, 60);

        $data = $this->getAllInput();

        $result = $this->jobService->apply($id, $userId, $tenantId, $data);

        if ($result === null) {
            return $this->respondWithError('NOT_FOUND', 'Job vacancy not found or closed', null, 404);
        }

        return $this->respondWithData($result, null, 201);
    }
}
