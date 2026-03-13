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
 * Provides full CRUD, applications pipeline, saved jobs, skills matching,
 * qualification assessment, alerts, renewal, analytics, and featured jobs.
 *
 * Endpoints:
 * - GET    /api/v2/jobs                         - List all job vacancies (paginated)
 * - POST   /api/v2/jobs                         - Create a new job vacancy
 * - GET    /api/v2/jobs/saved                   - List saved/bookmarked jobs
 * - GET    /api/v2/jobs/my-applications         - List current user's applications
 * - GET    /api/v2/jobs/alerts                  - List user's job alerts
 * - POST   /api/v2/jobs/alerts                  - Create a job alert
 * - DELETE /api/v2/jobs/alerts/{id}             - Delete a job alert
 * - PUT    /api/v2/jobs/alerts/{id}/unsubscribe - Deactivate alert
 * - GET    /api/v2/jobs/{id}                    - Get a single job vacancy
 * - PUT    /api/v2/jobs/{id}                    - Update a job vacancy
 * - DELETE /api/v2/jobs/{id}                    - Delete a job vacancy
 * - POST   /api/v2/jobs/{id}/apply              - Apply to a job vacancy
 * - POST   /api/v2/jobs/{id}/save               - Save/bookmark a job
 * - DELETE /api/v2/jobs/{id}/save               - Unsave a job
 * - GET    /api/v2/jobs/{id}/match              - Get match percentage for a job
 * - GET    /api/v2/jobs/{id}/qualified           - Am I Qualified assessment
 * - GET    /api/v2/jobs/{id}/applications       - List applications (owner only)
 * - GET    /api/v2/jobs/{id}/analytics          - Job analytics (owner only)
 * - POST   /api/v2/jobs/{id}/renew              - Renew a job vacancy
 * - POST   /api/v2/jobs/{id}/feature            - Feature a job (admin)
 * - DELETE /api/v2/jobs/{id}/feature            - Unfeature a job (admin)
 * - PUT    /api/v2/jobs/applications/{id}       - Update application status/stage
 * - GET    /api/v2/jobs/applications/{id}/history - Application status history
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
            return;
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
        if ($this->query('featured')) {
            $filters['featured'] = $this->query('featured') === '1' || $this->query('featured') === 'true';
        }

        $result = JobVacancyService::getAll($filters, $userId);

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
            return;
        }

        // Increment views (fire-and-forget) — J8: pass userId for analytics
        JobVacancyService::incrementViews($id, $userId);

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
            return;
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

        $this->respondWithData([
            'items'    => $result['items'],
            'cursor'   => $result['cursor'],
            'has_more' => $result['has_more'],
        ]);
    }

    // =========================================================================
    // J1: SAVED JOBS
    // =========================================================================

    /**
     * POST /api/v2/jobs/{id}/save
     *
     * Save (bookmark) a job vacancy.
     */
    public function saveJob(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('jobs_save', 30, 60);

        $success = JobVacancyService::saveJob($id, $userId);

        if (!$success) {
            $errors = JobVacancyService::getErrors();
            $this->respondWithErrors($errors, 400);
        }

        $this->respondWithData(['message' => 'Job saved successfully', 'is_saved' => true], null, 201);
    }

    /**
     * DELETE /api/v2/jobs/{id}/save
     *
     * Unsave (remove bookmark) a job vacancy.
     */
    public function unsaveJob(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('jobs_unsave', 30, 60);

        JobVacancyService::unsaveJob($id, $userId);

        $this->respondWithData(['message' => 'Job removed from saved', 'is_saved' => false]);
    }

    /**
     * GET /api/v2/jobs/saved
     *
     * List saved/bookmarked jobs.
     */
    public function savedJobs(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_saved_list', 30, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = JobVacancyService::getSavedJobs($userId, $filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    // =========================================================================
    // J2: SKILLS MATCHING
    // =========================================================================

    /**
     * GET /api/v2/jobs/{id}/match
     *
     * Get match percentage between current user and job requirements.
     */
    public function matchPercentage(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_match', 30, 60);

        $result = JobVacancyService::calculateMatchPercentage($userId, $id);

        $this->respondWithData($result);
    }

    // =========================================================================
    // J5: "AM I QUALIFIED?" TOOL
    // =========================================================================

    /**
     * GET /api/v2/jobs/{id}/qualified
     *
     * Get full qualification assessment for the current user against a job.
     */
    public function qualificationAssessment(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_qualified', 20, 60);

        $result = JobVacancyService::getQualificationAssessment($userId, $id);

        if ($result === null) {
            $errors = JobVacancyService::getErrors();
            $this->respondWithErrors($errors, 404);
        }

        $this->respondWithData($result);
    }

    // =========================================================================
    // J4: APPLICATION HISTORY
    // =========================================================================

    /**
     * GET /api/v2/jobs/applications/{id}/history
     *
     * Get status change history for an application.
     */
    public function applicationHistory(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_app_history', 30, 60);

        $history = JobVacancyService::getApplicationHistory($id, $userId);

        if ($history === null) {
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

        $this->respondWithData($history);
    }

    // =========================================================================
    // J6: JOB ALERTS
    // =========================================================================

    /**
     * GET /api/v2/jobs/alerts
     *
     * List user's job alert subscriptions.
     */
    public function listAlerts(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_alerts_list', 30, 60);

        $alerts = JobVacancyService::getAlerts($userId);

        $this->respondWithData($alerts);
    }

    /**
     * POST /api/v2/jobs/alerts
     *
     * Create a new job alert subscription.
     */
    public function createAlert(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('jobs_alerts_create', 5, 60);

        $data = $this->getAllInput();

        $alertId = JobVacancyService::subscribeAlert($userId, $data);

        if ($alertId === null) {
            $errors = JobVacancyService::getErrors();
            $this->respondWithErrors($errors, 422);
        }

        $this->respondWithData([
            'id' => $alertId,
            'message' => 'Job alert created successfully',
        ], null, 201);
    }

    /**
     * DELETE /api/v2/jobs/alerts/{id}
     *
     * Delete a job alert.
     */
    public function deleteAlert(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('jobs_alerts_delete', 10, 60);

        JobVacancyService::deleteAlert($id, $userId);

        $this->noContent();
    }

    /**
     * PUT /api/v2/jobs/alerts/{id}/unsubscribe
     *
     * Deactivate a job alert (soft unsubscribe).
     */
    public function unsubscribeAlert(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('jobs_alerts_unsub', 10, 60);

        JobVacancyService::unsubscribeAlert($id, $userId);

        $this->respondWithData(['message' => 'Alert unsubscribed successfully']);
    }

    /**
     * PUT /api/v2/jobs/alerts/{id}/resubscribe
     *
     * Reactivate a paused job alert.
     */
    public function resubscribeAlert(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('jobs_alerts_resub', 10, 60);

        JobVacancyService::resubscribeAlert($id, $userId);

        $this->respondWithData(['message' => 'Alert resubscribed successfully']);
    }

    // =========================================================================
    // J7: JOB RENEWAL
    // =========================================================================

    /**
     * POST /api/v2/jobs/{id}/renew
     *
     * Renew a job vacancy (extend deadline, reopen).
     */
    public function renewJob(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('jobs_renew', 5, 60);

        $days = $this->input('days') ?? 30;
        $days = max(1, min(90, (int)$days));

        $success = JobVacancyService::renewJob($id, $userId, $days);

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

        $vacancy = JobVacancyService::getById($id, $userId);

        $this->respondWithData($vacancy);
    }

    // =========================================================================
    // J8: JOB ANALYTICS
    // =========================================================================

    /**
     * GET /api/v2/jobs/{id}/analytics
     *
     * Get analytics for a job vacancy (owner only).
     */
    public function analytics(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_analytics', 20, 60);

        $analytics = JobVacancyService::getAnalytics($id, $userId);

        if ($analytics === null) {
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

        $this->respondWithData($analytics);
    }

    // =========================================================================
    // J10: FEATURED JOBS
    // =========================================================================

    /**
     * POST /api/v2/jobs/{id}/feature
     *
     * Feature a job vacancy (admin only).
     */
    public function featureJob(int $id): void
    {
        $this->checkFeature();
        $userId = $this->requireAdmin();
        $this->verifyCsrf();
        $this->rateLimit('jobs_feature', 10, 60);

        $days = $this->input('days') ?? 7;
        $days = max(1, min(30, (int)$days));

        $success = JobVacancyService::featureJob($id, $userId, $days);

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

        $vacancy = JobVacancyService::getById($id, $userId);

        $this->respondWithData($vacancy);
    }

    /**
     * DELETE /api/v2/jobs/{id}/feature
     *
     * Unfeature a job vacancy (admin only).
     */
    public function unfeatureJob(int $id): void
    {
        $this->checkFeature();
        $userId = $this->requireAdmin();
        $this->verifyCsrf();
        $this->rateLimit('jobs_unfeature', 10, 60);

        $success = JobVacancyService::unfeatureJob($id, $userId);

        if (!$success) {
            $errors = JobVacancyService::getErrors();
            $this->respondWithErrors($errors, 400);
        }

        $vacancy = JobVacancyService::getById($id, $userId);

        $this->respondWithData($vacancy);
    }

    /**
     * GET /api/v2/jobs/my-postings
     *
     * List all job vacancies posted by the current user (any status).
     *
     * Query Parameters:
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 50)
     */
    public function myPostings(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('jobs_my_postings', 30, 60);

        $tenantId = TenantContext::getId();

        $params = [
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        if ($this->query('cursor')) {
            $params['cursor'] = $this->query('cursor');
        }

        $result = JobVacancyService::getMyPostings($userId, $tenantId, $params);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $params['limit'],
            $result['has_more']
        );
    }
}
