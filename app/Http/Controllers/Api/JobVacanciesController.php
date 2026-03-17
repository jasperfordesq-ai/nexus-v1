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
 * Core CRUD uses Eloquent via JobVacancyService. All remaining endpoints
 * now call legacy JobVacancyService static methods directly instead of
 * ob_start() delegation.
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

    // =====================================================================
    // CORE CRUD (Eloquent via JobVacancyService)
    // =====================================================================

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

        $userId = $this->getOptionalUserId();

        $job = $this->jobService->legacyGetById($id, $userId);

        if (!$job) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Job vacancy not found', null, 404);
        }

        // Increment views
        $this->jobService->incrementViews($id, $userId);

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

    /** PUT /api/v2/jobs/{id} — update a job vacancy */
    public function update($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_update', 10, 60);

        $data = $this->getAllInput();

        $success = $this->jobService->update((int) $id, $userId, $data);

        if (!$success) {
            $errors = $this->jobService->getErrors();
            $status = 422;

            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            return $this->respondWithErrors($errors, $status);
        }

        $vacancy = $this->jobService->legacyGetById((int) $id, $userId);

        return $this->respondWithData($vacancy);
    }

    /** DELETE /api/v2/jobs/{id} — delete a job vacancy */
    public function destroy($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_delete', 5, 60);

        $success = $this->jobService->delete((int) $id, $userId);

        if (!$success) {
            $errors = $this->jobService->getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            return $this->respondWithErrors($errors, $status);
        }

        return $this->noContent();
    }

    /** POST /api/v2/jobs/{id}/apply — apply to a job */
    public function apply(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_apply', 5, 60);

        $message = $this->input('message');

        $applicationId = $this->jobService->legacyApply($id, $userId, $message);

        if ($applicationId === null) {
            $errors = $this->jobService->getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_CONFLICT') {
                    $status = 409;
                    break;
                }
            }

            return $this->respondWithErrors($errors, $status);
        }

        $vacancy = $this->jobService->legacyGetById($id, $userId);

        return $this->respondWithData($vacancy, null, 201);
    }

    // =====================================================================
    // SAVED JOBS (J1)
    // =====================================================================

    /** GET /api/v2/jobs/saved */
    public function savedJobs(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_saved_list', 30, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->jobService->getSavedJobs($userId, $filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /** POST /api/v2/jobs/{id}/save */
    public function saveJob($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_save', 30, 60);

        $success = $this->jobService->saveJob((int) $id, $userId);

        if (!$success) {
            $errors = $this->jobService->getErrors();
            return $this->respondWithErrors($errors, 400);
        }

        return $this->respondWithData(['message' => 'Job saved successfully', 'is_saved' => true], null, 201);
    }

    /** DELETE /api/v2/jobs/{id}/save */
    public function unsaveJob($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_unsave', 30, 60);

        $this->jobService->unsaveJob((int) $id, $userId);

        return $this->respondWithData(['message' => 'Job removed from saved', 'is_saved' => false]);
    }

    // =====================================================================
    // MY APPLICATIONS & MY POSTINGS
    // =====================================================================

    /** GET /api/v2/jobs/my-applications */
    public function myApplications(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_my_apps', 30, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('status')) {
            $filters['status'] = $this->query('status');
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->jobService->getMyApplications($userId, $filters);

        return $this->respondWithData([
            'items' => $result['items'],
            'cursor' => $result['cursor'],
            'has_more' => $result['has_more'],
        ]);
    }

    /** GET /api/v2/jobs/my-postings */
    public function myPostings(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_my_postings', 30, 60);

        $tenantId = TenantContext::getId();

        $params = [
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        if ($this->query('cursor')) {
            $params['cursor'] = $this->query('cursor');
        }

        $result = $this->jobService->getMyPostings($userId, $tenantId, $params);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $params['limit'],
            $result['has_more']
        );
    }

    // =====================================================================
    // JOB ALERTS (J6)
    // =====================================================================

    /** GET /api/v2/jobs/alerts */
    public function listAlerts(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_alerts_list', 30, 60);

        $alerts = $this->jobService->getAlerts($userId);

        return $this->respondWithData($alerts);
    }

    /** POST /api/v2/jobs/alerts */
    public function createAlert(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_alerts_create', 5, 60);

        $data = $this->getAllInput();

        $alertId = $this->jobService->subscribeAlert($userId, $data);

        if ($alertId === null) {
            $errors = $this->jobService->getErrors();
            return $this->respondWithErrors($errors, 422);
        }

        return $this->respondWithData([
            'id' => $alertId,
            'message' => 'Job alert created successfully',
        ], null, 201);
    }

    /** DELETE /api/v2/jobs/alerts/{id} */
    public function deleteAlert($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_alerts_delete', 10, 60);

        $this->jobService->deleteAlert((int) $id, $userId);

        return $this->noContent();
    }

    /** PUT /api/v2/jobs/alerts/{id}/unsubscribe */
    public function unsubscribeAlert($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_alerts_unsub', 10, 60);

        $this->jobService->unsubscribeAlert((int) $id, $userId);

        return $this->respondWithData(['message' => 'Alert unsubscribed successfully']);
    }

    /** PUT /api/v2/jobs/alerts/{id}/resubscribe */
    public function resubscribeAlert($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_alerts_resub', 10, 60);

        $this->jobService->resubscribeAlert((int) $id, $userId);

        return $this->respondWithData(['message' => 'Alert resubscribed successfully']);
    }

    // =====================================================================
    // SKILLS MATCHING (J2) & QUALIFICATION (J5)
    // =====================================================================

    /** GET /api/v2/jobs/{id}/match */
    public function matchPercentage($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_match', 30, 60);

        $result = $this->jobService->calculateMatchPercentage($userId, (int) $id);

        return $this->respondWithData($result);
    }

    /** GET /api/v2/jobs/{id}/qualified */
    public function qualificationAssessment($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_qualified', 20, 60);

        $result = $this->jobService->getQualificationAssessment($userId, (int) $id);

        if ($result === null) {
            $errors = $this->jobService->getErrors();
            return $this->respondWithErrors($errors, 404);
        }

        return $this->respondWithData($result);
    }

    // =====================================================================
    // APPLICATIONS MANAGEMENT
    // =====================================================================

    /** GET /api/v2/jobs/{id}/applications */
    public function applications($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_applications', 30, 60);

        $applications = $this->jobService->getApplications((int) $id, $userId);

        if ($applications === null) {
            $errors = $this->jobService->getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData($applications);
    }

    /** PUT /api/v2/jobs/applications/{id} */
    public function updateApplication($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_app_update', 10, 60);

        $status = $this->input('status');
        $notes = $this->input('notes');

        if (empty($status)) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'Status is required', 'status', 400);
        }

        $success = $this->jobService->updateApplicationStatus((int) $id, $userId, $status, $notes);

        if (!$success) {
            $errors = $this->jobService->getErrors();
            $httpStatus = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $httpStatus = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $httpStatus = 403;
                    break;
                }
            }

            return $this->respondWithErrors($errors, $httpStatus);
        }

        return $this->respondWithData(['message' => 'Application updated successfully']);
    }

    /** GET /api/v2/jobs/applications/{id}/history */
    public function applicationHistory($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_app_history', 30, 60);

        $history = $this->jobService->getApplicationHistory((int) $id, $userId);

        if ($history === null) {
            $errors = $this->jobService->getErrors();
            $httpStatus = 400;
            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $httpStatus = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $httpStatus = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $httpStatus);
        }

        return $this->respondWithData($history);
    }

    // =====================================================================
    // ANALYTICS & FEATURED (J8, J10)
    // =====================================================================

    /** GET /api/v2/jobs/{id}/analytics */
    public function analytics($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_analytics', 20, 60);

        $analytics = $this->jobService->getAnalytics((int) $id, $userId);

        if ($analytics === null) {
            $errors = $this->jobService->getErrors();
            $httpStatus = 400;
            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $httpStatus = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $httpStatus = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $httpStatus);
        }

        return $this->respondWithData($analytics);
    }

    /** POST /api/v2/jobs/{id}/renew */
    public function renewJob($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_renew', 5, 60);

        $days = $this->input('days') ?? 30;
        $days = max(1, min(90, (int) $days));

        $success = $this->jobService->renewJob((int) $id, $userId, $days);

        if (!$success) {
            $errors = $this->jobService->getErrors();
            $httpStatus = 400;
            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $httpStatus = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $httpStatus = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $httpStatus);
        }

        $vacancy = $this->jobService->legacyGetById((int) $id, $userId);

        return $this->respondWithData($vacancy);
    }

    /** POST /api/v2/jobs/{id}/feature */
    public function featureJob($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAdmin();
        $this->rateLimit('jobs_feature', 10, 60);

        $days = $this->input('days') ?? 7;
        $days = max(1, min(30, (int) $days));

        $success = $this->jobService->featureJob((int) $id, $userId, $days);

        if (!$success) {
            $errors = $this->jobService->getErrors();
            $httpStatus = 400;
            foreach ($errors as $error) {
                if ($error['code'] === 'RESOURCE_NOT_FOUND') {
                    $httpStatus = 404;
                    break;
                }
                if ($error['code'] === 'RESOURCE_FORBIDDEN') {
                    $httpStatus = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $httpStatus);
        }

        $vacancy = $this->jobService->legacyGetById((int) $id, $userId);

        return $this->respondWithData($vacancy);
    }

    /** DELETE /api/v2/jobs/{id}/feature */
    public function unfeatureJob($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAdmin();
        $this->rateLimit('jobs_unfeature', 10, 60);

        $success = $this->jobService->unfeatureJob((int) $id, $userId);

        if (!$success) {
            $errors = $this->jobService->getErrors();
            return $this->respondWithErrors($errors, 400);
        }

        $vacancy = $this->jobService->legacyGetById((int) $id, $userId);

        return $this->respondWithData($vacancy);
    }
}
