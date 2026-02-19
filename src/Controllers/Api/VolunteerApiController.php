<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\VolunteerService;
use Nexus\Core\TenantContext;

/**
 * VolunteerApiController - RESTful API for volunteering module
 *
 * Provides volunteering management endpoints with standardized response format.
 *
 * OPPORTUNITIES:
 * - GET    /api/v2/volunteering/opportunities                     - List opportunities (cursor paginated)
 * - GET    /api/v2/volunteering/opportunities/{id}                - Get opportunity details
 * - POST   /api/v2/volunteering/opportunities                     - Create opportunity (org admin)
 * - PUT    /api/v2/volunteering/opportunities/{id}                - Update opportunity (org admin)
 * - DELETE /api/v2/volunteering/opportunities/{id}                - Delete opportunity (org admin)
 *
 * APPLICATIONS:
 * - POST   /api/v2/volunteering/opportunities/{id}/apply          - Apply to volunteer
 * - DELETE /api/v2/volunteering/applications/{id}                 - Withdraw application
 * - GET    /api/v2/volunteering/applications                      - My applications
 * - GET    /api/v2/volunteering/opportunities/{id}/applications   - Applications for opportunity (admin)
 * - PUT    /api/v2/volunteering/applications/{id}                 - Accept/reject application (admin)
 *
 * SHIFTS:
 * - GET    /api/v2/volunteering/opportunities/{id}/shifts         - List shifts for opportunity
 * - GET    /api/v2/volunteering/shifts                            - My shifts
 * - POST   /api/v2/volunteering/shifts/{id}/signup                - Sign up for shift
 * - DELETE /api/v2/volunteering/shifts/{id}/signup                - Cancel shift signup
 *
 * HOURS:
 * - POST   /api/v2/volunteering/hours                             - Log hours
 * - GET    /api/v2/volunteering/hours                             - My logged hours
 * - GET    /api/v2/volunteering/hours/summary                     - Hours summary/stats
 * - PUT    /api/v2/volunteering/hours/{id}/verify                 - Verify hours (admin)
 *
 * ORGANIZATIONS:
 * - GET    /api/v2/volunteering/organisations                     - List organisations
 * - GET    /api/v2/volunteering/organisations/{id}                - Organisation details
 *
 * REVIEWS:
 * - POST   /api/v2/volunteering/reviews                           - Create volunteering review
 * - GET    /api/v2/volunteering/reviews/{type}/{id}               - Get reviews for target
 *
 * Response Format:
 * Success: { "data": {...}, "meta": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "...", "field": "..." }] }
 */
class VolunteerApiController extends BaseApiController
{
    /**
     * Check if volunteering feature is enabled
     */
    private function checkFeature(): void
    {
        if (!TenantContext::hasFeature('volunteering')) {
            $this->respondWithError('FEATURE_DISABLED', 'Volunteering module is not enabled for this community', null, 403);
        }
    }

    // ========================================
    // OPPORTUNITIES
    // ========================================

    /**
     * GET /api/v2/volunteering/opportunities
     *
     * List opportunities with optional filtering and cursor-based pagination.
     *
     * Query Parameters:
     * - organization_id: int (filter by organization)
     * - category_id: int (filter by category)
     * - search: string (search in title, description, org name)
     * - is_remote: bool (filter remote opportunities)
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 50)
     */
    public function opportunities(): void
    {
        $this->checkFeature();
        $this->getUserIdOptional();
        $this->rateLimit('volunteering_list', 60, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        if ($this->query('organization_id')) {
            $filters['organization_id'] = (int)$this->query('organization_id');
        }

        if ($this->query('category_id')) {
            $filters['category_id'] = (int)$this->query('category_id');
        }

        if ($this->query('search')) {
            $filters['search'] = $this->query('search');
        }

        if ($this->query('is_remote') === 'true' || $this->query('is_remote') === '1') {
            $filters['is_remote'] = true;
        }

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = VolunteerService::getOpportunities($filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/volunteering/opportunities/{id}
     *
     * Get opportunity details with shifts and application status.
     */
    public function showOpportunity(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserIdOptional();
        $this->rateLimit('volunteering_show', 120, 60);

        $opportunity = VolunteerService::getOpportunityById($id, $userId);

        if (!$opportunity) {
            $this->respondWithError('NOT_FOUND', 'Opportunity not found', null, 404);
            return;
        }

        $this->respondWithData($opportunity);
    }

    /**
     * POST /api/v2/volunteering/opportunities
     *
     * Create a new opportunity (requires org admin).
     *
     * Request Body (JSON):
     * {
     *   "organization_id": int (required),
     *   "title": "string" (required),
     *   "description": "string",
     *   "location": "string",
     *   "skills_needed": "string",
     *   "start_date": "YYYY-MM-DD",
     *   "end_date": "YYYY-MM-DD",
     *   "category_id": int
     * }
     */
    public function createOpportunity(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_create', 10, 60);

        $data = [
            'organization_id' => $this->inputInt('organization_id'),
            'title' => trim($this->input('title', '')),
            'description' => trim($this->input('description', '')),
            'location' => trim($this->input('location', '')),
            'skills_needed' => trim($this->input('skills_needed', '')),
            'start_date' => $this->input('start_date'),
            'end_date' => $this->input('end_date'),
            'category_id' => $this->inputInt('category_id') ?: null,
        ];

        $oppId = VolunteerService::createOpportunity($userId, $data);

        if ($oppId === null) {
            $errors = VolunteerService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $opportunity = VolunteerService::getOpportunityById($oppId, $userId);
        $this->respondWithData($opportunity, null, 201);
    }

    /**
     * PUT /api/v2/volunteering/opportunities/{id}
     *
     * Update an opportunity (requires org admin).
     */
    public function updateOpportunity(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_update', 20, 60);

        $data = [];

        if ($this->input('title') !== null) {
            $data['title'] = trim($this->input('title'));
        }
        if ($this->input('description') !== null) {
            $data['description'] = trim($this->input('description'));
        }
        if ($this->input('location') !== null) {
            $data['location'] = trim($this->input('location'));
        }
        if ($this->input('skills_needed') !== null) {
            $data['skills_needed'] = trim($this->input('skills_needed'));
        }
        if ($this->input('start_date') !== null) {
            $data['start_date'] = $this->input('start_date');
        }
        if ($this->input('end_date') !== null) {
            $data['end_date'] = $this->input('end_date');
        }
        if ($this->input('category_id') !== null) {
            $data['category_id'] = $this->inputInt('category_id') ?: null;
        }

        $success = VolunteerService::updateOpportunity($id, $userId, $data);

        if (!$success) {
            $errors = VolunteerService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $opportunity = VolunteerService::getOpportunityById($id, $userId);
        $this->respondWithData($opportunity);
    }

    /**
     * DELETE /api/v2/volunteering/opportunities/{id}
     *
     * Delete (deactivate) an opportunity (requires org admin).
     */
    public function deleteOpportunity(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_delete', 10, 60);

        $success = VolunteerService::deleteOpportunity($id, $userId);

        if (!$success) {
            $errors = VolunteerService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->noContent();
    }

    // ========================================
    // APPLICATIONS
    // ========================================

    /**
     * POST /api/v2/volunteering/opportunities/{id}/apply
     *
     * Apply to volunteer for an opportunity.
     *
     * Request Body (JSON):
     * {
     *   "message": "string" (optional cover message),
     *   "shift_id": int (optional - apply to specific shift)
     * }
     */
    public function apply(int $opportunityId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_apply', 20, 60);

        $data = [
            'message' => trim($this->input('message', '')),
            'shift_id' => $this->inputInt('shift_id') ?: null,
        ];

        $appId = VolunteerService::apply($userId, $opportunityId, $data);

        if ($appId === null) {
            $errors = VolunteerService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData([
            'id' => $appId,
            'status' => 'pending',
            'message' => 'Application submitted successfully',
        ], null, 201);
    }

    /**
     * DELETE /api/v2/volunteering/applications/{id}
     *
     * Withdraw an application.
     */
    public function withdrawApplication(int $applicationId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_withdraw', 20, 60);

        $success = VolunteerService::withdrawApplication($applicationId, $userId);

        if (!$success) {
            $errors = VolunteerService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->noContent();
    }

    /**
     * GET /api/v2/volunteering/applications
     *
     * Get my applications with cursor pagination.
     *
     * Query Parameters:
     * - status: string (pending, approved, declined)
     * - cursor: string
     * - per_page: int (default 20, max 50)
     */
    public function myApplications(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_my_apps', 60, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        if ($this->query('status')) {
            $filters['status'] = $this->query('status');
        }

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = VolunteerService::getMyApplications($userId, $filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/volunteering/opportunities/{id}/applications
     *
     * Get applications for an opportunity (org admin only).
     */
    public function opportunityApplications(int $opportunityId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_opp_apps', 60, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        if ($this->query('status')) {
            $filters['status'] = $this->query('status');
        }

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = VolunteerService::getApplicationsForOpportunity($opportunityId, $userId, $filters);

        if ($result === null) {
            $errors = VolunteerService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * PUT /api/v2/volunteering/applications/{id}
     *
     * Handle application (accept/reject) - org admin only.
     *
     * Request Body (JSON):
     * {
     *   "action": "approve" | "decline"
     * }
     */
    public function handleApplication(int $applicationId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_handle_app', 30, 60);

        $action = $this->input('action');

        if (!$action) {
            $this->respondWithError('VALIDATION_ERROR', 'Action is required (approve or decline)', 'action', 400);
            return;
        }

        $success = VolunteerService::handleApplication($applicationId, $userId, $action);

        if (!$success) {
            $errors = VolunteerService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData([
            'id' => $applicationId,
            'status' => $action === 'approve' ? 'approved' : 'declined',
        ]);
    }

    // ========================================
    // SHIFTS
    // ========================================

    /**
     * GET /api/v2/volunteering/opportunities/{id}/shifts
     *
     * Get shifts for an opportunity.
     */
    public function shifts(int $opportunityId): void
    {
        $this->checkFeature();
        $this->getUserIdOptional();
        $this->rateLimit('volunteering_shifts', 120, 60);

        $shifts = VolunteerService::getShiftsForOpportunity($opportunityId);

        $this->respondWithData(['shifts' => $shifts]);
    }

    /**
     * GET /api/v2/volunteering/shifts
     *
     * Get my shifts with cursor pagination.
     *
     * Query Parameters:
     * - upcoming_only: bool
     * - cursor: string
     * - per_page: int (default 20, max 50)
     */
    public function myShifts(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_my_shifts', 60, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        if ($this->query('upcoming_only') === 'true' || $this->query('upcoming_only') === '1') {
            $filters['upcoming_only'] = true;
        }

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = VolunteerService::getMyShifts($userId, $filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * POST /api/v2/volunteering/shifts/{id}/signup
     *
     * Sign up for a shift (requires approved application).
     */
    public function signUp(int $shiftId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_shift_signup', 20, 60);

        $success = VolunteerService::signUpForShift($shiftId, $userId);

        if (!$success) {
            $errors = VolunteerService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData([
            'shift_id' => $shiftId,
            'message' => 'Successfully signed up for shift',
        ]);
    }

    /**
     * DELETE /api/v2/volunteering/shifts/{id}/signup
     *
     * Cancel shift signup.
     */
    public function cancelSignup(int $shiftId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_shift_cancel', 20, 60);

        $success = VolunteerService::cancelShiftSignup($shiftId, $userId);

        if (!$success) {
            $errors = VolunteerService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->noContent();
    }

    // ========================================
    // HOURS
    // ========================================

    /**
     * POST /api/v2/volunteering/hours
     *
     * Log volunteering hours.
     *
     * Request Body (JSON):
     * {
     *   "organization_id": int (required),
     *   "opportunity_id": int (optional),
     *   "date": "YYYY-MM-DD" (required),
     *   "hours": float (required),
     *   "description": "string"
     * }
     */
    public function logHours(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_log_hours', 20, 60);

        $data = [
            'organization_id' => $this->inputInt('organization_id'),
            'opportunity_id' => $this->inputInt('opportunity_id') ?: null,
            'date' => $this->input('date'),
            'hours' => (float)$this->input('hours'),
            'description' => trim($this->input('description', '')),
        ];

        $logId = VolunteerService::logHours($userId, $data);

        if ($logId === null) {
            $errors = VolunteerService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData([
            'id' => $logId,
            'status' => 'pending',
            'message' => 'Hours logged successfully, pending verification',
        ], null, 201);
    }

    /**
     * GET /api/v2/volunteering/hours
     *
     * Get my logged hours with cursor pagination.
     *
     * Query Parameters:
     * - status: string (pending, approved, declined)
     * - cursor: string
     * - per_page: int (default 20, max 50)
     */
    public function myHours(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_my_hours', 60, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        if ($this->query('status')) {
            $filters['status'] = $this->query('status');
        }

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = VolunteerService::getMyHours($userId, $filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/volunteering/hours/summary
     *
     * Get hours summary/stats for the current user.
     */
    public function hoursSummary(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_hours_summary', 60, 60);

        $summary = VolunteerService::getHoursSummary($userId);

        $this->respondWithData($summary);
    }

    /**
     * PUT /api/v2/volunteering/hours/{id}/verify
     *
     * Verify hours (org admin only).
     *
     * Request Body (JSON):
     * {
     *   "action": "approve" | "decline"
     * }
     */
    public function verifyHours(int $logId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_verify_hours', 30, 60);

        $action = $this->input('action');

        if (!$action) {
            $this->respondWithError('VALIDATION_ERROR', 'Action is required (approve or decline)', 'action', 400);
            return;
        }

        $success = VolunteerService::verifyHours($logId, $userId, $action);

        if (!$success) {
            $errors = VolunteerService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData([
            'id' => $logId,
            'status' => $action === 'approve' ? 'approved' : 'declined',
        ]);
    }

    // ========================================
    // ORGANISATIONS
    // ========================================

    /**
     * GET /api/v2/volunteering/organisations
     *
     * List volunteer organisations with cursor pagination.
     *
     * Query Parameters:
     * - search: string
     * - cursor: string
     * - per_page: int (default 20, max 50)
     */
    public function organisations(): void
    {
        $this->checkFeature();
        $this->getUserIdOptional();
        $this->rateLimit('volunteering_orgs', 60, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        if ($this->query('search')) {
            $filters['search'] = $this->query('search');
        }

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = VolunteerService::getOrganizations($filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/volunteering/organisations/{id}
     *
     * Get organisation details with stats.
     */
    public function showOrganisation(int $id): void
    {
        $this->checkFeature();
        $this->getUserIdOptional();
        $this->rateLimit('volunteering_org_show', 120, 60);

        $org = VolunteerService::getOrganizationById($id);

        if (!$org) {
            $this->respondWithError('NOT_FOUND', 'Organisation not found', null, 404);
            return;
        }

        $this->respondWithData($org);
    }

    // ========================================
    // REVIEWS
    // ========================================

    /**
     * POST /api/v2/volunteering/reviews
     *
     * Create a volunteering review.
     *
     * Request Body (JSON):
     * {
     *   "target_type": "organization" | "user" (required),
     *   "target_id": int (required),
     *   "rating": int 1-5 (required),
     *   "comment": "string"
     * }
     */
    public function createReview(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_review', 10, 60);

        $targetType = $this->input('target_type');
        $targetId = $this->inputInt('target_id');
        $rating = $this->inputInt('rating');
        $comment = trim($this->input('comment', ''));

        if (!$targetType) {
            $this->respondWithError('VALIDATION_ERROR', 'Target type is required', 'target_type', 400);
            return;
        }

        if (!$targetId) {
            $this->respondWithError('VALIDATION_ERROR', 'Target ID is required', 'target_id', 400);
            return;
        }

        if (!$rating) {
            $this->respondWithError('VALIDATION_ERROR', 'Rating is required', 'rating', 400);
            return;
        }

        $reviewId = VolunteerService::createReview($userId, $targetType, $targetId, $rating, $comment);

        if ($reviewId === null) {
            $errors = VolunteerService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData([
            'id' => $reviewId,
            'rating' => $rating,
            'message' => 'Review submitted successfully',
        ], null, 201);
    }

    /**
     * GET /api/v2/volunteering/reviews/{type}/{id}
     *
     * Get reviews for a target (organization or user).
     */
    public function getReviews(string $type, int $id): void
    {
        $this->checkFeature();
        $this->getUserIdOptional();
        $this->rateLimit('volunteering_reviews', 60, 60);

        if (!in_array($type, ['organization', 'user'])) {
            $this->respondWithError('VALIDATION_ERROR', 'Type must be organization or user', 'type', 400);
            return;
        }

        $reviews = VolunteerService::getReviews($type, $id);

        $this->respondWithData(['reviews' => $reviews]);
    }

    // ========================================
    // HELPERS
    // ========================================

    /**
     * Get user ID optionally (some endpoints work for anonymous users)
     */
    private function getUserIdOptional(): ?int
    {
        try {
            return $this->getUserId();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Determine HTTP status from error codes
     */
    private function getErrorStatus(array $errors): int
    {
        foreach ($errors as $error) {
            if ($error['code'] === 'NOT_FOUND') {
                return 404;
            }
            if ($error['code'] === 'FORBIDDEN') {
                return 403;
            }
            if ($error['code'] === 'ALREADY_EXISTS') {
                return 409;
            }
            if ($error['code'] === 'FEATURE_DISABLED') {
                return 403;
            }
        }
        return 400;
    }
}
