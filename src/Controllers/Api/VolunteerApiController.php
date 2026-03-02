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

    /**
     * POST /api/v2/volunteering/organisations
     *
     * Register a new volunteer organisation (pending admin approval).
     *
     * Request Body (JSON):
     * {
     *   "name": "string" (required, min 3 chars),
     *   "description": "string" (required, min 20 chars),
     *   "contact_email": "string" (required, valid email),
     *   "website": "string" (optional, valid URL)
     * }
     */
    public function createOrganisation(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_org_create', 5, 60);

        $data = [
            'name' => trim($this->input('name', '')),
            'description' => trim($this->input('description', '')),
            'contact_email' => trim($this->input('contact_email', '')),
            'website' => trim($this->input('website', '')),
        ];

        $orgId = VolunteerService::createOrganization($userId, $data);

        if ($orgId === null) {
            $errors = VolunteerService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $org = VolunteerService::getOrganizationById($orgId);
        $this->respondWithData($org, null, 201);
    }

    /**
     * GET /api/v2/volunteering/my-organisations
     *
     * Get organisations the current user owns or is a member of.
     */
    public function myOrganisations(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_my_orgs', 60, 60);

        $orgs = VolunteerService::getMyOrganizations($userId);
        $this->respondWithData($orgs);
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
    // WAITLIST (V1)
    // ========================================

    /**
     * POST /api/v2/volunteering/shifts/{id}/waitlist
     * Join the waitlist for a full shift
     */
    public function joinWaitlist(int $shiftId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_waitlist_join', 20, 60);

        $entryId = \Nexus\Services\ShiftWaitlistService::join($shiftId, $userId);

        if ($entryId === null) {
            $errors = \Nexus\Services\ShiftWaitlistService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $position = \Nexus\Services\ShiftWaitlistService::getUserPosition($shiftId, $userId);

        $this->respondWithData([
            'id' => $entryId,
            'position' => $position['position'] ?? 1,
            'message' => 'Successfully joined the waitlist',
        ], null, 201);
    }

    /**
     * DELETE /api/v2/volunteering/shifts/{id}/waitlist
     * Leave the waitlist for a shift
     */
    public function leaveWaitlist(int $shiftId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_waitlist_leave', 20, 60);

        $success = \Nexus\Services\ShiftWaitlistService::leave($shiftId, $userId);

        if (!$success) {
            $errors = \Nexus\Services\ShiftWaitlistService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->noContent();
    }

    /**
     * POST /api/v2/volunteering/shifts/{id}/waitlist/promote
     * Accept a waitlist promotion (claim the spot)
     */
    public function promoteFromWaitlist(int $shiftId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_waitlist_promote', 10, 60);

        $success = \Nexus\Services\ShiftWaitlistService::promoteUser($shiftId, $userId);

        if (!$success) {
            $errors = \Nexus\Services\ShiftWaitlistService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData(['message' => 'Successfully claimed the shift spot']);
    }

    // ========================================
    // SHIFT SWAPPING (V2)
    // ========================================

    /**
     * POST /api/v2/volunteering/swaps
     * Request a shift swap
     */
    public function requestSwap(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_swap_request', 10, 60);

        $data = [
            'from_shift_id' => $this->inputInt('from_shift_id'),
            'to_shift_id' => $this->inputInt('to_shift_id'),
            'to_user_id' => $this->inputInt('to_user_id'),
            'message' => trim($this->input('message', '')),
        ];

        $swapId = \Nexus\Services\ShiftSwapService::requestSwap($userId, $data);

        if ($swapId === null) {
            $errors = \Nexus\Services\ShiftSwapService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData(['id' => $swapId, 'message' => 'Swap request sent'], null, 201);
    }

    /**
     * GET /api/v2/volunteering/swaps
     * Get swap requests for the current user
     */
    public function getSwapRequests(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_swaps_list', 60, 60);

        $direction = $this->query('direction') ?? 'all';
        $requests = \Nexus\Services\ShiftSwapService::getSwapRequests($userId, $direction);

        $this->respondWithData(['swaps' => $requests]);
    }

    /**
     * PUT /api/v2/volunteering/swaps/{id}
     * Respond to a swap request (accept/reject)
     */
    public function respondToSwap(int $swapId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_swap_respond', 20, 60);

        $action = $this->input('action');
        if (!$action || !in_array($action, ['accept', 'reject'])) {
            $this->respondWithError('VALIDATION_ERROR', 'Action must be accept or reject', 'action', 400);
            return;
        }

        $success = \Nexus\Services\ShiftSwapService::respond($swapId, $userId, $action);

        if (!$success) {
            $errors = \Nexus\Services\ShiftSwapService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData(['id' => $swapId, 'status' => $action === 'accept' ? 'accepted' : 'rejected']);
    }

    /**
     * DELETE /api/v2/volunteering/swaps/{id}
     * Cancel a pending swap request
     */
    public function cancelSwap(int $swapId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_swap_cancel', 20, 60);

        $success = \Nexus\Services\ShiftSwapService::cancel($swapId, $userId);

        if (!$success) {
            $errors = \Nexus\Services\ShiftSwapService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->noContent();
    }

    // ========================================
    // GROUP RESERVATIONS (V3)
    // ========================================

    /**
     * POST /api/v2/volunteering/shifts/{id}/group-reserve
     * Reserve shift slots for a group
     */
    public function groupReserve(int $shiftId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_group_reserve', 10, 60);

        $groupId = $this->inputInt('group_id');
        $slots = $this->inputInt('reserved_slots') ?: 1;
        $notes = trim($this->input('notes', ''));

        if (!$groupId) {
            $this->respondWithError('VALIDATION_ERROR', 'Group ID is required', 'group_id', 400);
            return;
        }

        $reservationId = \Nexus\Services\ShiftGroupReservationService::reserve($shiftId, $groupId, $userId, $slots, $notes ?: null);

        if ($reservationId === null) {
            $errors = \Nexus\Services\ShiftGroupReservationService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData(['id' => $reservationId, 'message' => "Reserved {$slots} slots"], null, 201);
    }

    /**
     * POST /api/v2/volunteering/group-reservations/{id}/members
     * Add a member to a group reservation
     */
    public function addGroupMember(int $reservationId): void
    {
        $this->checkFeature();
        $leaderId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_group_member', 20, 60);

        $userId = $this->inputInt('user_id');
        if (!$userId) {
            $this->respondWithError('VALIDATION_ERROR', 'User ID is required', 'user_id', 400);
            return;
        }

        $success = \Nexus\Services\ShiftGroupReservationService::addMember($reservationId, $userId, $leaderId);

        if (!$success) {
            $errors = \Nexus\Services\ShiftGroupReservationService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData(['message' => 'Member added to group reservation']);
    }

    /**
     * DELETE /api/v2/volunteering/group-reservations/{id}/members/{userId}
     * Remove a member from a group reservation
     */
    public function removeGroupMember(int $reservationId, int $userId): void
    {
        $this->checkFeature();
        $leaderId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_group_member_remove', 20, 60);

        $success = \Nexus\Services\ShiftGroupReservationService::removeMember($reservationId, $userId, $leaderId);

        if (!$success) {
            $errors = \Nexus\Services\ShiftGroupReservationService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->noContent();
    }

    /**
     * DELETE /api/v2/volunteering/group-reservations/{id}
     * Cancel a group reservation
     */
    public function cancelGroupReservation(int $reservationId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_group_cancel', 10, 60);

        $success = \Nexus\Services\ShiftGroupReservationService::cancelReservation($reservationId, $userId);

        if (!$success) {
            $errors = \Nexus\Services\ShiftGroupReservationService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->noContent();
    }

    // ========================================
    // SKILLS MATCHING (V4)
    // ========================================

    /**
     * GET /api/v2/volunteering/recommended-shifts
     * Get recommended shifts based on user skills
     */
    public function recommendedShifts(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_recommended', 30, 60);

        $limit = $this->queryInt('limit', 10, 1, 20);
        $minScore = $this->queryInt('min_score', 20, 0, 100);

        $shifts = \Nexus\Services\VolunteerMatchingService::getRecommendedShifts($userId, [
            'limit' => $limit,
            'min_match_score' => $minScore,
        ]);

        $this->respondWithData(['shifts' => $shifts]);
    }

    // ========================================
    // CERTIFICATES (V6)
    // ========================================

    /**
     * POST /api/v2/volunteering/certificates
     * Generate a volunteer impact certificate
     */
    public function generateCertificate(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_certificate', 5, 60);

        $options = [
            'start_date' => $this->input('start_date') ?: date('Y-01-01'),
            'end_date' => $this->input('end_date') ?: date('Y-m-d'),
        ];

        if ($this->inputInt('organization_id')) {
            $options['organization_id'] = $this->inputInt('organization_id');
        }

        $cert = \Nexus\Services\VolunteerCertificateService::generate($userId, $options);

        if ($cert === null) {
            $errors = \Nexus\Services\VolunteerCertificateService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData($cert, null, 201);
    }

    /**
     * GET /api/v2/volunteering/certificates
     * Get user's certificates
     */
    public function myCertificates(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_certificates_list', 30, 60);

        $certs = \Nexus\Services\VolunteerCertificateService::getUserCertificates($userId);

        $this->respondWithData(['certificates' => $certs]);
    }

    /**
     * GET /api/v2/volunteering/certificates/verify/{code}
     * Verify a certificate (public endpoint)
     */
    public function verifyCertificate(string $code): void
    {
        $this->rateLimit('volunteering_cert_verify', 60, 60);

        $cert = \Nexus\Services\VolunteerCertificateService::verify($code);

        if ($cert === null) {
            $this->respondWithError('NOT_FOUND', 'Certificate not found or invalid', null, 404);
            return;
        }

        $this->respondWithData($cert);
    }

    /**
     * GET /api/v2/volunteering/certificates/{code}/html
     * Get certificate HTML for printing/PDF
     */
    public function certificateHtml(string $code): void
    {
        $this->rateLimit('volunteering_cert_html', 10, 60);

        $html = \Nexus\Services\VolunteerCertificateService::generateHtml($code);

        if ($html === null) {
            $this->respondWithError('NOT_FOUND', 'Certificate not found', null, 404);
            return;
        }

        \Nexus\Services\VolunteerCertificateService::markDownloaded($code);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    // ========================================
    // QR CHECK-IN (V7)
    // ========================================

    /**
     * GET /api/v2/volunteering/shifts/{id}/checkin
     * Get QR check-in info for a shift (for the current user)
     */
    public function getCheckIn(int $shiftId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_checkin_get', 60, 60);

        $checkin = \Nexus\Services\VolunteerCheckInService::getUserCheckIn($shiftId, $userId);

        if (!$checkin) {
            // Try generating a token
            $token = \Nexus\Services\VolunteerCheckInService::generateToken($shiftId, $userId);
            if ($token) {
                $checkin = \Nexus\Services\VolunteerCheckInService::getUserCheckIn($shiftId, $userId);
            }
        }

        if (!$checkin) {
            $this->respondWithError('NOT_FOUND', 'No check-in available for this shift', null, 404);
            return;
        }

        $this->respondWithData($checkin);
    }

    /**
     * POST /api/v2/volunteering/checkin/verify/{token}
     * Verify QR check-in (scan QR code)
     */
    public function verifyCheckIn(string $token): void
    {
        $this->rateLimit('volunteering_checkin_verify', 30, 60);

        $result = \Nexus\Services\VolunteerCheckInService::verifyCheckIn($token);

        if ($result === null) {
            $errors = \Nexus\Services\VolunteerCheckInService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData($result);
    }

    /**
     * POST /api/v2/volunteering/checkin/checkout/{token}
     * Check out a volunteer
     */
    public function checkOut(string $token): void
    {
        $this->rateLimit('volunteering_checkout', 30, 60);

        $success = \Nexus\Services\VolunteerCheckInService::checkOut($token);

        if (!$success) {
            $errors = \Nexus\Services\VolunteerCheckInService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData(['message' => 'Successfully checked out']);
    }

    /**
     * GET /api/v2/volunteering/shifts/{id}/checkins
     * Get all check-ins for a shift (coordinator view)
     */
    public function shiftCheckIns(int $shiftId): void
    {
        $this->checkFeature();
        $this->getUserId();
        $this->rateLimit('volunteering_shift_checkins', 60, 60);

        $checkins = \Nexus\Services\VolunteerCheckInService::getShiftCheckIns($shiftId);

        $this->respondWithData(['checkins' => $checkins]);
    }

    // ========================================
    // EMERGENCY ALERTS (V9)
    // ========================================

    /**
     * POST /api/v2/volunteering/emergency-alerts
     * Create an emergency volunteer alert
     */
    public function createEmergencyAlert(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_emergency_create', 5, 60);

        $data = [
            'shift_id' => $this->inputInt('shift_id'),
            'message' => trim($this->input('message', '')),
            'priority' => $this->input('priority', 'urgent'),
            'required_skills' => $this->input('required_skills'),
            'expires_hours' => $this->inputInt('expires_hours') ?: 24,
        ];

        $alertId = \Nexus\Services\VolunteerEmergencyAlertService::createAlert($userId, $data);

        if ($alertId === null) {
            $errors = \Nexus\Services\VolunteerEmergencyAlertService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData(['id' => $alertId, 'message' => 'Emergency alert sent'], null, 201);
    }

    /**
     * GET /api/v2/volunteering/emergency-alerts
     * Get emergency alerts for the current user
     */
    public function myEmergencyAlerts(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_emergency_list', 60, 60);

        $alerts = \Nexus\Services\VolunteerEmergencyAlertService::getUserAlerts($userId);

        $this->respondWithData(['alerts' => $alerts]);
    }

    /**
     * PUT /api/v2/volunteering/emergency-alerts/{id}
     * Respond to an emergency alert (accept/decline)
     */
    public function respondToEmergencyAlert(int $alertId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_emergency_respond', 10, 60);

        $response = $this->input('response');
        if (!$response || !in_array($response, ['accepted', 'declined'])) {
            $this->respondWithError('VALIDATION_ERROR', 'Response must be accepted or declined', 'response', 400);
            return;
        }

        $success = \Nexus\Services\VolunteerEmergencyAlertService::respond($alertId, $userId, $response);

        if (!$success) {
            $errors = \Nexus\Services\VolunteerEmergencyAlertService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData(['id' => $alertId, 'response' => $response]);
    }

    /**
     * DELETE /api/v2/volunteering/emergency-alerts/{id}
     * Cancel an emergency alert
     */
    public function cancelEmergencyAlert(int $alertId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_emergency_cancel', 10, 60);

        $success = \Nexus\Services\VolunteerEmergencyAlertService::cancelAlert($alertId, $userId);

        if (!$success) {
            $errors = \Nexus\Services\VolunteerEmergencyAlertService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->noContent();
    }

    // ========================================
    // WELLBEING / BURNOUT DETECTION (V10)
    // ========================================

    /**
     * GET /api/v2/volunteering/wellbeing/my-status
     * Get burnout risk assessment for current user
     */
    public function myWellbeingStatus(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_wellbeing_status', 10, 60);

        $assessment = \Nexus\Services\VolunteerWellbeingService::detectBurnoutRisk($userId);

        $this->respondWithData($assessment);
    }

    // ========================================
    // RECURRING SHIFTS (V8)
    // ========================================

    /**
     * GET /api/v2/volunteering/opportunities/{id}/recurring-patterns
     * Get recurring shift patterns for an opportunity
     */
    public function recurringPatterns(int $opportunityId): void
    {
        $this->checkFeature();
        $this->getUserId();
        $this->rateLimit('volunteering_recurring_list', 60, 60);

        $patterns = \Nexus\Services\RecurringShiftService::getPatternsForOpportunity($opportunityId);

        $this->respondWithData(['patterns' => $patterns]);
    }

    /**
     * POST /api/v2/volunteering/opportunities/{id}/recurring-patterns
     * Create a recurring shift pattern
     */
    public function createRecurringPattern(int $opportunityId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_recurring_create', 10, 60);

        $data = [
            'title' => $this->input('title'),
            'frequency' => $this->input('frequency'),
            'days_of_week' => $this->input('days_of_week'),
            'start_time' => $this->input('start_time'),
            'end_time' => $this->input('end_time'),
            'capacity' => $this->inputInt('capacity', 1),
            'start_date' => $this->input('start_date'),
            'end_date' => $this->input('end_date'),
            'max_occurrences' => $this->input('max_occurrences'),
        ];

        $patternId = \Nexus\Services\RecurringShiftService::createPattern($opportunityId, $userId, $data);

        if ($patternId === null) {
            $errors = \Nexus\Services\RecurringShiftService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $pattern = \Nexus\Services\RecurringShiftService::getPattern($patternId);
        $this->respondWithData($pattern, null, 201);
    }

    /**
     * PUT /api/v2/volunteering/recurring-patterns/{id}
     * Update a recurring shift pattern
     */
    public function updateRecurringPattern(int $patternId): void
    {
        $this->checkFeature();
        $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_recurring_update', 10, 60);

        $data = $this->getAllInput();

        $success = \Nexus\Services\RecurringShiftService::updatePattern($patternId, $data);

        if (!$success) {
            $errors = \Nexus\Services\RecurringShiftService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $pattern = \Nexus\Services\RecurringShiftService::getPattern($patternId);
        $this->respondWithData($pattern);
    }

    /**
     * DELETE /api/v2/volunteering/recurring-patterns/{id}
     * Deactivate a recurring shift pattern and remove future unbooked shifts
     */
    public function deleteRecurringPattern(int $patternId): void
    {
        $this->checkFeature();
        $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_recurring_delete', 10, 60);

        $deactivated = \Nexus\Services\RecurringShiftService::deactivatePattern($patternId);

        if (!$deactivated) {
            $errors = \Nexus\Services\RecurringShiftService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $deleted = \Nexus\Services\RecurringShiftService::deleteFutureShifts($patternId);

        $this->respondWithData([
            'message' => 'Recurring pattern deactivated',
            'future_shifts_removed' => $deleted,
        ]);
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
