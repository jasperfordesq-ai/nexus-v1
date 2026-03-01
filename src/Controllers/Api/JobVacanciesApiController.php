<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\JobVacancyService;
use Nexus\Core\ApiErrorCodes;
use Nexus\Core\TenantContext;

/**
 * JobVacanciesApiController - RESTful API v2 for job vacancies
 *
 * Provides full CRUD operations for job vacancies plus application management.
 *
 * Endpoints:
 * - GET    /api/v2/jobs              - List all job vacancies (paginated)
 * - POST   /api/v2/jobs              - Create a new job vacancy
 * - GET    /api/v2/jobs/{id}         - Get a single job vacancy
 * - PUT    /api/v2/jobs/{id}         - Update a job vacancy
 * - DELETE /api/v2/jobs/{id}         - Delete a job vacancy
 * - POST   /api/v2/jobs/{id}/apply   - Apply to a job vacancy
 * - GET    /api/v2/jobs/{id}/applications - List applications (owner only)
 * - PUT    /api/v2/jobs/applications/{id} - Update application status
 * - GET    /api/v2/jobs/my-applications   - List current user's applications
 *
 * @package Nexus\Controllers\Api
 */
class JobVacanciesApiController extends BaseApiController
{
    /** Mark as v2 API for correct headers */
    protected bool $isV2Api = true;

    private function checkFeature(): void
    {
        if (!TenantContext::hasFeature('job_vacancies')) {
            $this->respondWithError('FEATURE_DISABLED', 'Job Vacancies module is not enabled for this community', null, 403);
        }
    }

    /**
     * GET /api/v2/jobs
     *
     * List job vacancies with optional filtering and cursor-based pagination.
     *
     * Query Parameters:
     * - status: string ('open', 'closed', 'filled', 'draft')
     * - type: string ('paid', 'volunteer', 'timebank')
     * - commitment: string ('full_time', 'part_time', 'flexible', 'one_off')
     * - category: string
     * - search: string (free text search)
     * - user_id: int (filter by creator)
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     */
    public function index(): void
    {
        $this->checkFeature();
        $this->rateLimit('jobs_list', 60, 60);

        // Optional auth — if logged in, enrich with has_applied
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

        $result = JobVacancyService::getAll($filters);

        // Enrich with has_applied for authenticated users
        if ($userId) {
            foreach ($result['items'] as &$vacancy) {
                if (!isset($vacancy['has_applied'])) {
                    $vacancy = JobVacancyService::getById($vacancy['id'], $userId);
                }
            }
        }

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * POST /api/v2/jobs
     *
     * Create a new job vacancy.
     */
    public function store(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('jobs_create', 5, 60);

        $data = $this->getAllInput();

        $vacancyId = JobVacancyService::create($userId, $data);

        if ($vacancyId === null) {
            $errors = JobVacancyService::getErrors();
            $this->respondWithErrors($errors, 422);
        }

        $vacancy = JobVacancyService::getById($vacancyId, $userId);

        $this->respondWithData($vacancy, null, 201);
    }

    /**
     * GET /api/v2/jobs/{id}
     *
     * Get a single job vacancy by ID. Increments view count.
     */
    public function show(int $id): void
    {
        $this->checkFeature();
        $this->rateLimit('jobs_show', 60, 60);

        $userId = $this->getOptionalUserId();

        $vacancy = JobVacancyService::getById($id, $userId);

        if (!$vacancy) {
            $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                'Job vacancy not found',
                null,
                404
            );
        }

        // Increment views (fire-and-forget)
        JobVacancyService::incrementViews($id);

        $this->respondWithData($vacancy);
    }

    /**
     * PUT /api/v2/jobs/{id}
     *
     * Update an existing job vacancy.
     */
    public function update(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('jobs_update', 10, 60);

        $data = $this->getAllInput();

        $success = JobVacancyService::update($id, $userId, $data);

        if (!$success) {
            $errors = JobVacancyService::getErrors();
            $status = 422;

            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) {
                    $status = 404;
                    break;
                }
                if ($error['code'] === ApiErrorCodes::RESOURCE_FORBIDDEN) {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $vacancy = JobVacancyService::getById($id, $userId);

        $this->respondWithData($vacancy);
    }

    /**
     * DELETE /api/v2/jobs/{id}
     *
     * Delete a job vacancy.
     */
    public function destroy(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('jobs_delete', 5, 60);

        $success = JobVacancyService::delete($id, $userId);

        if (!$success) {
            $errors = JobVacancyService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) {
                    $status = 404;
                    break;
                }
                if ($error['code'] === ApiErrorCodes::RESOURCE_FORBIDDEN) {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $this->noContent();
    }

    /**
     * POST /api/v2/jobs/{id}/apply
     *
     * Apply to a job vacancy.
     */
    public function apply(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('jobs_apply', 5, 60);

        $message = $this->input('message');

        $applicationId = JobVacancyService::apply($id, $userId, $message);

        if ($applicationId === null) {
            $errors = JobVacancyService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) {
                    $status = 404;
                    break;
                }
                if ($error['code'] === ApiErrorCodes::RESOURCE_CONFLICT) {
                    $status = 409;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        // Return the updated vacancy with application status
        $vacancy = JobVacancyService::getById($id, $userId);

        $this->respondWithData($vacancy, null, 201);
    }

    /**
     * GET /api/v2/jobs/{id}/applications
     *
     * List applications for a vacancy (owner only).
     */
    public function applications(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_applications', 30, 60);

        $applications = JobVacancyService::getApplications($id, $userId);

        if ($applications === null) {
            $errors = JobVacancyService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) {
                    $status = 404;
                    break;
                }
                if ($error['code'] === ApiErrorCodes::RESOURCE_FORBIDDEN) {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $this->respondWithData($applications);
    }

    /**
     * PUT /api/v2/jobs/applications/{id}
     *
     * Update an application status (accept/reject/review).
     */
    public function updateApplication(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('jobs_app_update', 10, 60);

        $status = $this->input('status');
        $notes = $this->input('notes');

        if (empty($status)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Status is required',
                'status',
                400
            );
        }

        $success = JobVacancyService::updateApplicationStatus($id, $userId, $status, $notes);

        if (!$success) {
            $errors = JobVacancyService::getErrors();
            $httpStatus = 400;

            foreach ($errors as $error) {
                if ($error['code'] === ApiErrorCodes::RESOURCE_NOT_FOUND) {
                    $httpStatus = 404;
                    break;
                }
                if ($error['code'] === ApiErrorCodes::RESOURCE_FORBIDDEN) {
                    $httpStatus = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $httpStatus);
        }

        $this->respondWithData(['message' => 'Application updated successfully']);
    }

    /**
     * GET /api/v2/jobs/my-applications
     *
     * List the current user's applications.
     */
    public function myApplications(): void
    {
        $this->checkFeature();
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

        $result = JobVacancyService::getMyApplications($userId, $filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }
}
