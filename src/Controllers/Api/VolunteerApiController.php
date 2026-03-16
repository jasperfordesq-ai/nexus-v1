<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\VolunteerService;
use Nexus\Services\WebhookDispatchService;
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
            return;
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

        $this->respondWithData([
            'items'    => $result['items'],
            'cursor'   => $result['cursor'],
            'has_more' => $result['has_more'],
        ]);
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
        $orgNote = trim((string)($this->input('org_note') ?? ''));

        if (!$action) {
            $this->respondWithError('VALIDATION_ERROR', 'Action is required (approve or decline)', 'action', 400);
            return;
        }

        $success = VolunteerService::handleApplication($applicationId, $userId, $action, $orgNote);

        if (!$success) {
            $errors = VolunteerService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
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
        }

        $this->respondWithData([
            'id' => $logId,
            'status' => $action === 'approve' ? 'approved' : 'declined',
        ]);
    }

    /**
     * GET /api/v2/volunteering/hours/pending-review
     *
     * Get hours pending approval for organisations owned by the current user.
     */
    public function pendingHoursReview(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_pending_hours', 60, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = VolunteerService::getPendingHoursForOrgOwner($userId, $filters);
        $this->respondWithData([
            'items'    => $result['items'],
            'cursor'   => $result['cursor'],
            'has_more' => $result['has_more'],
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

        $org = VolunteerService::getOrganizationById($orgId, true);
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

    /**
     * GET /api/v2/volunteering/my-waitlists
     * List all shift waitlist entries for the current user
     */
    public function myWaitlists(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_waitlists_list', 60, 60);

        $entries = \Nexus\Services\ShiftWaitlistService::getUserWaitlists($userId);

        $this->respondWithData($entries);
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

    /**
     * GET /api/v2/volunteering/group-reservations
     * List all group reservations for the current user
     */
    public function myGroupReservations(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_group_reservations_list', 60, 60);

        $reservations = \Nexus\Services\ShiftGroupReservationService::getUserReservations($userId);

        $this->respondWithData($reservations);
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

        $options = [];

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
        if (!defined('TESTING')) { exit; }
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
            } else {
                $errors = \Nexus\Services\VolunteerCheckInService::getErrors();
                if (!empty($errors)) {
                    $status = $this->getErrorStatus($errors);
                    $this->respondWithErrors($errors, $status);
                    return;
                }
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
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_checkin_verify', 30, 60);

        $shiftId = \Nexus\Services\VolunteerCheckInService::getShiftIdByToken($token);
        if ($shiftId === null) {
            $this->respondWithError('NOT_FOUND', 'Invalid check-in code', null, 404);
            return;
        }

        if (!$this->canManageShift($shiftId, $userId)) {
            $this->respondWithError('FORBIDDEN', 'You do not have permission to verify check-ins for this shift', null, 403);
            return;
        }

        $result = \Nexus\Services\VolunteerCheckInService::verifyCheckIn($token);

        if ($result === null) {
            $errors = \Nexus\Services\VolunteerCheckInService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
        }

        $this->respondWithData($result);
    }

    /**
     * POST /api/v2/volunteering/checkin/checkout/{token}
     * Check out a volunteer
     */
    public function checkOut(string $token): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_checkout', 30, 60);

        $shiftId = \Nexus\Services\VolunteerCheckInService::getShiftIdByToken($token);
        if ($shiftId === null) {
            $this->respondWithError('NOT_FOUND', 'Invalid check-in code', null, 404);
            return;
        }

        if (!$this->canManageShift($shiftId, $userId)) {
            $this->respondWithError('FORBIDDEN', 'You do not have permission to check out volunteers for this shift', null, 403);
            return;
        }

        // Resolve volunteer user_id from the token before checkout
        $checkinUserId = \Nexus\Services\VolunteerCheckInService::getUserIdByToken($token);

        $success = \Nexus\Services\VolunteerCheckInService::checkOut($token);

        if (!$success) {
            $errors = \Nexus\Services\VolunteerCheckInService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
        }

        // Webhook: shift.completed
        if ($success && $checkinUserId) {
            try {
                WebhookDispatchService::dispatch('shift.completed', [
                    'user_id' => $checkinUserId,
                    'shift_id' => $shiftId,
                ]);
            } catch (\Throwable $e) {
                error_log("Webhook dispatch failed for shift.completed: " . $e->getMessage());
            }
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
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_shift_checkins', 60, 60);

        if (!$this->canManageShift($shiftId, $userId)) {
            $this->respondWithError('FORBIDDEN', 'You do not have permission to view check-ins for this shift', null, 403);
            return;
        }

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

    /**
     * GET /api/v2/volunteering/wellbeing
     * Composite wellbeing dashboard data for the frontend
     */
    public function wellbeingDashboard(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_wellbeing_dashboard', 30, 60);

        $tenantId = \Nexus\Core\TenantContext::getId();
        $db = \Nexus\Core\Database::getConnection();

        // Get burnout risk assessment
        $assessment = \Nexus\Services\VolunteerWellbeingService::detectBurnoutRisk($userId);

        // Wellbeing score = inverse of risk score (higher is better)
        $score = max(0, min(100, 100 - (int)$assessment['risk_score']));

        // Hours this week
        try {
            $stmt = $db->prepare("SELECT COALESCE(SUM(hours), 0) as total FROM vol_logs WHERE user_id = ? AND tenant_id = ? AND status = 'approved' AND date_logged >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute([$userId, $tenantId]);
            $hoursThisWeek = round((float)$stmt->fetch(\PDO::FETCH_ASSOC)['total'], 1);
        } catch (\Throwable $e) { $hoursThisWeek = 0; }

        // Hours this month
        try {
            $stmt = $db->prepare("SELECT COALESCE(SUM(hours), 0) as total FROM vol_logs WHERE user_id = ? AND tenant_id = ? AND status = 'approved' AND date_logged >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute([$userId, $tenantId]);
            $hoursThisMonth = round((float)$stmt->fetch(\PDO::FETCH_ASSOC)['total'], 1);
        } catch (\Throwable $e) { $hoursThisMonth = 0; }

        // Streak: consecutive days with logged hours
        try {
            $stmt = $db->prepare("SELECT DISTINCT DATE(date_logged) as d FROM vol_logs WHERE user_id = ? AND tenant_id = ? AND status = 'approved' ORDER BY d DESC LIMIT 90");
            $stmt->execute([$userId, $tenantId]);
            $dates = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $streak = 0;
            $today = new \DateTime();
            foreach ($dates as $i => $dateStr) {
                $expected = (clone $today)->modify("-{$i} days")->format('Y-m-d');
                if ($dateStr === $expected) {
                    $streak++;
                } else {
                    break;
                }
            }
        } catch (\Throwable $e) { $streak = 0; }

        // Map risk level to burnout_risk
        $burnoutRisk = match ($assessment['risk_level'] ?? 'low') {
            'critical', 'high' => 'high',
            'moderate' => 'moderate',
            default => 'low',
        };

        // Build warnings from indicators
        $warnings = [];
        $indicators = $assessment['indicators'] ?? [];
        if (($indicators['shift_frequency']['trend'] ?? '') === 'declining') {
            $warnings[] = 'Your volunteering frequency has decreased compared to last month.';
        }
        if (($indicators['cancellation_rate']['rate_percent'] ?? 0) > 30) {
            $warnings[] = 'Your cancellation rate is higher than usual. Consider taking on fewer commitments.';
        }
        if (($indicators['hours_trend']['trend'] ?? '') === 'declining_significantly') {
            $warnings[] = 'Your logged hours have dropped significantly. Remember to take breaks when needed.';
        }
        if (($indicators['engagement_gap']['days_since_last_activity'] ?? 0) > 30) {
            $warnings[] = 'It has been a while since your last volunteer activity. We miss you!';
        }

        // Suggested rest days (next 7 days that have no scheduled shifts)
        $suggestedRest = [];
        try {
            $stmt = $db->prepare("SELECT DISTINCT DATE(s.start_time) as shift_date FROM vol_applications a JOIN vol_shifts s ON a.shift_id = s.id WHERE a.user_id = ? AND a.tenant_id = ? AND a.status = 'approved' AND s.start_time >= NOW() AND s.start_time <= DATE_ADD(NOW(), INTERVAL 7 DAY)");
            $stmt->execute([$userId, $tenantId]);
            $busyDays = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            for ($i = 0; $i < 7; $i++) {
                $day = (new \DateTime())->modify("+{$i} days")->format('Y-m-d');
                if (!in_array($day, $busyDays)) {
                    $suggestedRest[] = $day;
                    if (count($suggestedRest) >= 3) break;
                }
            }
        } catch (\Throwable $e) { /* no suggestions */ }

        // Recent mood check-ins
        $recentCheckins = [];
        try {
            $stmt = $db->prepare("SELECT id, mood, note, created_at FROM vol_mood_checkins WHERE user_id = ? AND tenant_id = ? ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$userId, $tenantId]);
            $recentCheckins = array_map(function ($row) {
                return [
                    'id' => (int)$row['id'],
                    'mood' => (int)$row['mood'],
                    'note' => $row['note'],
                    'created_at' => $row['created_at'],
                ];
            }, $stmt->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\Throwable $e) { /* table may not exist yet */ }

        $this->respondWithData([
            'score' => $score,
            'hours_this_week' => $hoursThisWeek,
            'hours_this_month' => $hoursThisMonth,
            'streak_days' => $streak,
            'burnout_risk' => $burnoutRisk,
            'warnings' => $warnings,
            'suggested_rest_days' => $suggestedRest,
            'recent_checkins' => $recentCheckins,
        ]);
    }

    /**
     * POST /api/v2/volunteering/wellbeing/checkin
     * Submit a mood check-in
     */
    public function wellbeingCheckin(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_wellbeing_checkin', 10, 60);

        $mood = (int)$this->input('mood');
        if ($mood < 1 || $mood > 5) {
            $this->respondWithError('VALIDATION_ERROR', 'Mood must be between 1 and 5', 'mood', 400);
            return;
        }

        $note = $this->input('note');
        if ($note) {
            $note = trim(mb_substr($note, 0, 500));
        }

        $tenantId = \Nexus\Core\TenantContext::getId();
        $db = \Nexus\Core\Database::getConnection();

        try {
            $stmt = $db->prepare("INSERT INTO vol_mood_checkins (tenant_id, user_id, mood, note, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$tenantId, $userId, $mood, $note ?: null]);

            $this->respondWithData([
                'id' => (int)$db->lastInsertId(),
                'mood' => $mood,
                'note' => $note ?: null,
            ]);
        } catch (\Throwable $e) {
            error_log("Wellbeing checkin failed: " . $e->getMessage());
            $this->respondWithError('SERVER_ERROR', 'Failed to save check-in', null, 500);
            return;
        }
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
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_recurring_list', 60, 60);

        $patterns = \Nexus\Services\RecurringShiftService::getPatternsForOpportunity($opportunityId, $userId);

        $errors = \Nexus\Services\RecurringShiftService::getErrors();
        if (!empty($errors)) {
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

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
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_recurring_update', 10, 60);

        $data = $this->getAllInput();

        $success = \Nexus\Services\RecurringShiftService::updatePattern($patternId, $data, $userId);

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
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('volunteering_recurring_delete', 10, 60);

        $deactivated = \Nexus\Services\RecurringShiftService::deactivatePattern($patternId, $userId);

        if (!$deactivated) {
            $errors = \Nexus\Services\RecurringShiftService::getErrors();
            $status = $this->getErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $deleted = \Nexus\Services\RecurringShiftService::deleteFutureShifts($patternId, $userId);

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
     * Check if the user can coordinate/manage a shift.
     */
    private function canManageShift(int $shiftId, int $userId): bool
    {
        try {
            $tenantId = \Nexus\Core\TenantContext::getId();
            $db = \Nexus\Core\Database::getConnection();

            $stmt = $db->prepare("
                SELECT org.id AS organization_id, org.user_id AS org_owner_id
                FROM vol_shifts s
                JOIN vol_opportunities opp ON s.opportunity_id = opp.id
                JOIN vol_organizations org ON opp.organization_id = org.id
                WHERE s.id = ? AND s.tenant_id = ? AND opp.tenant_id = ? AND org.tenant_id = ?
                LIMIT 1
            ");
            $stmt->execute([$shiftId, $tenantId, $tenantId, $tenantId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                return false;
            }

            if ((int)$row['org_owner_id'] === $userId) {
                return true;
            }

            if (\Nexus\Models\OrgMember::isAdmin((int)$row['organization_id'], $userId)) {
                return true;
            }

            $roleStmt = $db->prepare("SELECT role FROM users WHERE id = ? AND tenant_id = ?");
            $roleStmt->execute([$userId, $tenantId]);
            $role = $roleStmt->fetchColumn();

            return in_array($role, ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'], true);
        } catch (\Throwable $e) {
            return false;
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

    // ========================================
    // CREDENTIAL VERIFICATION
    // ========================================

    /**
     * GET /api/v2/volunteering/credentials
     * Get user's uploaded credentials
     */
    public function myCredentials(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_credentials', 30, 60);

        $tenantId = TenantContext::getId();
        $db = \Nexus\Core\Database::getConnection();

        $stmt = $db->prepare("
            SELECT id, credential_type, file_url, file_name, status, expires_at, created_at, updated_at
            FROM vol_credentials
            WHERE user_id = ? AND tenant_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$userId, $tenantId]);
        $credentials = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $mapped = array_map(static function (array $row): array {
            $type = (string)($row['credential_type'] ?? '');
            $typeLabel = ucwords(str_replace('_', ' ', $type));

            return [
                'id' => (int)($row['id'] ?? 0),
                // Canonical API fields
                'credential_type' => $type,
                'file_url' => $row['file_url'] ?? null,
                'file_name' => $row['file_name'] ?? null,
                'status' => $row['status'] ?? 'pending',
                'expires_at' => $row['expires_at'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
                // Frontend compatibility aliases
                'type' => $type,
                'type_label' => $typeLabel,
                'document_name' => $row['file_name'] ?? null,
                'upload_date' => $row['created_at'] ?? null,
                'expiry_date' => $row['expires_at'] ?? null,
                'rejection_reason' => null,
            ];
        }, $credentials);

        $this->respondWithData(['credentials' => $mapped]);
    }

    /**
     * POST /api/v2/volunteering/credentials
     * Upload a new credential document
     */
    public function uploadCredential(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('vol_credential_upload', 10, 60);

        $tenantId = TenantContext::getId();
        $type = trim((string)($this->input('credential_type') ?? $this->input('type') ?? ''));
        $expiresAt = $this->input('expires_at') ?? $this->input('expiry_date');

        if (empty($type)) {
            $this->respondWithError('VALIDATION_ERROR', 'Credential type is required', 'credential_type');
            return;
        }

        $uploadedFile = $_FILES['file'] ?? $_FILES['document'] ?? null;
        if (empty($uploadedFile) || !isset($uploadedFile['error']) || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $this->respondWithError('VALIDATION_ERROR', 'A credential file is required', 'file');
            return;
        }

        // Handle file upload
        $fileUrl = null;
        $fileName = null;
        if (!empty($uploadedFile) && $uploadedFile['error'] === UPLOAD_ERR_OK) {
            // Validate file type
            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($uploadedFile['tmp_name']);
            if (!in_array($mimeType, $allowedMimes, true)) {
                $this->respondWithError('VALIDATION_ERROR', 'Only PDF, JPEG, PNG, and WebP files are allowed', 'file');
                return;
            }

            // 10 MB limit
            if (($uploadedFile['size'] ?? 0) > 10 * 1024 * 1024) {
                $this->respondWithError('VALIDATION_ERROR', 'File size must be under 10 MB', 'file');
                return;
            }

            $fileUrl = \Nexus\Core\ImageUploader::upload($uploadedFile, 'credentials');
            $fileName = $uploadedFile['name'] ?? null;
        }

        $db = \Nexus\Core\Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO vol_credentials (tenant_id, user_id, credential_type, file_url, file_name, status, expires_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())
        ");
        $stmt->execute([$tenantId, $userId, $type, $fileUrl, $fileName, $expiresAt ?: null]);

        $this->respondWithData([
            'success' => true,
            'id' => (int)$db->lastInsertId(),
        ], null, 201);
    }

    /**
     * DELETE /api/v2/volunteering/credentials/{id}
     * Delete a credential
     */
    public function deleteCredential(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('vol_credential_delete', 10, 60);

        $tenantId = TenantContext::getId();
        $db = \Nexus\Core\Database::getConnection();

        $stmt = $db->prepare("DELETE FROM vol_credentials WHERE id = ? AND user_id = ? AND tenant_id = ?");
        $stmt->execute([$id, $userId, $tenantId]);

        if ($stmt->rowCount() === 0) {
            $this->respondWithError('NOT_FOUND', 'Credential not found', null, 404);
            return;
        }

        $this->respondWithData(['success' => true]);
    }

    // ========================================
    // V11: EXPENSE REIMBURSEMENT
    // ========================================

    /**
     * GET /api/v2/volunteering/expenses
     */
    public function myExpenses(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_expenses_list', 30, 60);

        $filters = [
            'user_id' => $userId,
            'status' => $this->query('status'),
            'date_from' => $this->query('date_from'),
            'date_to' => $this->query('date_to'),
            'cursor' => $this->query('cursor'),
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        $result = \Nexus\Services\VolunteerExpenseService::getExpenses($filters);
        $this->respondWithData($result);
    }

    /**
     * POST /api/v2/volunteering/expenses
     */
    public function submitExpense(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('vol_expense_submit', 10, 60);

        $data = $this->getJsonInput();
        $result = \Nexus\Services\VolunteerExpenseService::submitExpense($userId, $data);

        if (isset($result['error'])) {
            $this->respondWithError('VALIDATION_ERROR', $result['error'], null, 422);
            return;
        }

        $this->respondWithData($result, null, 201);
    }

    /**
     * GET /api/v2/volunteering/expenses/{id}
     */
    public function getExpense(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_expense_get', 30, 60);

        $expense = \Nexus\Services\VolunteerExpenseService::getExpense($id);
        if (!$expense || (int)$expense['user_id'] !== $userId) {
            $this->respondWithError('NOT_FOUND', 'Expense not found', null, 404);
            return;
        }

        $this->respondWithData($expense);
    }

    /**
     * GET /api/v2/admin/volunteering/expenses
     */
    public function adminExpenses(): void
    {
        $this->checkFeature();
        $this->requireAdmin();
        $this->rateLimit('vol_admin_expenses', 30, 60);

        $filters = [
            'status' => $this->query('status'),
            'user_id' => $this->query('user_id') ? (int)$this->query('user_id') : null,
            'organization_id' => $this->query('organization_id') ? (int)$this->query('organization_id') : null,
            'date_from' => $this->query('date_from'),
            'date_to' => $this->query('date_to'),
            'cursor' => $this->query('cursor'),
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        $result = \Nexus\Services\VolunteerExpenseService::getExpenses($filters);
        $this->respondWithData($result);
    }

    /**
     * PUT /api/v2/admin/volunteering/expenses/{id}
     */
    public function reviewExpense(int $id): void
    {
        $this->checkFeature();
        $adminId = $this->requireAdmin();
        $this->verifyCsrf();
        $this->rateLimit('vol_expense_review', 30, 60);

        $data = $this->getJsonInput();
        $status = $data['status'] ?? '';

        $allowedStatuses = ['approved', 'rejected', 'paid'];
        if (!in_array($status, $allowedStatuses, true)) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid status. Must be one of: ' . implode(', ', $allowedStatuses), 'status', 422);
            return;
        }

        if ($status === 'paid') {
            $result = \Nexus\Services\VolunteerExpenseService::markPaid($id, $adminId, $data['payment_reference'] ?? null);
        } else {
            $result = \Nexus\Services\VolunteerExpenseService::reviewExpense($id, $adminId, $status, $data['review_notes'] ?? null);
        }

        if (!$result) {
            $this->respondWithError('NOT_FOUND', 'Expense not found or invalid status', null, 404);
            return;
        }

        $this->respondWithData(['success' => true]);
    }

    /**
     * GET /api/v2/admin/volunteering/expenses/export
     */
    public function exportExpenses(): void
    {
        $this->checkFeature();
        $this->requireAdmin();

        $filters = [
            'status' => $this->query('status'),
            'date_from' => $this->query('date_from'),
            'date_to' => $this->query('date_to'),
        ];

        $csv = \Nexus\Services\VolunteerExpenseService::exportExpenses($filters);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="volunteer_expenses_' . date('Y-m-d') . '.csv"');
        echo $csv;
        exit;
    }

    /**
     * GET /api/v2/admin/volunteering/expenses/policies
     */
    public function getExpensePolicies(): void
    {
        $this->checkFeature();
        $this->requireAdmin();

        $orgId = $this->query('organization_id') ? (int)$this->query('organization_id') : null;
        $policies = \Nexus\Services\VolunteerExpenseService::getPolicies($orgId);
        $this->respondWithData($policies);
    }

    /**
     * PUT /api/v2/admin/volunteering/expenses/policies
     */
    public function updateExpensePolicy(): void
    {
        $this->checkFeature();
        $this->requireAdmin();
        $this->verifyCsrf();
        $this->rateLimit('vol_expense_policy_update', 10, 60);

        $data = $this->getJsonInput();

        if (empty($data['expense_type'])) {
            $this->respondWithError('VALIDATION_ERROR', 'expense_type is required', 'expense_type', 422);
            return;
        }

        // Ensure at least one policy field is present besides expense_type
        $policyFields = ['max_amount', 'requires_receipt', 'auto_approve_below', 'description', 'enabled'];
        $hasPolicyField = false;
        foreach ($policyFields as $field) {
            if (array_key_exists($field, $data)) {
                $hasPolicyField = true;
                break;
            }
        }
        if (!$hasPolicyField) {
            $this->respondWithError('VALIDATION_ERROR', 'At least one policy field is required (e.g., max_amount, requires_receipt, auto_approve_below, description, enabled)', null, 422);
            return;
        }

        $result = \Nexus\Services\VolunteerExpenseService::updatePolicy($data);
        $this->respondWithData(['success' => $result]);
    }

    // ========================================
    // V12: GUARDIAN CONSENT
    // ========================================

    /**
     * GET /api/v2/volunteering/guardian-consents
     */
    public function myGuardianConsents(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_guardian_consents', 30, 60);

        $consents = \Nexus\Services\GuardianConsentService::getConsentsForMinor($userId);
        $this->respondWithData($consents);
    }

    /**
     * POST /api/v2/volunteering/guardian-consents
     */
    public function requestGuardianConsent(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('vol_guardian_consent_request', 5, 60);

        $data = $this->getJsonInput();
        $opportunityId = isset($data['opportunity_id']) ? (int)$data['opportunity_id'] : null;

        try {
            $result = \Nexus\Services\GuardianConsentService::requestConsent($userId, $data, $opportunityId);
            $this->respondWithData($result, null, 201);
        } catch (\RuntimeException $e) {
            $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * GET /api/v2/volunteering/guardian-consents/verify/{token}
     */
    public function verifyGuardianConsent(string $token): void
    {
        $this->rateLimit('guardian_consent_verify', 10, 300);

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $result = \Nexus\Services\GuardianConsentService::grantConsent($token, $ip);

        if (!$result) {
            $this->respondWithError('INVALID_TOKEN', 'Consent token is invalid or expired', null, 400);
            return;
        }

        $this->respondWithData(['success' => true, 'message' => 'Guardian consent has been granted successfully.']);
    }

    /**
     * DELETE /api/v2/volunteering/guardian-consents/{id}
     */
    public function withdrawGuardianConsent(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('vol_guardian_consent_withdraw', 10, 60);

        $result = \Nexus\Services\GuardianConsentService::withdrawConsent($id, $userId);
        if (!$result) {
            $this->respondWithError('NOT_FOUND', 'Consent not found', null, 404);
            return;
        }

        $this->respondWithData(['success' => true]);
    }

    /**
     * GET /api/v2/admin/volunteering/guardian-consents
     */
    public function adminGuardianConsents(): void
    {
        $this->checkFeature();
        $this->requireAdmin();

        $filters = [
            'status' => $this->query('status'),
            'search' => $this->query('search'),
        ];

        $consents = \Nexus\Services\GuardianConsentService::getConsentsForAdmin($filters);
        $this->respondWithData($consents);
    }

    // ========================================
    // V13: SAFEGUARDING TRAINING & INCIDENTS
    // ========================================

    /**
     * GET /api/v2/volunteering/training
     */
    public function myTraining(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_training_list', 30, 60);

        $training = \Nexus\Services\SafeguardingService::getTrainingForUser($userId);
        $this->respondWithData($training);
    }

    /**
     * POST /api/v2/volunteering/training
     */
    public function recordTraining(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('vol_training_record', 10, 60);

        $data = $this->getJsonInput();

        try {
            $result = \Nexus\Services\SafeguardingService::recordTraining($userId, $data);
            $this->respondWithData($result, null, 201);
        } catch (\InvalidArgumentException $e) {
            $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * GET /api/v2/admin/volunteering/training
     */
    public function adminTraining(): void
    {
        $this->checkFeature();
        $this->requireAdmin();

        $filters = [
            'status' => $this->query('status'),
            'training_type' => $this->query('training_type'),
            'user_id' => $this->query('user_id') ? (int)$this->query('user_id') : null,
            'cursor' => $this->query('cursor'),
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        $result = \Nexus\Services\SafeguardingService::getTrainingForAdmin($filters);
        $this->respondWithData($result);
    }

    /**
     * PUT /api/v2/admin/volunteering/training/{id}/verify
     */
    public function verifyTraining(int $id): void
    {
        $this->checkFeature();
        $adminId = $this->requireAdmin();
        $this->verifyCsrf();

        $result = \Nexus\Services\SafeguardingService::verifyTraining($id, $adminId);
        if (!$result) {
            $this->respondWithError('NOT_FOUND', 'Training record not found', null, 404);
            return;
        }
        $this->respondWithData(['success' => true]);
    }

    /**
     * PUT /api/v2/admin/volunteering/training/{id}/reject
     */
    public function rejectTraining(int $id): void
    {
        $this->checkFeature();
        $adminId = $this->requireAdmin();
        $this->verifyCsrf();

        $result = \Nexus\Services\SafeguardingService::rejectTraining($id, $adminId);
        if (!$result) {
            $this->respondWithError('NOT_FOUND', 'Training record not found', null, 404);
            return;
        }
        $this->respondWithData(['success' => true]);
    }

    /**
     * POST /api/v2/volunteering/incidents
     */
    public function reportIncident(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('vol_incident_report', 5, 60);

        $data = $this->getJsonInput();

        try {
            $result = \Nexus\Services\SafeguardingService::reportIncident($userId, $data);
            $this->respondWithData($result, null, 201);
        } catch (\InvalidArgumentException $e) {
            $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * GET /api/v2/volunteering/incidents
     */
    public function getIncidents(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_incidents_list', 30, 60);

        $filters = [
            'reported_by' => $userId,
            'status' => $this->query('status'),
        ];

        $result = \Nexus\Services\SafeguardingService::getIncidents($filters);
        $this->respondWithData($result);
    }

    /**
     * GET /api/v2/volunteering/incidents/{id}
     */
    public function getIncident(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_incident_get', 30, 60);

        $incident = \Nexus\Services\SafeguardingService::getIncident($id);
        if (!$incident) {
            $this->respondWithError('NOT_FOUND', 'Incident not found', null, 404);
            return;
        }

        // Ownership check: only the reporter or an admin can view the incident
        $role = $this->getAuthenticatedUserRole() ?? 'member';
        $isAdmin = in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'], true);
        if ((int)($incident['reported_by'] ?? 0) !== $userId && !$isAdmin) {
            $this->respondWithError('FORBIDDEN', 'You do not have permission to view this incident', null, 403);
            return;
        }

        $this->respondWithData($incident);
    }

    /**
     * GET /api/v2/admin/volunteering/incidents
     */
    public function adminIncidents(): void
    {
        $this->checkFeature();
        $this->requireAdmin();

        $filters = [
            'status' => $this->query('status'),
            'severity' => $this->query('severity'),
            'organization_id' => $this->query('organization_id') ? (int)$this->query('organization_id') : null,
            'cursor' => $this->query('cursor'),
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        $result = \Nexus\Services\SafeguardingService::getIncidents($filters);
        $this->respondWithData($result);
    }

    /**
     * PUT /api/v2/admin/volunteering/incidents/{id}
     */
    public function updateIncident(int $id): void
    {
        $this->checkFeature();
        $this->requireAdmin();
        $this->verifyCsrf();

        $data = $this->getJsonInput();
        $result = \Nexus\Services\SafeguardingService::updateIncident($id, $data);

        if (!$result) {
            $this->respondWithError('NOT_FOUND', 'Incident not found', null, 404);
            return;
        }
        $this->respondWithData(['success' => true]);
    }

    /**
     * PUT /api/v2/admin/volunteering/organizations/{id}/dlp
     */
    public function assignDlp(int $id): void
    {
        $this->checkFeature();
        $this->requireAdmin();
        $this->verifyCsrf();

        $data = $this->getJsonInput();

        $dlpUserId = (int)($data['dlp_user_id'] ?? 0);
        if ($dlpUserId <= 0) {
            $this->respondWithError('VALIDATION_ERROR', 'dlp_user_id is required and must be a positive integer', 'dlp_user_id', 422);
            return;
        }

        $result = \Nexus\Services\SafeguardingService::assignDlp(
            $id,
            $dlpUserId,
            isset($data['deputy_dlp_user_id']) ? (int)$data['deputy_dlp_user_id'] : null
        );

        $this->respondWithData(['success' => $result]);
    }

    // ========================================
    // V14: CUSTOM FORMS & ACCESSIBILITY
    // ========================================

    /**
     * GET /api/v2/volunteering/custom-fields
     *
     * Intentionally public (no auth required) — custom field definitions are needed
     * to render application forms, which may be accessible before login.
     */
    public function getCustomFields(): void
    {
        $this->checkFeature();
        $this->rateLimit('vol_public_read', 60, 30);

        $orgId = $this->query('organization_id') ? (int)$this->query('organization_id') : null;
        $appliesTo = $this->query('applies_to') ?: 'application';

        $fields = \Nexus\Services\VolunteerFormService::getCustomFields($orgId, $appliesTo);
        $this->respondWithData($fields);
    }

    /**
     * GET /api/v2/admin/volunteering/custom-fields
     */
    public function adminCustomFields(): void
    {
        $this->checkFeature();
        $this->requireAdmin();

        $orgId = $this->query('organization_id') ? (int)$this->query('organization_id') : null;
        $fields = \Nexus\Services\VolunteerFormService::getCustomFields($orgId);
        $this->respondWithData($fields);
    }

    /**
     * POST /api/v2/admin/volunteering/custom-fields
     */
    public function createCustomField(): void
    {
        $this->checkFeature();
        $this->requireAdmin();
        $this->verifyCsrf();
        $this->rateLimit('vol_custom_field_create', 10, 60);

        $data = $this->getJsonInput();

        if (empty($data['field_label'])) {
            $this->respondWithError('VALIDATION_ERROR', 'field_label is required', 'field_label', 422);
            return;
        }

        try {
            $result = \Nexus\Services\VolunteerFormService::createField($data);
            $this->respondWithData($result, null, 201);
        } catch (\InvalidArgumentException $e) {
            $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\Exception $e) {
            error_log("VolunteerApiController::createCustomField error: " . $e->getMessage());
            $this->respondWithError('INTERNAL_ERROR', 'Failed to create custom field', null, 500);
        }
    }

    /**
     * PUT /api/v2/admin/volunteering/custom-fields/{id}
     */
    public function updateCustomField(int $id): void
    {
        $this->checkFeature();
        $this->requireAdmin();
        $this->verifyCsrf();

        $data = $this->getJsonInput();
        $result = \Nexus\Services\VolunteerFormService::updateField($id, $data);

        if (!$result) {
            $this->respondWithError('NOT_FOUND', 'Custom field not found', null, 404);
            return;
        }

        $this->respondWithData(['success' => true]);
    }

    /**
     * DELETE /api/v2/admin/volunteering/custom-fields/{id}
     */
    public function deleteCustomField(int $id): void
    {
        $this->checkFeature();
        $this->requireAdmin();
        $this->verifyCsrf();

        $result = \Nexus\Services\VolunteerFormService::deleteField($id);

        if (!$result) {
            $this->respondWithError('NOT_FOUND', 'Custom field not found', null, 404);
            return;
        }

        $this->respondWithData(['success' => true]);
    }

    /**
     * GET /api/v2/volunteering/accessibility-needs
     */
    public function myAccessibilityNeeds(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();

        $needs = \Nexus\Services\VolunteerFormService::getAccessibilityNeeds($userId);
        $this->respondWithData($needs);
    }

    /**
     * PUT /api/v2/volunteering/accessibility-needs
     */
    public function updateAccessibilityNeeds(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();

        $data = $this->getJsonInput();
        \Nexus\Services\VolunteerFormService::updateAccessibilityNeeds($userId, $data['needs'] ?? []);
        $this->respondWithData(['success' => true]);
    }

    // ========================================
    // V15: COMMUNITY PROJECTS
    // ========================================

    /**
     * GET /api/v2/volunteering/community-projects
     *
     * Intentionally public (no auth required) — community project listings may be
     * embedded on public pages for visibility and community engagement.
     */
    public function getCommunityProjects(): void
    {
        $this->checkFeature();
        $this->rateLimit('vol_public_read', 60, 30);

        $filters = [
            'status' => $this->query('status') ?: 'proposed',
            'category' => $this->query('category'),
            'search' => $this->query('search'),
            'sort' => $this->query('sort') ?: 'newest',
            'cursor' => $this->query('cursor'),
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        $result = \Nexus\Services\CommunityProjectService::getProposals($filters);
        $this->respondWithData($result);
    }

    /**
     * POST /api/v2/volunteering/community-projects
     */
    public function proposeCommunityProject(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('vol_project_propose', 5, 60);

        $data = $this->getJsonInput();

        try {
            $result = \Nexus\Services\CommunityProjectService::propose($userId, $data);
            $this->respondWithData($result, null, 201);
        } catch (\InvalidArgumentException $e) {
            $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * GET /api/v2/volunteering/community-projects/{id}
     *
     * Intentionally public (no auth required) — individual project pages may be
     * shared via direct links for community engagement.
     */
    public function getCommunityProject(int $id): void
    {
        $this->checkFeature();
        $this->rateLimit('vol_public_read', 60, 30);

        $project = \Nexus\Services\CommunityProjectService::getProposal($id);
        if (!$project) {
            $this->respondWithError('NOT_FOUND', 'Project not found', null, 404);
            return;
        }
        $this->respondWithData($project);
    }

    /**
     * PUT /api/v2/volunteering/community-projects/{id}
     */
    public function updateCommunityProject(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();

        $data = $this->getJsonInput();
        $result = \Nexus\Services\CommunityProjectService::updateProposal($id, $userId, $data);

        if (!$result) {
            $this->respondWithError('FORBIDDEN', 'Cannot update this project', null, 403);
            return;
        }
        $this->respondWithData(['success' => true]);
    }

    /**
     * POST /api/v2/volunteering/community-projects/{id}/support
     */
    public function supportCommunityProject(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('vol_project_support', 30, 60);

        $data = $this->getJsonInput();
        $result = \Nexus\Services\CommunityProjectService::support($id, $userId, $data['message'] ?? null);
        $this->respondWithData(['success' => $result]);
    }

    /**
     * DELETE /api/v2/volunteering/community-projects/{id}/support
     */
    public function unsupportCommunityProject(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();

        $result = \Nexus\Services\CommunityProjectService::unsupport($id, $userId);
        $this->respondWithData(['success' => $result]);
    }

    /**
     * PUT /api/v2/admin/volunteering/community-projects/{id}/review
     */
    public function reviewCommunityProject(int $id): void
    {
        $this->checkFeature();
        $adminId = $this->requireAdmin();
        $this->verifyCsrf();

        $data = $this->getJsonInput();

        try {
            $result = \Nexus\Services\CommunityProjectService::review(
                $id,
                $adminId,
                $data['status'] ?? '',
                $data['notes'] ?? null
            );
            $this->respondWithData(['success' => $result]);
        } catch (\InvalidArgumentException $e) {
            $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    // ========================================
    // V16: DONATIONS & GIVING DAYS
    // ========================================

    /**
     * GET /api/v2/volunteering/donations
     *
     * Intentionally public (no auth required) — donation listings may be embedded
     * on fundraising pages and shared externally.
     */
    public function getDonations(): void
    {
        $this->checkFeature();
        $this->rateLimit('vol_public_read', 60, 30);

        $filters = [
            'opportunity_id' => $this->query('opportunity_id') ? (int)$this->query('opportunity_id') : null,
            'community_project_id' => $this->query('community_project_id') ? (int)$this->query('community_project_id') : null,
            'cursor' => $this->query('cursor'),
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        $result = \Nexus\Services\VolunteerDonationService::getDonations($filters);
        $this->respondWithData($result);
    }

    /**
     * POST /api/v2/volunteering/donations
     */
    public function createDonation(): void
    {
        $this->checkFeature();
        $userId = $this->getUserIdOptional();
        $this->verifyCsrf();
        $this->rateLimit('vol_donation_create', 10, 60);

        $data = $this->getJsonInput();
        if ($userId) {
            $data['user_id'] = $userId;
        }

        try {
            $result = \Nexus\Services\VolunteerDonationService::createDonation($userId ?: 0, $data);
            $this->respondWithData($result, null, 201);
        } catch (\InvalidArgumentException $e) {
            $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * GET /api/v2/volunteering/giving-days
     */
    public function getGivingDays(): void
    {
        $this->checkFeature();
        $this->rateLimit('vol_giving_days', 30, 60);

        $result = \Nexus\Services\VolunteerDonationService::getGivingDays();
        $this->respondWithData($result);
    }

    /**
     * GET /api/v2/volunteering/giving-days/{id}/stats
     *
     * Intentionally public (no auth required) — giving day stats are displayed
     * on public fundraising pages and real-time donation widgets.
     */
    public function getGivingDayStats(int $id): void
    {
        $this->checkFeature();
        $this->rateLimit('vol_public_read', 60, 30);

        $stats = \Nexus\Services\VolunteerDonationService::getGivingDayStats($id);
        if (!$stats) {
            $this->respondWithError('NOT_FOUND', 'Giving day not found', null, 404);
            return;
        }
        $this->respondWithData($stats);
    }

    /**
     * GET /api/v2/admin/volunteering/giving-days
     */
    public function adminGivingDays(): void
    {
        $this->checkFeature();
        $this->requireAdmin();

        $result = \Nexus\Services\VolunteerDonationService::adminGetGivingDays();
        $this->respondWithData($result);
    }

    /**
     * POST /api/v2/admin/volunteering/giving-days
     */
    public function createGivingDay(): void
    {
        $this->checkFeature();
        $adminId = $this->requireAdmin();
        $this->verifyCsrf();

        $data = $this->getJsonInput();

        try {
            $result = \Nexus\Services\VolunteerDonationService::createGivingDay($adminId, $data);
            $this->respondWithData($result, null, 201);
        } catch (\InvalidArgumentException $e) {
            $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * PUT /api/v2/admin/volunteering/giving-days/{id}
     */
    public function updateGivingDay(int $id): void
    {
        $this->checkFeature();
        $this->requireAdmin();
        $this->verifyCsrf();

        $data = $this->getJsonInput();
        $result = \Nexus\Services\VolunteerDonationService::updateGivingDay($id, $data);
        $this->respondWithData(['success' => $result]);
    }

    /**
     * GET /api/v2/admin/volunteering/donations/export
     */
    public function exportDonations(): void
    {
        $this->checkFeature();
        $this->requireAdmin();

        $filters = [
            'date_from' => $this->query('date_from'),
            'date_to' => $this->query('date_to'),
        ];

        $csv = \Nexus\Services\VolunteerDonationService::exportDonations($filters);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="volunteer_donations_' . date('Y-m-d') . '.csv"');
        echo $csv;
        exit;
    }

    // ========================================
    // OUTBOUND WEBHOOKS (Admin)
    // ========================================

    /**
     * GET /api/v2/admin/volunteering/webhooks
     */
    public function getWebhooks(): void
    {
        $this->checkFeature();
        $this->requireAdmin();

        $webhooks = WebhookDispatchService::getWebhooks();
        $this->respondWithData($webhooks);
    }

    /**
     * POST /api/v2/admin/volunteering/webhooks
     */
    public function createWebhook(): void
    {
        $this->checkFeature();
        $adminId = $this->requireAdmin();
        $this->verifyCsrf();

        $data = $this->getJsonInput();

        try {
            $result = WebhookDispatchService::createWebhook($adminId, $data);
            $this->respondWithData($result, null, 201);
        } catch (\InvalidArgumentException $e) {
            $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /**
     * PUT /api/v2/admin/volunteering/webhooks/{id}
     */
    public function updateWebhook(int $id): void
    {
        $this->checkFeature();
        $this->requireAdmin();
        $this->verifyCsrf();

        $data = $this->getJsonInput();
        $result = WebhookDispatchService::updateWebhook($id, $data);
        $this->respondWithData(['success' => $result]);
    }

    /**
     * DELETE /api/v2/admin/volunteering/webhooks/{id}
     */
    public function deleteWebhook(int $id): void
    {
        $this->checkFeature();
        $this->requireAdmin();
        $this->verifyCsrf();

        $result = WebhookDispatchService::deleteWebhook($id);
        $this->respondWithData(['success' => $result]);
    }

    /**
     * POST /api/v2/admin/volunteering/webhooks/{id}/test
     */
    public function testWebhook(int $id): void
    {
        $this->checkFeature();
        $this->requireAdmin();
        $this->verifyCsrf();

        $result = WebhookDispatchService::testWebhook($id);
        $this->respondWithData($result);
    }

    /**
     * GET /api/v2/admin/volunteering/webhooks/{id}/logs
     */
    public function getWebhookLogs(int $id): void
    {
        $this->checkFeature();
        $this->requireAdmin();

        $filters = [
            'cursor' => $this->query('cursor'),
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        $logs = WebhookDispatchService::getLogs($id, $filters);
        $this->respondWithData($logs);
    }

    // ========================================
    // REMINDER SETTINGS (Admin)
    // ========================================

    /**
     * GET /api/v2/admin/volunteering/reminder-settings
     */
    public function getReminderSettings(): void
    {
        $this->checkFeature();
        $this->requireAdmin();

        $settings = \Nexus\Services\VolunteerReminderService::getSettings();
        $this->respondWithData($settings);
    }

    /**
     * PUT /api/v2/admin/volunteering/reminder-settings
     */
    public function updateReminderSettings(): void
    {
        $this->checkFeature();
        $this->requireAdmin();
        $this->verifyCsrf();

        $data = $this->getJsonInput();
        $type = $data['reminder_type'] ?? '';

        $allowedTypes = ['pre_shift', 'post_shift_feedback', 'lapsed_volunteer', 'credential_expiry', 'training_expiry'];
        if (!in_array($type, $allowedTypes, true)) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid reminder_type. Must be one of: ' . implode(', ', $allowedTypes), 'reminder_type', 422);
            return;
        }

        $result = \Nexus\Services\VolunteerReminderService::updateSetting($type, $data);
        $this->respondWithData(['success' => $result]);
    }
}
