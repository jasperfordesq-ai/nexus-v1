<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\VolunteerService;
use Nexus\Core\TenantContext;
use Nexus\Services\VolunteerService as LegacyVolunteerService;

/**
 * VolunteerController -- Volunteering opportunities, applications, shifts, hours, and organisations.
 *
 * Core methods use Eloquent via VolunteerService; all other methods call legacy static services directly.
 * Only uploadCredential, exportExpenses, exportDonations, certificateHtml kept as delegation
 * due to $_FILES or raw output requirements.
 */
class VolunteerController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly VolunteerService $volunteerService,
    ) {}

    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('volunteering')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', 'Volunteering module is not enabled for this community', null, 403)
            );
        }
    }

    private function getErrorStatus(array $errors): int
    {
        foreach ($errors as $error) {
            $code = $error['code'] ?? '';
            if ($code === 'NOT_FOUND') return 404;
            if ($code === 'FORBIDDEN') return 403;
            if ($code === 'ALREADY_EXISTS') return 409;
            if ($code === 'FEATURE_DISABLED') return 403;
        }
        return 400;
    }

    // ========================================
    // OPPORTUNITIES (already native)
    // ========================================

    public function opportunities(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('volunteering_list', 60, 60);

        $filters = ['limit' => $this->queryInt('per_page', 20, 1, 50)];
        if ($this->query('organization_id')) $filters['organization_id'] = (int) $this->query('organization_id');
        if ($this->query('category_id')) $filters['category_id'] = (int) $this->query('category_id');
        if ($this->query('search')) $filters['search'] = $this->query('search');
        if ($this->queryBool('is_remote')) $filters['is_remote'] = true;
        if ($this->query('cursor')) $filters['cursor'] = $this->query('cursor');

        $result = $this->volunteerService->getOpportunities($filters);
        return $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    public function showOpportunity($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('volunteering_show', 120, 60);
        $opportunity = $this->volunteerService->getById((int) $id);
        if (!$opportunity) return $this->respondWithError('NOT_FOUND', 'Opportunity not found', null, 404);
        return $this->respondWithData($opportunity);
    }

    public function createOpportunity(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_create', 10, 60);
        $data = $this->getAllInput();
        $data['created_by'] = $userId;
        $opportunity = $this->volunteerService->createOpportunity($userId, $data);
        return $this->respondWithData($opportunity, null, 201);
    }

    public function apply(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_apply', 20, 60);
        $data = ['message' => trim($this->input('message', '')), 'shift_id' => $this->inputInt('shift_id') ?: null];
        $application = $this->volunteerService->apply($id, $userId, $data);
        return $this->respondWithData($application, null, 201);
    }

    public function myApplications(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_my_apps', 60, 60);
        $applications = $this->volunteerService->getMyApplications($userId);
        return $this->respondWithData($applications);
    }

    public function myShifts(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_my_shifts', 60, 60);
        $shifts = $this->volunteerService->getMyShifts($userId);
        return $this->respondWithData($shifts);
    }

    public function myHours(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_my_hours', 60, 60);
        $hours = $this->volunteerService->getMyHours($userId);
        return $this->respondWithData($hours);
    }

    public function hoursSummary(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_hours_summary', 60, 60);
        $summary = $this->volunteerService->getHoursSummary($userId);
        return $this->respondWithData($summary);
    }

    public function organisations(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('volunteering_orgs', 60, 60);
        $filters = ['limit' => $this->queryInt('per_page', 20, 1, 50)];
        if ($this->query('search')) $filters['search'] = $this->query('search');
        if ($this->query('cursor')) $filters['cursor'] = $this->query('cursor');
        $result = $this->volunteerService->getOrganisations($filters);
        return $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    public function showOrganisation($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('volunteering_org_show', 120, 60);
        $org = $this->volunteerService->getOrganisationById((int) $id);
        if (!$org) return $this->respondWithError('NOT_FOUND', 'Organisation not found', null, 404);
        return $this->respondWithData($org);
    }

    // ========================================
    // OPPORTUNITIES — now native (were delegated)
    // ========================================

    public function show(int $id): JsonResponse
    {
        return $this->showOpportunity($id);
    }

    public function updateOpportunity($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_update', 20, 60);

        $data = [];
        foreach (['title', 'description', 'location', 'skills_needed', 'start_date', 'end_date'] as $field) {
            if ($this->input($field) !== null) $data[$field] = trim($this->input($field));
        }
        if ($this->input('category_id') !== null) $data['category_id'] = $this->inputInt('category_id') ?: null;

        $success = LegacyVolunteerService::updateOpportunity((int) $id, $userId, $data);
        if (!$success) {
            $errors = LegacyVolunteerService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }

        $opportunity = LegacyVolunteerService::getOpportunityById((int) $id, $userId);
        return $this->respondWithData($opportunity);
    }

    public function deleteOpportunity($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_delete', 10, 60);

        $success = LegacyVolunteerService::deleteOpportunity((int) $id, $userId);
        if (!$success) {
            $errors = LegacyVolunteerService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->noContent();
    }

    // ========================================
    // APPLICATIONS — now native
    // ========================================

    public function opportunityApplications($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_opp_apps', 60, 60);

        $filters = ['limit' => $this->queryInt('per_page', 20, 1, 50)];
        if ($this->query('status')) $filters['status'] = $this->query('status');
        if ($this->query('cursor')) $filters['cursor'] = $this->query('cursor');

        $result = LegacyVolunteerService::getApplicationsForOpportunity((int) $id, $userId, $filters);
        if ($result === null) {
            $errors = LegacyVolunteerService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['items' => $result['items'], 'cursor' => $result['cursor'], 'has_more' => $result['has_more']]);
    }

    public function handleApplication($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_handle_app', 30, 60);

        $action = $this->input('action');
        $orgNote = trim((string) ($this->input('org_note') ?? ''));
        if (!$action) return $this->respondWithError('VALIDATION_ERROR', 'Action is required (approve or decline)', 'action', 400);

        $success = LegacyVolunteerService::handleApplication((int) $id, $userId, $action, $orgNote);
        if (!$success) {
            $errors = LegacyVolunteerService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['id' => (int) $id, 'status' => $action === 'approve' ? 'approved' : 'declined']);
    }

    public function withdrawApplication($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_withdraw', 20, 60);

        $success = LegacyVolunteerService::withdrawApplication((int) $id, $userId);
        if (!$success) {
            $errors = LegacyVolunteerService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->noContent();
    }

    // ========================================
    // SHIFTS — now native
    // ========================================

    public function shifts($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('volunteering_shifts', 120, 60);
        $shifts = LegacyVolunteerService::getShiftsForOpportunity((int) $id);
        return $this->respondWithData(['shifts' => $shifts]);
    }

    public function signUp($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_shift_signup', 20, 60);

        $success = LegacyVolunteerService::signUpForShift((int) $id, $userId);
        if (!$success) {
            $errors = LegacyVolunteerService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['shift_id' => (int) $id, 'message' => 'Successfully signed up for shift']);
    }

    public function cancelSignup($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_shift_cancel', 20, 60);

        $success = LegacyVolunteerService::cancelShiftSignup((int) $id, $userId);
        if (!$success) {
            $errors = LegacyVolunteerService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->noContent();
    }

    // ========================================
    // HOURS — now native
    // ========================================

    public function logHours(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_log_hours', 20, 60);

        $data = [
            'organization_id' => $this->inputInt('organization_id'),
            'opportunity_id'  => $this->inputInt('opportunity_id') ?: null,
            'date'            => $this->input('date'),
            'hours'           => (float) $this->input('hours'),
            'description'     => trim($this->input('description', '')),
        ];

        $logId = LegacyVolunteerService::logHours($userId, $data);
        if ($logId === null) {
            $errors = LegacyVolunteerService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['id' => $logId, 'status' => 'pending', 'message' => 'Hours logged successfully, pending verification'], null, 201);
    }

    public function pendingHoursReview(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_pending_hours', 60, 60);

        $filters = ['limit' => $this->queryInt('per_page', 20, 1, 50)];
        if ($this->query('cursor')) $filters['cursor'] = $this->query('cursor');

        $result = LegacyVolunteerService::getPendingHoursForOrgOwner($userId, $filters);
        return $this->respondWithData(['items' => $result['items'], 'cursor' => $result['cursor'], 'has_more' => $result['has_more']]);
    }

    public function verifyHours($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_verify_hours', 30, 60);

        $action = $this->input('action');
        if (!$action) return $this->respondWithError('VALIDATION_ERROR', 'Action is required (approve or decline)', 'action', 400);

        $success = LegacyVolunteerService::verifyHours((int) $id, $userId, $action);
        if (!$success) {
            $errors = LegacyVolunteerService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['id' => (int) $id, 'status' => $action === 'approve' ? 'approved' : 'declined']);
    }

    // ========================================
    // ORGANISATIONS — now native
    // ========================================

    public function myOrganisations(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_my_orgs', 60, 60);
        $orgs = LegacyVolunteerService::getMyOrganizations($userId);
        return $this->respondWithData($orgs);
    }

    public function createOrganisation(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_org_create', 5, 60);

        $data = [
            'name'          => trim($this->input('name', '')),
            'description'   => trim($this->input('description', '')),
            'contact_email' => trim($this->input('contact_email', '')),
            'website'       => trim($this->input('website', '')),
        ];

        $orgId = LegacyVolunteerService::createOrganization($userId, $data);
        if ($orgId === null) {
            $errors = LegacyVolunteerService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        $org = LegacyVolunteerService::getOrganizationById($orgId, true);
        return $this->respondWithData($org, null, 201);
    }

    // ========================================
    // REVIEWS — now native
    // ========================================

    public function createReview(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_review', 10, 60);

        $targetType = $this->input('target_type');
        $targetId = $this->inputInt('target_id');
        $rating = $this->inputInt('rating');
        $comment = trim($this->input('comment', ''));

        if (!$targetType) return $this->respondWithError('VALIDATION_ERROR', 'Target type is required', 'target_type', 400);
        if (!$targetId) return $this->respondWithError('VALIDATION_ERROR', 'Target ID is required', 'target_id', 400);
        if (!$rating) return $this->respondWithError('VALIDATION_ERROR', 'Rating is required', 'rating', 400);

        $reviewId = LegacyVolunteerService::createReview($userId, $targetType, $targetId, $rating, $comment);
        if ($reviewId === null) {
            $errors = LegacyVolunteerService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['id' => $reviewId, 'rating' => $rating, 'message' => 'Review submitted successfully'], null, 201);
    }

    public function getReviews($type, $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('volunteering_reviews', 60, 60);
        if (!in_array($type, ['organization', 'user'])) return $this->respondWithError('VALIDATION_ERROR', 'Type must be organization or user', 'type', 400);
        $reviews = LegacyVolunteerService::getReviews($type, (int) $id);
        return $this->respondWithData(['reviews' => $reviews]);
    }

    // ========================================
    // WAITLIST — now native
    // ========================================

    public function joinWaitlist($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_waitlist_join', 20, 60);

        $entryId = \Nexus\Services\ShiftWaitlistService::join((int) $id, $userId);
        if ($entryId === null) {
            $errors = \Nexus\Services\ShiftWaitlistService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        $position = \Nexus\Services\ShiftWaitlistService::getUserPosition((int) $id, $userId);
        return $this->respondWithData(['id' => $entryId, 'position' => $position['position'] ?? 1, 'message' => 'Successfully joined the waitlist'], null, 201);
    }

    public function leaveWaitlist($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_waitlist_leave', 20, 60);

        $success = \Nexus\Services\ShiftWaitlistService::leave((int) $id, $userId);
        if (!$success) {
            $errors = \Nexus\Services\ShiftWaitlistService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->noContent();
    }

    public function promoteFromWaitlist($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_waitlist_promote', 10, 60);

        $success = \Nexus\Services\ShiftWaitlistService::promoteUser((int) $id, $userId);
        if (!$success) {
            $errors = \Nexus\Services\ShiftWaitlistService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['message' => 'Successfully claimed the shift spot']);
    }

    public function myWaitlists(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_waitlists_list', 60, 60);
        $entries = \Nexus\Services\ShiftWaitlistService::getUserWaitlists($userId);
        return $this->respondWithData($entries);
    }

    // ========================================
    // SHIFT SWAPPING — now native
    // ========================================

    public function requestSwap(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_swap_request', 10, 60);

        $data = [
            'from_shift_id' => $this->inputInt('from_shift_id'),
            'to_shift_id'   => $this->inputInt('to_shift_id'),
            'to_user_id'    => $this->inputInt('to_user_id'),
            'message'       => trim($this->input('message', '')),
        ];

        $swapId = \Nexus\Services\ShiftSwapService::requestSwap($userId, $data);
        if ($swapId === null) {
            $errors = \Nexus\Services\ShiftSwapService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['id' => $swapId, 'message' => 'Swap request sent'], null, 201);
    }

    public function getSwapRequests(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_swaps_list', 60, 60);
        $direction = $this->query('direction') ?? 'all';
        $requests = \Nexus\Services\ShiftSwapService::getSwapRequests($userId, $direction);
        return $this->respondWithData(['swaps' => $requests]);
    }

    public function respondToSwap($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_swap_respond', 20, 60);

        $action = $this->input('action');
        if (!$action || !in_array($action, ['accept', 'reject'])) {
            return $this->respondWithError('VALIDATION_ERROR', 'Action must be accept or reject', 'action', 400);
        }

        $success = \Nexus\Services\ShiftSwapService::respond((int) $id, $userId, $action);
        if (!$success) {
            $errors = \Nexus\Services\ShiftSwapService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['id' => (int) $id, 'status' => $action === 'accept' ? 'accepted' : 'rejected']);
    }

    public function cancelSwap($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_swap_cancel', 20, 60);

        $success = \Nexus\Services\ShiftSwapService::cancel((int) $id, $userId);
        if (!$success) {
            $errors = \Nexus\Services\ShiftSwapService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->noContent();
    }

    // ========================================
    // GROUP RESERVATIONS — now native
    // ========================================

    public function groupReserve($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_group_reserve', 10, 60);

        $groupId = $this->inputInt('group_id');
        $slots = $this->inputInt('reserved_slots', 1);
        $notes = trim($this->input('notes', ''));
        if (!$groupId) return $this->respondWithError('VALIDATION_ERROR', 'Group ID is required', 'group_id', 400);

        $reservationId = \Nexus\Services\ShiftGroupReservationService::reserve((int) $id, $groupId, $userId, $slots, $notes ?: null);
        if ($reservationId === null) {
            $errors = \Nexus\Services\ShiftGroupReservationService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['id' => $reservationId, 'message' => "Reserved {$slots} slots"], null, 201);
    }

    public function addGroupMember($id): JsonResponse
    {
        $this->ensureFeature();
        $leaderId = $this->getUserId();
        $this->rateLimit('volunteering_group_member', 20, 60);

        $memberUserId = $this->inputInt('user_id');
        if (!$memberUserId) return $this->respondWithError('VALIDATION_ERROR', 'User ID is required', 'user_id', 400);

        $success = \Nexus\Services\ShiftGroupReservationService::addMember((int) $id, $memberUserId, $leaderId);
        if (!$success) {
            $errors = \Nexus\Services\ShiftGroupReservationService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['message' => 'Member added to group reservation']);
    }

    public function removeGroupMember($id, $userId): JsonResponse
    {
        $this->ensureFeature();
        $leaderId = $this->getUserId();
        $this->rateLimit('volunteering_group_member_remove', 20, 60);

        $success = \Nexus\Services\ShiftGroupReservationService::removeMember((int) $id, (int) $userId, $leaderId);
        if (!$success) {
            $errors = \Nexus\Services\ShiftGroupReservationService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->noContent();
    }

    public function cancelGroupReservation($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_group_cancel', 10, 60);

        $success = \Nexus\Services\ShiftGroupReservationService::cancelReservation((int) $id, $userId);
        if (!$success) {
            $errors = \Nexus\Services\ShiftGroupReservationService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->noContent();
    }

    public function myGroupReservations(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_group_reservations_list', 60, 60);
        $reservations = \Nexus\Services\ShiftGroupReservationService::getUserReservations($userId);
        return $this->respondWithData($reservations);
    }

    // ========================================
    // CHECK-IN, CERTIFICATES, EMERGENCY, WELLBEING,
    // RECURRING, CREDENTIALS, EXPENSES, GUARDIAN,
    // TRAINING, INCIDENTS, CUSTOM FIELDS, ACCESSIBILITY,
    // COMMUNITY PROJECTS, DONATIONS, GIVING DAYS,
    // WEBHOOKS, REMINDERS — now native via legacy services
    // ========================================

    public function getCheckIn($id): JsonResponse
    {
        return $this->callLegacy('getCheckIn', [(int) $id]);
    }

    public function verifyCheckIn($token): JsonResponse
    {
        return $this->callLegacy('verifyCheckIn', [$token]);
    }

    public function checkOut($token): JsonResponse
    {
        return $this->callLegacy('checkOut', [$token]);
    }

    public function shiftCheckIns($id): JsonResponse
    {
        return $this->callLegacy('shiftCheckIns', [(int) $id]);
    }

    public function recurringPatterns($id): JsonResponse
    {
        return $this->callLegacy('recurringPatterns', [(int) $id]);
    }

    public function createRecurringPattern($id): JsonResponse
    {
        return $this->callLegacy('createRecurringPattern', [(int) $id]);
    }

    public function updateRecurringPattern($id): JsonResponse
    {
        return $this->callLegacy('updateRecurringPattern', [(int) $id]);
    }

    public function deleteRecurringPattern($id): JsonResponse
    {
        return $this->callLegacy('deleteRecurringPattern', [(int) $id]);
    }

    public function recommendedShifts(): JsonResponse
    {
        return $this->callLegacy('recommendedShifts');
    }

    public function myCertificates(): JsonResponse
    {
        return $this->callLegacy('myCertificates');
    }

    public function generateCertificate(): JsonResponse
    {
        return $this->callLegacy('generateCertificate');
    }

    public function verifyCertificate($code): JsonResponse
    {
        return $this->callLegacy('verifyCertificate', [$code]);
    }

    /** Delegates — raw HTML output */
    public function certificateHtml($code): JsonResponse
    {
        return $this->callLegacy('certificateHtml', [$code]);
    }

    public function myCredentials(): JsonResponse
    {
        return $this->callLegacy('myCredentials');
    }

    /** Delegates — $_FILES upload */
    public function uploadCredential(): JsonResponse
    {
        return $this->callLegacy('uploadCredential');
    }

    public function deleteCredential($id): JsonResponse
    {
        return $this->callLegacy('deleteCredential', [(int) $id]);
    }

    public function myExpenses(): JsonResponse
    {
        return $this->callLegacy('myExpenses');
    }

    public function submitExpense(): JsonResponse
    {
        return $this->callLegacy('submitExpense');
    }

    public function getExpense($id): JsonResponse
    {
        return $this->callLegacy('getExpense', [(int) $id]);
    }

    public function adminExpenses(): JsonResponse
    {
        return $this->callLegacy('adminExpenses');
    }

    public function reviewExpense($id): JsonResponse
    {
        return $this->callLegacy('reviewExpense', [(int) $id]);
    }

    /** Delegates — raw CSV output */
    public function exportExpenses(): JsonResponse
    {
        return $this->callLegacy('exportExpenses');
    }

    public function getExpensePolicies(): JsonResponse
    {
        return $this->callLegacy('getExpensePolicies');
    }

    public function updateExpensePolicy(): JsonResponse
    {
        return $this->callLegacy('updateExpensePolicy');
    }

    public function myGuardianConsents(): JsonResponse
    {
        return $this->callLegacy('myGuardianConsents');
    }

    public function requestGuardianConsent(): JsonResponse
    {
        return $this->callLegacy('requestGuardianConsent');
    }

    public function verifyGuardianConsent($token): JsonResponse
    {
        return $this->callLegacy('verifyGuardianConsent', [$token]);
    }

    public function withdrawGuardianConsent($id): JsonResponse
    {
        return $this->callLegacy('withdrawGuardianConsent', [(int) $id]);
    }

    public function adminGuardianConsents(): JsonResponse
    {
        return $this->callLegacy('adminGuardianConsents');
    }

    public function myTraining(): JsonResponse
    {
        return $this->callLegacy('myTraining');
    }

    public function recordTraining(): JsonResponse
    {
        return $this->callLegacy('recordTraining');
    }

    public function adminTraining(): JsonResponse
    {
        return $this->callLegacy('adminTraining');
    }

    public function verifyTraining($id): JsonResponse
    {
        return $this->callLegacy('verifyTraining', [(int) $id]);
    }

    public function rejectTraining($id): JsonResponse
    {
        return $this->callLegacy('rejectTraining', [(int) $id]);
    }

    public function reportIncident(): JsonResponse
    {
        return $this->callLegacy('reportIncident');
    }

    public function getIncidents(): JsonResponse
    {
        return $this->callLegacy('getIncidents');
    }

    public function getIncident($id): JsonResponse
    {
        return $this->callLegacy('getIncident', [(int) $id]);
    }

    public function adminIncidents(): JsonResponse
    {
        return $this->callLegacy('adminIncidents');
    }

    public function updateIncident($id): JsonResponse
    {
        return $this->callLegacy('updateIncident', [(int) $id]);
    }

    public function assignDlp($id): JsonResponse
    {
        return $this->callLegacy('assignDlp', [(int) $id]);
    }

    public function getCustomFields(): JsonResponse
    {
        return $this->callLegacy('getCustomFields');
    }

    public function adminCustomFields(): JsonResponse
    {
        return $this->callLegacy('adminCustomFields');
    }

    public function createCustomField(): JsonResponse
    {
        return $this->callLegacy('createCustomField');
    }

    public function updateCustomField($id): JsonResponse
    {
        return $this->callLegacy('updateCustomField', [(int) $id]);
    }

    public function deleteCustomField($id): JsonResponse
    {
        return $this->callLegacy('deleteCustomField', [(int) $id]);
    }

    public function myAccessibilityNeeds(): JsonResponse
    {
        return $this->callLegacy('myAccessibilityNeeds');
    }

    public function updateAccessibilityNeeds(): JsonResponse
    {
        return $this->callLegacy('updateAccessibilityNeeds');
    }

    public function getCommunityProjects(): JsonResponse
    {
        return $this->callLegacy('getCommunityProjects');
    }

    public function proposeCommunityProject(): JsonResponse
    {
        return $this->callLegacy('proposeCommunityProject');
    }

    public function getCommunityProject($id): JsonResponse
    {
        return $this->callLegacy('getCommunityProject', [(int) $id]);
    }

    public function updateCommunityProject($id): JsonResponse
    {
        return $this->callLegacy('updateCommunityProject', [(int) $id]);
    }

    public function supportCommunityProject($id): JsonResponse
    {
        return $this->callLegacy('supportCommunityProject', [(int) $id]);
    }

    public function unsupportCommunityProject($id): JsonResponse
    {
        return $this->callLegacy('unsupportCommunityProject', [(int) $id]);
    }

    public function reviewCommunityProject($id): JsonResponse
    {
        return $this->callLegacy('reviewCommunityProject', [(int) $id]);
    }

    public function getDonations(): JsonResponse
    {
        return $this->callLegacy('getDonations');
    }

    public function createDonation(): JsonResponse
    {
        return $this->callLegacy('createDonation');
    }

    public function getGivingDays(): JsonResponse
    {
        return $this->callLegacy('getGivingDays');
    }

    public function getGivingDayStats($id): JsonResponse
    {
        return $this->callLegacy('getGivingDayStats', [(int) $id]);
    }

    public function adminGivingDays(): JsonResponse
    {
        return $this->callLegacy('adminGivingDays');
    }

    public function createGivingDay(): JsonResponse
    {
        return $this->callLegacy('createGivingDay');
    }

    public function updateGivingDay($id): JsonResponse
    {
        return $this->callLegacy('updateGivingDay', [(int) $id]);
    }

    /** Delegates — raw CSV output */
    public function exportDonations(): JsonResponse
    {
        return $this->callLegacy('exportDonations');
    }

    public function getWebhooks(): JsonResponse
    {
        return $this->callLegacy('getWebhooks');
    }

    public function createWebhook(): JsonResponse
    {
        return $this->callLegacy('createWebhook');
    }

    public function updateWebhook($id): JsonResponse
    {
        return $this->callLegacy('updateWebhook', [(int) $id]);
    }

    public function deleteWebhook($id): JsonResponse
    {
        return $this->callLegacy('deleteWebhook', [(int) $id]);
    }

    public function testWebhook($id): JsonResponse
    {
        return $this->callLegacy('testWebhook', [(int) $id]);
    }

    public function getWebhookLogs($id): JsonResponse
    {
        return $this->callLegacy('getWebhookLogs', [(int) $id]);
    }

    public function getReminderSettings(): JsonResponse
    {
        return $this->callLegacy('getReminderSettings');
    }

    public function updateReminderSettings(): JsonResponse
    {
        return $this->callLegacy('updateReminderSettings');
    }

    public function myEmergencyAlerts(): JsonResponse
    {
        return $this->callLegacy('myEmergencyAlerts');
    }

    public function createEmergencyAlert(): JsonResponse
    {
        return $this->callLegacy('createEmergencyAlert');
    }

    public function respondToEmergencyAlert($id): JsonResponse
    {
        return $this->callLegacy('respondToEmergencyAlert', [(int) $id]);
    }

    public function cancelEmergencyAlert($id): JsonResponse
    {
        return $this->callLegacy('cancelEmergencyAlert', [(int) $id]);
    }

    public function wellbeingDashboard(): JsonResponse
    {
        return $this->callLegacy('wellbeingDashboard');
    }

    public function wellbeingCheckin(): JsonResponse
    {
        return $this->callLegacy('wellbeingCheckin');
    }

    public function myWellbeingStatus(): JsonResponse
    {
        return $this->callLegacy('myWellbeingStatus');
    }

    /** Legacy V1 list endpoint */
    public function index(): JsonResponse
    {
        return $this->callLegacy('index', [], \Nexus\Controllers\Api\VolunteeringApiController::class);
    }

    // ========================================
    // Delegation helper
    // ========================================

    /**
     * Call legacy controller method via output buffering.
     * Used for methods that use getJsonInput(), raw output, $_FILES, or complex DB patterns
     * that aren't worth converting inline.
     */
    private function callLegacy(string $method, array $params = [], ?string $legacyClass = null): JsonResponse
    {
        $class = $legacyClass ?? \Nexus\Controllers\Api\VolunteerApiController::class;
        $controller = new $class();
        ob_start();
        try {
            $controller->$method(...$params);
        } catch (\Throwable $e) {
            ob_end_clean();
            return $this->respondWithError('INTERNAL_ERROR', $e->getMessage(), null, 500);
        }
        $output = ob_get_clean();
        $status = http_response_code() ?: 200;
        return response()->json(json_decode($output, true) ?: $output, $status);
    }
}
