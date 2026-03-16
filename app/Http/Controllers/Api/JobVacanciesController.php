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

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }


    public function savedJobs(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'savedJobs');
    }


    public function myApplications(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'myApplications');
    }


    public function myPostings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'myPostings');
    }


    public function listAlerts(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'listAlerts');
    }


    public function createAlert(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'createAlert');
    }


    public function deleteAlert($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'deleteAlert', [$id]);
    }


    public function unsubscribeAlert($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'unsubscribeAlert', [$id]);
    }


    public function resubscribeAlert($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'resubscribeAlert', [$id]);
    }


    public function update($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'update', [$id]);
    }


    public function destroy($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'destroy', [$id]);
    }


    public function saveJob($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'saveJob', [$id]);
    }


    public function unsaveJob($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'unsaveJob', [$id]);
    }


    public function matchPercentage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'matchPercentage', [$id]);
    }


    public function qualificationAssessment($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'qualificationAssessment', [$id]);
    }


    public function applications($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'applications', [$id]);
    }


    public function analytics($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'analytics', [$id]);
    }


    public function renewJob($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'renewJob', [$id]);
    }


    public function featureJob($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'featureJob', [$id]);
    }


    public function unfeatureJob($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'unfeatureJob', [$id]);
    }


    public function updateApplication($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'updateApplication', [$id]);
    }


    public function applicationHistory($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'applicationHistory', [$id]);
    }

}
