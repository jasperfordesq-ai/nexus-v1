<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\JobVacancyService;
use Nexus\Core\TenantContext;

/**
 * JobVacanciesController — Community job vacancy listings.
 *
 * Core CRUD uses Eloquent via JobVacancyService; advanced features delegate to legacy.
 */
class JobVacanciesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly JobVacancyService $jobService,
    ) {}

    /**
     * Ensure the job_vacancies feature is enabled.
     */
    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('job_vacancies')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', 'Job Vacancies module is not enabled for this community', null, 403)
            );
        }
    }

    /** GET /api/v2/jobs — list jobs with filters + cursor pagination */
    public function index(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('jobs_list', 60, 60);

        $userId = $this->getOptionalUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('status')) {
            $filters['status'] = $this->query('status');
        }
        if ($this->query('type')) {
            $filters['type'] = $this->query('type');
        }
        if ($this->query('commitment')) {
            $filters['commitment'] = $this->query('commitment');
        }
        if ($this->query('category')) {
            $filters['category'] = $this->query('category');
        }
        if ($this->query('search')) {
            $filters['search'] = $this->query('search');
        }
        if ($this->query('user_id')) {
            $filters['user_id'] = $this->queryInt('user_id');
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }
        if ($this->query('featured')) {
            $filters['featured'] = $this->queryBool('featured');
        }

        $result = $this->jobService->getAll($filters, $userId);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /** GET /api/v2/jobs/{id} — single job vacancy */
    public function show(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('jobs_show', 60, 60);

        $job = $this->jobService->getById($id);

        if (!$job) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Job vacancy not found', null, 404);
        }

        return $this->respondWithData($job);
    }

    /** POST /api/v2/jobs — create a new job vacancy */
    public function store(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_create', 5, 60);

        $data = $this->getAllInput();
        $jobId = $this->jobService->create($userId, $data);

        $job = $this->jobService->getById($jobId);

        return $this->respondWithData($job, null, 201);
    }

    /** POST /api/v2/jobs/{id}/apply — apply to a job */
    public function apply(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_apply', 5, 60);

        $data = $this->getAllInput();
        $applicationId = $this->jobService->apply($id, $userId, $data);

        if ($applicationId === null) {
            return $this->respondWithError('RESOURCE_CONFLICT', 'You have already applied to this job', null, 409);
        }

        $job = $this->jobService->getById($id);

        return $this->respondWithData($job, null, 201);
    }

    // ========================================
    // DELEGATION — advanced features via legacy
    // ========================================

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
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'deleteAlert', [(int) $id]);
    }

    public function unsubscribeAlert($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'unsubscribeAlert', [(int) $id]);
    }

    public function resubscribeAlert($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'resubscribeAlert', [(int) $id]);
    }

    public function update($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'update', [(int) $id]);
    }

    public function destroy($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'destroy', [(int) $id]);
    }

    public function saveJob($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'saveJob', [(int) $id]);
    }

    public function unsaveJob($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'unsaveJob', [(int) $id]);
    }

    public function matchPercentage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'matchPercentage', [(int) $id]);
    }

    public function qualificationAssessment($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'qualificationAssessment', [(int) $id]);
    }

    public function applications($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'applications', [(int) $id]);
    }

    public function analytics($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'analytics', [(int) $id]);
    }

    public function renewJob($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'renewJob', [(int) $id]);
    }

    public function featureJob($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'featureJob', [(int) $id]);
    }

    public function unfeatureJob($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'unfeatureJob', [(int) $id]);
    }

    public function updateApplication($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'updateApplication', [(int) $id]);
    }

    public function applicationHistory($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\JobVacanciesApiController::class, 'applicationHistory', [(int) $id]);
    }
}
