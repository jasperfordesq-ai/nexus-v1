<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\CommunityProjectService;
use App\Services\GuardianConsentService;
use App\Services\RecurringShiftService;
use App\Services\ShiftGroupReservationService;
use App\Services\ShiftSwapService;
use App\Services\ShiftWaitlistService;
use App\Services\VolunteerCertificateService;
use App\Services\VolunteerDonationService;
use App\Services\VolunteerEmergencyAlertService;
use App\Services\VolunteerExpenseService;
use App\Services\VolunteerFormService;
use App\Services\VolunteerService;
use App\Services\SafeguardingService;
use App\Services\VolunteerCheckInService;
use App\Services\VolunteerWellbeingService;
use App\Services\WebhookDispatchService;
use Nexus\Core\TenantContext;
use Nexus\Services\VolunteerService as LegacyVolunteerService;

/**
 * VolunteerController -- Volunteering opportunities, applications, shifts, hours, and organisations.
 *
 * All methods call services directly — no legacy delegation remaining.
 */
class VolunteerController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly VolunteerService $volunteerService,
        private readonly ShiftWaitlistService $shiftWaitlistService,
        private readonly ShiftSwapService $shiftSwapService,
        private readonly ShiftGroupReservationService $shiftGroupReservationService,
        private readonly RecurringShiftService $recurringShiftService,
        private readonly VolunteerCertificateService $volunteerCertificateService,
        private readonly VolunteerExpenseService $volunteerExpenseService,
        private readonly GuardianConsentService $guardianConsentService,
        private readonly VolunteerFormService $volunteerFormService,
        private readonly CommunityProjectService $communityProjectService,
        private readonly VolunteerDonationService $volunteerDonationService,
        private readonly WebhookDispatchService $webhookDispatchService,
        private readonly VolunteerEmergencyAlertService $volunteerEmergencyAlertService,
        private readonly VolunteerWellbeingService $volunteerWellbeingService,
        private readonly SafeguardingService $safeguardingService,
        private readonly VolunteerCheckInService $volunteerCheckInService,
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

        $entryId = $this->shiftWaitlistService->join((int) $id, $userId);
        if ($entryId === null) {
            $errors = $this->shiftWaitlistService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        $position = $this->shiftWaitlistService->getUserPosition((int) $id, $userId);
        return $this->respondWithData(['id' => $entryId, 'position' => $position['position'] ?? 1, 'message' => 'Successfully joined the waitlist'], null, 201);
    }

    public function leaveWaitlist($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_waitlist_leave', 20, 60);

        $success = $this->shiftWaitlistService->leave((int) $id, $userId);
        if (!$success) {
            $errors = $this->shiftWaitlistService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->noContent();
    }

    public function promoteFromWaitlist($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_waitlist_promote', 10, 60);

        $tenantId = TenantContext::getId();
        $success = $this->shiftWaitlistService->promoteUser((int) $id, $tenantId);
        if (!$success) {
            $errors = $this->shiftWaitlistService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['message' => 'Successfully claimed the shift spot']);
    }

    public function myWaitlists(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_waitlists_list', 60, 60);
        $tenantId = TenantContext::getId();
        $entries = $this->shiftWaitlistService->getUserWaitlists($userId, $tenantId);
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

        $swapId = $this->shiftSwapService->requestSwap($userId, $data);
        if ($swapId === null) {
            $errors = $this->shiftSwapService->getErrors();
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
        $requests = $this->shiftSwapService->getSwapRequests($userId, $direction);
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

        $success = $this->shiftSwapService->respond((int) $id, $userId, $action);
        if (!$success) {
            $errors = $this->shiftSwapService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['id' => (int) $id, 'status' => $action === 'accept' ? 'accepted' : 'rejected']);
    }

    public function cancelSwap($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_swap_cancel', 20, 60);

        $tenantId = TenantContext::getId();
        $success = $this->shiftSwapService->cancel((int) $id, $userId, $tenantId);
        if (!$success) {
            $errors = $this->shiftSwapService->getErrors();
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

        $reservationId = $this->shiftGroupReservationService->reserve((int) $id, $groupId, $userId, $slots, $notes ?: null);
        if ($reservationId === null) {
            $errors = $this->shiftGroupReservationService->getErrors();
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

        $success = $this->shiftGroupReservationService->addMember((int) $id, $memberUserId, $leaderId);
        if (!$success) {
            $errors = $this->shiftGroupReservationService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->respondWithData(['message' => 'Member added to group reservation']);
    }

    public function removeGroupMember($id, $userId): JsonResponse
    {
        $this->ensureFeature();
        $leaderId = $this->getUserId();
        $this->rateLimit('volunteering_group_member_remove', 20, 60);

        $success = $this->shiftGroupReservationService->removeMember((int) $id, (int) $userId, $leaderId);
        if (!$success) {
            $errors = $this->shiftGroupReservationService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->noContent();
    }

    public function cancelGroupReservation($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_group_cancel', 10, 60);

        $success = $this->shiftGroupReservationService->cancelReservation((int) $id, $userId);
        if (!$success) {
            $errors = $this->shiftGroupReservationService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }
        return $this->noContent();
    }

    public function myGroupReservations(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_group_reservations_list', 60, 60);
        $tenantId = TenantContext::getId();
        $reservations = $this->shiftGroupReservationService->getUserReservations($userId, $tenantId);
        return $this->respondWithData($reservations);
    }

    // ========================================
    // CHECK-IN (V7) — VolunteerCheckInService
    // ========================================

    public function getCheckIn($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_checkin_get', 60, 60);
        $shiftId = (int) $id;

        $tenantId = TenantContext::getId();
        $checkin = $this->volunteerCheckInService->getUserCheckIn($userId, $shiftId, $tenantId);

        if (!$checkin) {
            $token = $this->volunteerCheckInService->generateToken($shiftId, $tenantId);
            if ($token) {
                $checkin = $this->volunteerCheckInService->getUserCheckIn($userId, $shiftId, $tenantId);
            }
        }

        if (!$checkin) {
            return $this->respondWithError('NOT_FOUND', 'No check-in available for this shift', null, 404);
        }

        return $this->respondWithData($checkin);
    }

    public function verifyCheckIn($token): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_checkin_verify', 30, 60);

        $tenantId = TenantContext::getId();
        $shiftId = $this->volunteerCheckInService->getShiftIdByToken($token, $tenantId);
        if ($shiftId === null) {
            return $this->respondWithError('NOT_FOUND', 'Invalid check-in code', null, 404);
        }

        if (!$this->canManageShift($shiftId, $userId)) {
            return $this->respondWithError('FORBIDDEN', 'You do not have permission to verify check-ins for this shift', null, 403);
        }

        $result = $this->volunteerCheckInService->verifyCheckIn(['token' => $token], $tenantId);

        if ($result === false) {
            return $this->respondWithError('NOT_FOUND', 'Check-in not found or already completed', null, 404);
        }

        return $this->respondWithData($result);
    }

    public function checkOut($token): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_checkout', 30, 60);

        $tenantId = TenantContext::getId();
        $shiftId = $this->volunteerCheckInService->getShiftIdByToken($token, $tenantId);
        if ($shiftId === null) {
            return $this->respondWithError('NOT_FOUND', 'Invalid check-in code', null, 404);
        }

        if (!$this->canManageShift($shiftId, $userId)) {
            return $this->respondWithError('FORBIDDEN', 'You do not have permission to check out volunteers for this shift', null, 403);
        }

        $checkinUserId = $this->volunteerCheckInService->getUserIdByToken($token);
        $success = \Nexus\Services\VolunteerCheckInService::checkOut($token);

        if (!$success) {
            $errors = \Nexus\Services\VolunteerCheckInService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }

        if ($checkinUserId) {
            try {
                $this->webhookDispatchService->dispatch('shift.completed', [
                    'user_id' => $checkinUserId,
                    'shift_id' => $shiftId,
                ]);
            } catch (\Throwable $e) {
                error_log("Webhook dispatch failed for shift.completed: " . $e->getMessage());
            }
        }

        return $this->respondWithData(['message' => 'Successfully checked out']);
    }

    public function shiftCheckIns($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_shift_checkins', 60, 60);
        $shiftId = (int) $id;

        if (!$this->canManageShift($shiftId, $userId)) {
            return $this->respondWithError('FORBIDDEN', 'You do not have permission to view check-ins for this shift', null, 403);
        }

        $tenantId = TenantContext::getId();
        $checkins = $this->volunteerCheckInService->getShiftCheckIns($shiftId, $tenantId);
        return $this->respondWithData(['checkins' => $checkins]);
    }

    // ========================================
    // RECURRING SHIFTS (V8) — RecurringShiftService
    // ========================================

    public function recurringPatterns($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_recurring_list', 60, 60);
        $oppId = (int) $id;

        $patterns = $this->recurringShiftService->getPatternsForOpportunity($oppId, $userId);

        $errors = $this->recurringShiftService->getErrors();
        if (!empty($errors)) {
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }

        return $this->respondWithData(['patterns' => $patterns]);
    }

    public function createRecurringPattern($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_recurring_create', 10, 60);
        $oppId = (int) $id;

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

        $patternId = $this->recurringShiftService->createPattern($oppId, $userId, $data);

        if ($patternId === null) {
            $errors = $this->recurringShiftService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }

        $pattern = \Nexus\Services\RecurringShiftService::getPattern($patternId);
        return $this->respondWithData($pattern, null, 201);
    }

    public function updateRecurringPattern($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_recurring_update', 10, 60);
        $patternId = (int) $id;

        $success = \Nexus\Services\RecurringShiftService::updatePattern($patternId, $this->getAllInput(), $userId);

        if (!$success) {
            $errors = \Nexus\Services\RecurringShiftService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }

        $pattern = \Nexus\Services\RecurringShiftService::getPattern($patternId);
        return $this->respondWithData($pattern);
    }

    public function deleteRecurringPattern($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_recurring_delete', 10, 60);
        $patternId = (int) $id;

        $deactivated = \Nexus\Services\RecurringShiftService::deactivatePattern($patternId, $userId);

        if (!$deactivated) {
            $errors = \Nexus\Services\RecurringShiftService::getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }

        $deleted = \Nexus\Services\RecurringShiftService::deleteFutureShifts($patternId, $userId);

        return $this->respondWithData([
            'message' => 'Recurring pattern deactivated',
            'future_shifts_removed' => $deleted,
        ]);
    }

    // ========================================
    // RECOMMENDED SHIFTS (V4) — VolunteerMatchingService
    // ========================================

    public function recommendedShifts(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_recommended', 30, 60);

        $shifts = \Nexus\Services\VolunteerMatchingService::getRecommendedShifts($userId, [
            'limit' => $this->queryInt('limit', 10, 1, 20),
            'min_match_score' => $this->queryInt('min_score', 20, 0, 100),
        ]);

        return $this->respondWithData(['shifts' => $shifts]);
    }

    // ========================================
    // CERTIFICATES (V6) — VolunteerCertificateService
    // ========================================

    public function myCertificates(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_certificates_list', 30, 60);

        $certs = $this->volunteerCertificateService->getUserCertificates($userId);
        return $this->respondWithData(['certificates' => $certs]);
    }

    public function generateCertificate(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_certificate', 5, 60);

        $options = [];
        if ($this->inputInt('organization_id')) {
            $options['organization_id'] = $this->inputInt('organization_id');
        }

        $cert = $this->volunteerCertificateService->generate($userId, $options);

        if ($cert === null) {
            $errors = $this->volunteerCertificateService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }

        return $this->respondWithData($cert, null, 201);
    }

    public function verifyCertificate($code): JsonResponse
    {
        $this->rateLimit('volunteering_cert_verify', 60, 60);

        $cert = $this->volunteerCertificateService->verify($code);

        if ($cert === null) {
            return $this->respondWithError('NOT_FOUND', 'Certificate not found or invalid', null, 404);
        }

        return $this->respondWithData($cert);
    }

    /** Returns raw HTML for certificate printing/PDF — not JSON */
    public function certificateHtml($code): Response|JsonResponse
    {
        $this->rateLimit('volunteering_cert_html', 10, 60);

        $html = $this->volunteerCertificateService->generateHtml($code);

        if ($html === null) {
            return $this->respondWithError('NOT_FOUND', 'Certificate not found', null, 404);
        }

        \Nexus\Services\VolunteerCertificateService::markDownloaded($code);

        return response($html, 200)->header('Content-Type', 'text/html; charset=utf-8');
    }

    // ========================================
    // CREDENTIALS — direct DB (vol_credentials)
    // ========================================

    public function myCredentials(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_credentials', 30, 60);

        $tenantId = TenantContext::getId();

        $credentials = DB::select(
            "SELECT id, credential_type, file_url, file_name, status, expires_at, created_at, updated_at
             FROM vol_credentials
             WHERE user_id = ? AND tenant_id = ?
             ORDER BY created_at DESC",
            [$userId, $tenantId]
        );

        $mapped = array_map(static function ($row): array {
            $type = (string) ($row->credential_type ?? '');
            $typeLabel = ucwords(str_replace('_', ' ', $type));

            return [
                'id' => (int) ($row->id ?? 0),
                'credential_type' => $type,
                'file_url' => $row->file_url ?? null,
                'file_name' => $row->file_name ?? null,
                'status' => $row->status ?? 'pending',
                'expires_at' => $row->expires_at ?? null,
                'created_at' => $row->created_at ?? null,
                'updated_at' => $row->updated_at ?? null,
                'type' => $type,
                'type_label' => $typeLabel,
                'document_name' => $row->file_name ?? null,
                'upload_date' => $row->created_at ?? null,
                'expiry_date' => $row->expires_at ?? null,
                'rejection_reason' => null,
            ];
        }, $credentials);

        return $this->respondWithData(['credentials' => $mapped]);
    }

    public function uploadCredential(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_credential_upload', 10, 60);

        $tenantId = TenantContext::getId();
        $type = trim((string) ($this->input('credential_type') ?? $this->input('type') ?? ''));
        $expiresAt = $this->input('expires_at') ?? $this->input('expiry_date');

        if (empty($type)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Credential type is required', 'credential_type');
        }

        // Support both Laravel UploadedFile and raw $_FILES
        $file = request()->file('file') ?? request()->file('document');
        $uploadedFile = null;

        if ($file) {
            // Laravel UploadedFile path
            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            if (!in_array($file->getMimeType(), $allowedMimes, true)) {
                return $this->respondWithError('VALIDATION_ERROR', 'Only PDF, JPEG, PNG, and WebP files are allowed', 'file');
            }
            if ($file->getSize() > 10 * 1024 * 1024) {
                return $this->respondWithError('VALIDATION_ERROR', 'File size must be under 10 MB', 'file');
            }
            // Build $_FILES-compatible array for ImageUploader
            $uploadedFile = [
                'tmp_name' => $file->getRealPath(),
                'name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'type' => $file->getMimeType(),
                'error' => UPLOAD_ERR_OK,
            ];
        } else {
            // Fallback to raw $_FILES
            $uploadedFile = $_FILES['file'] ?? $_FILES['document'] ?? null;
            if (empty($uploadedFile) || !isset($uploadedFile['error']) || $uploadedFile['error'] !== UPLOAD_ERR_OK) {
                return $this->respondWithError('VALIDATION_ERROR', 'A credential file is required', 'file');
            }
            $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($uploadedFile['tmp_name']);
            if (!in_array($mimeType, $allowedMimes, true)) {
                return $this->respondWithError('VALIDATION_ERROR', 'Only PDF, JPEG, PNG, and WebP files are allowed', 'file');
            }
            if (($uploadedFile['size'] ?? 0) > 10 * 1024 * 1024) {
                return $this->respondWithError('VALIDATION_ERROR', 'File size must be under 10 MB', 'file');
            }
        }

        $fileUrl = \Nexus\Core\ImageUploader::upload($uploadedFile, 'credentials');
        $fileName = $uploadedFile['name'] ?? null;

        DB::insert(
            "INSERT INTO vol_credentials (tenant_id, user_id, credential_type, file_url, file_name, status, expires_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW(), NOW())",
            [$tenantId, $userId, $type, $fileUrl, $fileName, $expiresAt ?: null]
        );

        return $this->respondWithData([
            'success' => true,
            'id' => (int) DB::getPdo()->lastInsertId(),
        ], null, 201);
    }

    public function deleteCredential($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_credential_delete', 10, 60);

        $tenantId = TenantContext::getId();
        $affected = DB::delete(
            "DELETE FROM vol_credentials WHERE id = ? AND user_id = ? AND tenant_id = ?",
            [(int) $id, $userId, $tenantId]
        );

        if ($affected === 0) {
            return $this->respondWithError('NOT_FOUND', 'Credential not found', null, 404);
        }

        return $this->respondWithData(['success' => true]);
    }

    // ========================================
    // EXPENSES (V11) — VolunteerExpenseService
    // ========================================

    public function myExpenses(): JsonResponse
    {
        $this->ensureFeature();
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

        $result = $this->volunteerExpenseService->getExpenses($filters);
        return $this->respondWithData($result);
    }

    public function submitExpense(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_expense_submit', 10, 60);

        $data = $this->getAllInput();
        $result = $this->volunteerExpenseService->submitExpense($userId, $data);

        if (isset($result['error'])) {
            return $this->respondWithError('VALIDATION_ERROR', $result['error'], null, 422);
        }

        return $this->respondWithData($result, null, 201);
    }

    public function getExpense($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_expense_get', 30, 60);

        $expense = $this->volunteerExpenseService->getExpense((int) $id);
        if (!$expense || (int) $expense['user_id'] !== $userId) {
            return $this->respondWithError('NOT_FOUND', 'Expense not found', null, 404);
        }

        return $this->respondWithData($expense);
    }

    public function adminExpenses(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();
        $this->rateLimit('vol_admin_expenses', 30, 60);

        $filters = [
            'status' => $this->query('status'),
            'user_id' => $this->query('user_id') ? (int) $this->query('user_id') : null,
            'organization_id' => $this->query('organization_id') ? (int) $this->query('organization_id') : null,
            'date_from' => $this->query('date_from'),
            'date_to' => $this->query('date_to'),
            'cursor' => $this->query('cursor'),
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        $result = $this->volunteerExpenseService->getExpenses($filters);
        return $this->respondWithData($result);
    }

    public function reviewExpense($id): JsonResponse
    {
        $this->ensureFeature();
        $adminId = $this->requireAdmin();
        $this->rateLimit('vol_expense_review', 30, 60);

        $data = $this->getAllInput();
        $status = $data['status'] ?? '';

        $allowedStatuses = ['approved', 'rejected', 'paid'];
        if (!in_array($status, $allowedStatuses, true)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid status. Must be one of: ' . implode(', ', $allowedStatuses), 'status', 422);
        }

        if ($status === 'paid') {
            $result = $this->volunteerExpenseService->markPaid((int) $id, $adminId, $data['payment_reference'] ?? null);
        } else {
            $result = $this->volunteerExpenseService->reviewExpense((int) $id, $adminId, $status, $data['review_notes'] ?? null);
        }

        if (!$result) {
            return $this->respondWithError('NOT_FOUND', 'Expense not found or invalid status', null, 404);
        }

        return $this->respondWithData(['success' => true]);
    }

    /** Returns raw CSV for expense export */
    public function exportExpenses(): Response
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $filters = [
            'status' => $this->query('status'),
            'date_from' => $this->query('date_from'),
            'date_to' => $this->query('date_to'),
        ];

        $csv = \Nexus\Services\VolunteerExpenseService::exportExpenses($filters);

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="volunteer_expenses_' . date('Y-m-d') . '.csv"');
    }

    public function getExpensePolicies(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $orgId = $this->query('organization_id') ? (int) $this->query('organization_id') : null;
        $policies = \Nexus\Services\VolunteerExpenseService::getPolicies($orgId);
        return $this->respondWithData($policies);
    }

    public function updateExpensePolicy(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();
        $this->rateLimit('vol_expense_policy_update', 10, 60);

        $data = $this->getAllInput();

        if (empty($data['expense_type'])) {
            return $this->respondWithError('VALIDATION_ERROR', 'expense_type is required', 'expense_type', 422);
        }

        $policyFields = ['max_amount', 'requires_receipt', 'auto_approve_below', 'description', 'enabled'];
        $hasPolicyField = false;
        foreach ($policyFields as $field) {
            if (array_key_exists($field, $data)) {
                $hasPolicyField = true;
                break;
            }
        }
        if (!$hasPolicyField) {
            return $this->respondWithError('VALIDATION_ERROR', 'At least one policy field is required (e.g., max_amount, requires_receipt, auto_approve_below, description, enabled)', null, 422);
        }

        $result = \Nexus\Services\VolunteerExpenseService::updatePolicy($data);
        return $this->respondWithData(['success' => $result]);
    }

    // ========================================
    // GUARDIAN CONSENTS — GuardianConsentService
    // ========================================

    public function myGuardianConsents(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_guardian_consents', 30, 60);

        $consents = $this->guardianConsentService->getConsentsForMinor($userId);
        return $this->respondWithData($consents);
    }

    public function requestGuardianConsent(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_guardian_consent_request', 5, 60);

        $data = $this->getAllInput();
        $opportunityId = isset($data['opportunity_id']) ? (int) $data['opportunity_id'] : null;

        try {
            $result = $this->guardianConsentService->requestConsent($userId, $data, $opportunityId);
            return $this->respondWithData($result, null, 201);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /** Public endpoint — no auth required */
    public function verifyGuardianConsent($token): JsonResponse
    {
        $this->rateLimit('guardian_consent_verify', 10, 300);

        $ip = request()->ip() ?? '0.0.0.0';
        $result = $this->guardianConsentService->grantConsent($token, $ip);

        if (!$result) {
            return $this->respondWithError('INVALID_TOKEN', 'Consent token is invalid or expired', null, 400);
        }

        return $this->respondWithData(['success' => true, 'message' => 'Guardian consent has been granted successfully.']);
    }

    public function withdrawGuardianConsent($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_guardian_consent_withdraw', 10, 60);

        $result = $this->guardianConsentService->withdrawConsent((int) $id, $userId);
        if (!$result) {
            return $this->respondWithError('NOT_FOUND', 'Consent not found', null, 404);
        }

        return $this->respondWithData(['success' => true]);
    }

    public function adminGuardianConsents(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $filters = [
            'status' => $this->query('status'),
            'search' => $this->query('search'),
        ];

        $consents = \Nexus\Services\GuardianConsentService::getConsentsForAdmin($filters);
        return $this->respondWithData($consents);
    }

    // ========================================
    // TRAINING — SafeguardingService
    // ========================================

    public function myTraining(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_training_list', 30, 60);

        $tenantId = TenantContext::getId();
        $training = $this->safeguardingService->getTrainingForUser($userId, $tenantId);
        return $this->respondWithData($training);
    }

    public function recordTraining(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_training_record', 10, 60);

        $data = $this->getAllInput();

        try {
            $tenantId = TenantContext::getId();
            $result = $this->safeguardingService->recordTraining($userId, $data, $tenantId);
            return $this->respondWithData($result, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    public function adminTraining(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $filters = [
            'status' => $this->query('status'),
            'training_type' => $this->query('training_type'),
            'user_id' => $this->query('user_id') ? (int) $this->query('user_id') : null,
            'cursor' => $this->query('cursor'),
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        $tenantId = TenantContext::getId();
        $result = $this->safeguardingService->getTrainingForAdmin($tenantId);
        return $this->respondWithData($result);
    }

    public function verifyTraining($id): JsonResponse
    {
        $this->ensureFeature();
        $adminId = $this->requireAdmin();

        $tenantId = TenantContext::getId();
        $result = $this->safeguardingService->verifyTraining((int) $id, $adminId, $tenantId);
        if (!$result) {
            return $this->respondWithError('NOT_FOUND', 'Training record not found', null, 404);
        }
        return $this->respondWithData(['success' => true]);
    }

    public function rejectTraining($id): JsonResponse
    {
        $this->ensureFeature();
        $adminId = $this->requireAdmin();

        $tenantId = TenantContext::getId();
        $result = $this->safeguardingService->rejectTraining((int) $id, $adminId, '', $tenantId);
        if (!$result) {
            return $this->respondWithError('NOT_FOUND', 'Training record not found', null, 404);
        }
        return $this->respondWithData(['success' => true]);
    }

    // ========================================
    // INCIDENTS — SafeguardingService
    // ========================================

    public function reportIncident(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_incident_report', 5, 60);

        $data = $this->getAllInput();

        try {
            $tenantId = TenantContext::getId();
            $result = $this->safeguardingService->reportIncident($userId, $data, $tenantId);
            return $this->respondWithData($result, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    public function getIncidents(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_incidents_list', 30, 60);

        $filters = [
            'reported_by' => $userId,
            'status' => $this->query('status'),
        ];

        $result = \Nexus\Services\SafeguardingService::getIncidents($filters);
        return $this->respondWithData($result);
    }

    public function getIncident($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_incident_get', 30, 60);

        $tenantId = TenantContext::getId();
        $incident = $this->safeguardingService->getIncident((int) $id, $tenantId);
        if (!$incident) {
            return $this->respondWithError('NOT_FOUND', 'Incident not found', null, 404);
        }

        // Ownership check: only the reporter or an admin can view
        $user = Auth::user();
        $role = $user->role ?? 'member';
        $isAdmin = in_array($role, ['admin', 'tenant_admin', 'super_admin', 'god'], true);
        if ((int) ($incident['reported_by'] ?? 0) !== $userId && !$isAdmin) {
            return $this->respondWithError('FORBIDDEN', 'You do not have permission to view this incident', null, 403);
        }

        return $this->respondWithData($incident);
    }

    public function adminIncidents(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $filters = [
            'status' => $this->query('status'),
            'severity' => $this->query('severity'),
            'organization_id' => $this->query('organization_id') ? (int) $this->query('organization_id') : null,
            'cursor' => $this->query('cursor'),
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        $result = \Nexus\Services\SafeguardingService::getIncidents($filters);
        return $this->respondWithData($result);
    }

    public function updateIncident($id): JsonResponse
    {
        $this->ensureFeature();
        $adminId = $this->requireAdmin();

        $data = $this->getAllInput();
        $tenantId = TenantContext::getId();
        $result = $this->safeguardingService->updateIncident((int) $id, $data, $adminId, $tenantId);

        if (!$result) {
            return $this->respondWithError('NOT_FOUND', 'Incident not found', null, 404);
        }
        return $this->respondWithData(['success' => true]);
    }

    public function assignDlp($id): JsonResponse
    {
        $this->ensureFeature();
        $adminId = $this->requireAdmin();

        $data = $this->getAllInput();

        $dlpUserId = (int) ($data['dlp_user_id'] ?? 0);
        if ($dlpUserId <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', 'dlp_user_id is required and must be a positive integer', 'dlp_user_id', 422);
        }

        $tenantId = TenantContext::getId();
        $result = $this->safeguardingService->assignDlp(
            (int) $id,
            $dlpUserId,
            $adminId,
            $tenantId
        );

        return $this->respondWithData(['success' => $result]);
    }

    // ========================================
    // CUSTOM FIELDS — VolunteerFormService
    // ========================================

    /** Public endpoint — custom fields needed for application forms */
    public function getCustomFields(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('vol_public_read', 60, 30);

        $orgId = $this->query('organization_id') ? (int) $this->query('organization_id') : null;
        $appliesTo = $this->query('applies_to') ?: 'application';

        $fields = $this->volunteerFormService->getCustomFields($orgId, $appliesTo);
        return $this->respondWithData($fields);
    }

    public function adminCustomFields(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $orgId = $this->query('organization_id') ? (int) $this->query('organization_id') : null;
        $fields = $this->volunteerFormService->getCustomFields($orgId);
        return $this->respondWithData($fields);
    }

    public function createCustomField(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();
        $this->rateLimit('vol_custom_field_create', 10, 60);

        $data = $this->getAllInput();

        if (empty($data['field_label'])) {
            return $this->respondWithError('VALIDATION_ERROR', 'field_label is required', 'field_label', 422);
        }

        try {
            $result = $this->volunteerFormService->createField($data);
            return $this->respondWithData($result, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\Exception $e) {
            error_log("VolunteerController::createCustomField error: " . $e->getMessage());
            return $this->respondWithError('INTERNAL_ERROR', 'Failed to create custom field', null, 500);
        }
    }

    public function updateCustomField($id): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $data = $this->getAllInput();
        $result = $this->volunteerFormService->updateField((int) $id, $data);

        if (!$result) {
            return $this->respondWithError('NOT_FOUND', 'Custom field not found', null, 404);
        }

        return $this->respondWithData(['success' => true]);
    }

    public function deleteCustomField($id): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $result = $this->volunteerFormService->deleteField((int) $id);

        if (!$result) {
            return $this->respondWithError('NOT_FOUND', 'Custom field not found', null, 404);
        }

        return $this->respondWithData(['success' => true]);
    }

    // ========================================
    // ACCESSIBILITY — VolunteerFormService
    // ========================================

    public function myAccessibilityNeeds(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();

        $tenantId = TenantContext::getId();
        $needs = $this->volunteerFormService->getAccessibilityNeeds($userId, $tenantId);
        return $this->respondWithData($needs);
    }

    public function updateAccessibilityNeeds(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();

        $data = $this->getAllInput();
        $tenantId = TenantContext::getId();
        $this->volunteerFormService->updateAccessibilityNeeds($userId, $data['needs'] ?? [], $tenantId);
        return $this->respondWithData(['success' => true]);
    }

    // ========================================
    // COMMUNITY PROJECTS — CommunityProjectService
    // ========================================

    /** Public endpoint — community project listings */
    public function getCommunityProjects(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('vol_public_read', 60, 30);

        $filters = [
            'status' => $this->query('status') ?: 'proposed',
            'category' => $this->query('category'),
            'search' => $this->query('search'),
            'sort' => $this->query('sort') ?: 'newest',
            'cursor' => $this->query('cursor'),
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        $result = $this->communityProjectService->getProposals($filters);
        return $this->respondWithData($result);
    }

    public function proposeCommunityProject(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_project_propose', 5, 60);

        $data = $this->getAllInput();

        try {
            $result = $this->communityProjectService->propose($userId, $data);
            return $this->respondWithData($result, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /** Public endpoint — individual project pages */
    public function getCommunityProject($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('vol_public_read', 60, 30);

        $project = $this->communityProjectService->getProposal((int) $id);
        if (!$project) {
            return $this->respondWithError('NOT_FOUND', 'Project not found', null, 404);
        }
        return $this->respondWithData($project);
    }

    public function updateCommunityProject($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();

        $data = $this->getAllInput();
        $result = $this->communityProjectService->updateProposal((int) $id, $userId, $data);

        if (!$result) {
            return $this->respondWithError('FORBIDDEN', 'Cannot update this project', null, 403);
        }
        return $this->respondWithData(['success' => true]);
    }

    public function supportCommunityProject($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('vol_project_support', 30, 60);

        $data = $this->getAllInput();
        $tenantId = TenantContext::getId();
        $result = $this->communityProjectService->support((int) $id, $userId, $tenantId);
        return $this->respondWithData(['success' => $result]);
    }

    public function unsupportCommunityProject($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();

        $tenantId = TenantContext::getId();
        $result = $this->communityProjectService->unsupport((int) $id, $userId, $tenantId);
        return $this->respondWithData(['success' => $result]);
    }

    public function reviewCommunityProject($id): JsonResponse
    {
        $this->ensureFeature();
        $adminId = $this->requireAdmin();

        $data = $this->getAllInput();

        try {
            $tenantId = TenantContext::getId();
            $result = $this->communityProjectService->review(
                (int) $id,
                $data['status'] ?? '',
                $data['notes'] ?? null,
                $adminId,
                $tenantId
            );
            return $this->respondWithData(['success' => $result]);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    // ========================================
    // DONATIONS — VolunteerDonationService
    // ========================================

    /** Public endpoint — donation listings */
    public function getDonations(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('vol_public_read', 60, 30);

        $filters = [
            'opportunity_id' => $this->query('opportunity_id') ? (int) $this->query('opportunity_id') : null,
            'community_project_id' => $this->query('community_project_id') ? (int) $this->query('community_project_id') : null,
            'cursor' => $this->query('cursor'),
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        $result = $this->volunteerDonationService->getDonations($filters);
        return $this->respondWithData($result);
    }

    public function createDonation(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getOptionalUserId();
        $this->rateLimit('vol_donation_create', 10, 60);

        $data = $this->getAllInput();
        if ($userId) {
            $data['user_id'] = $userId;
        }

        try {
            $result = $this->volunteerDonationService->createDonation($userId ?: 0, $data);
            return $this->respondWithData($result, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /** Returns raw CSV for donation export */
    public function exportDonations(): Response
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $filters = [
            'date_from' => $this->query('date_from'),
            'date_to' => $this->query('date_to'),
        ];

        $csv = \Nexus\Services\VolunteerDonationService::exportDonations($filters);

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="volunteer_donations_' . date('Y-m-d') . '.csv"');
    }

    // ========================================
    // GIVING DAYS — VolunteerDonationService
    // ========================================

    public function getGivingDays(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('vol_giving_days', 30, 60);

        $result = $this->volunteerDonationService->getGivingDays();
        return $this->respondWithData($result);
    }

    /** Public endpoint — giving day stats for fundraising pages */
    public function getGivingDayStats($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('vol_public_read', 60, 30);

        $stats = $this->volunteerDonationService->getGivingDayStats((int) $id);
        if (!$stats) {
            return $this->respondWithError('NOT_FOUND', 'Giving day not found', null, 404);
        }
        return $this->respondWithData($stats);
    }

    public function adminGivingDays(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $result = $this->volunteerDonationService->adminGetGivingDays();
        return $this->respondWithData($result);
    }

    public function createGivingDay(): JsonResponse
    {
        $this->ensureFeature();
        $adminId = $this->requireAdmin();

        $data = $this->getAllInput();

        try {
            $tenantId = TenantContext::getId();
            $data['created_by'] = $adminId;
            $result = $this->volunteerDonationService->createGivingDay($data, $tenantId);
            return $this->respondWithData($result, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    public function updateGivingDay($id): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $data = $this->getAllInput();
        $tenantId = TenantContext::getId();
        $result = $this->volunteerDonationService->updateGivingDay((int) $id, $data, $tenantId);
        return $this->respondWithData(['success' => $result]);
    }

    // ========================================
    // WEBHOOKS — WebhookDispatchService
    // ========================================

    public function getWebhooks(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $webhooks = $this->webhookDispatchService->getWebhooks();
        return $this->respondWithData($webhooks);
    }

    public function createWebhook(): JsonResponse
    {
        $this->ensureFeature();
        $adminId = $this->requireAdmin();

        $data = $this->getAllInput();

        try {
            $result = $this->webhookDispatchService->createWebhook($adminId, $data);
            return $this->respondWithData($result, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    public function updateWebhook($id): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $data = $this->getAllInput();
        $result = $this->webhookDispatchService->updateWebhook((int) $id, $data);
        return $this->respondWithData(['success' => $result]);
    }

    public function deleteWebhook($id): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $result = $this->webhookDispatchService->deleteWebhook((int) $id);
        return $this->respondWithData(['success' => $result]);
    }

    public function testWebhook($id): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $result = \Nexus\Services\WebhookDispatchService::testWebhook((int) $id);
        return $this->respondWithData($result);
    }

    public function getWebhookLogs($id): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $filters = [
            'cursor' => $this->query('cursor'),
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        $logs = \Nexus\Services\WebhookDispatchService::getLogs((int) $id, $filters);
        return $this->respondWithData($logs);
    }

    // ========================================
    // REMINDERS — VolunteerReminderService
    // ========================================

    public function getReminderSettings(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $settings = \Nexus\Services\VolunteerReminderService::getSettings();
        return $this->respondWithData($settings);
    }

    public function updateReminderSettings(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();
        $this->rateLimit('vol_reminder_settings_update', 10, 60);

        $data = $this->getAllInput();
        $type = $data['reminder_type'] ?? '';

        $allowedTypes = ['pre_shift', 'post_shift_feedback', 'lapsed_volunteer', 'credential_expiry', 'training_expiry'];
        if (!in_array($type, $allowedTypes, true)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid reminder_type. Must be one of: ' . implode(', ', $allowedTypes), 'reminder_type', 422);
        }

        $result = \Nexus\Services\VolunteerReminderService::updateSetting($type, $data);
        return $this->respondWithData(['success' => $result]);
    }

    // ========================================
    // EMERGENCY ALERTS (V9) — VolunteerEmergencyAlertService
    // ========================================

    public function myEmergencyAlerts(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_emergency_list', 60, 60);

        $alerts = $this->volunteerEmergencyAlertService->getUserAlerts($userId);
        return $this->respondWithData(['alerts' => $alerts]);
    }

    public function createEmergencyAlert(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_emergency_create', 5, 60);

        $data = [
            'shift_id' => $this->inputInt('shift_id'),
            'message' => trim($this->input('message', '')),
            'priority' => $this->input('priority', 'urgent'),
            'required_skills' => $this->input('required_skills'),
            'expires_hours' => $this->inputInt('expires_hours') ?: 24,
        ];

        $alertId = $this->volunteerEmergencyAlertService->createAlert($userId, $data);

        if ($alertId === null) {
            $errors = $this->volunteerEmergencyAlertService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }

        return $this->respondWithData(['id' => $alertId, 'message' => 'Emergency alert sent'], null, 201);
    }

    public function respondToEmergencyAlert($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_emergency_respond', 10, 60);

        $response = $this->input('response');
        if (!$response || !in_array($response, ['accepted', 'declined'])) {
            return $this->respondWithError('VALIDATION_ERROR', 'Response must be accepted or declined', 'response', 400);
        }

        $success = $this->volunteerEmergencyAlertService->respond((int) $id, $userId, $response);

        if (!$success) {
            $errors = $this->volunteerEmergencyAlertService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }

        return $this->respondWithData(['id' => (int) $id, 'response' => $response]);
    }

    public function cancelEmergencyAlert($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_emergency_cancel', 10, 60);

        $tenantId = TenantContext::getId();
        $success = $this->volunteerEmergencyAlertService->cancelAlert((int) $id, $userId, $tenantId);

        if (!$success) {
            $errors = $this->volunteerEmergencyAlertService->getCancelErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }

        return $this->noContent();
    }

    // ========================================
    // WELLBEING / BURNOUT (V10) — VolunteerWellbeingService + DB
    // ========================================

    public function myWellbeingStatus(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_wellbeing_status', 10, 60);

        $assessment = $this->volunteerWellbeingService->detectBurnoutRisk($userId);
        return $this->respondWithData($assessment);
    }

    public function wellbeingDashboard(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_wellbeing_dashboard', 30, 60);

        $tenantId = TenantContext::getId();

        // Burnout risk assessment
        $assessment = $this->volunteerWellbeingService->detectBurnoutRisk($userId);
        $score = max(0, min(100, 100 - (int) $assessment['risk_score']));

        // Hours this week
        try {
            $row = DB::selectOne(
                "SELECT COALESCE(SUM(hours), 0) as total FROM vol_logs WHERE user_id = ? AND tenant_id = ? AND status = 'approved' AND date_logged >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [$userId, $tenantId]
            );
            $hoursThisWeek = round((float) $row->total, 1);
        } catch (\Throwable $e) {
            $hoursThisWeek = 0;
        }

        // Hours this month
        try {
            $row = DB::selectOne(
                "SELECT COALESCE(SUM(hours), 0) as total FROM vol_logs WHERE user_id = ? AND tenant_id = ? AND status = 'approved' AND date_logged >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                [$userId, $tenantId]
            );
            $hoursThisMonth = round((float) $row->total, 1);
        } catch (\Throwable $e) {
            $hoursThisMonth = 0;
        }

        // Streak: consecutive days with logged hours
        try {
            $dates = DB::select(
                "SELECT DISTINCT DATE(date_logged) as d FROM vol_logs WHERE user_id = ? AND tenant_id = ? AND status = 'approved' ORDER BY d DESC LIMIT 90",
                [$userId, $tenantId]
            );
            $streak = 0;
            $today = new \DateTime();
            foreach ($dates as $i => $dateRow) {
                $expected = (clone $today)->modify("-{$i} days")->format('Y-m-d');
                if ($dateRow->d === $expected) {
                    $streak++;
                } else {
                    break;
                }
            }
        } catch (\Throwable $e) {
            $streak = 0;
        }

        // Map risk level
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

        // Suggested rest days (next 7 days without scheduled shifts)
        $suggestedRest = [];
        try {
            $busyRows = DB::select(
                "SELECT DISTINCT DATE(s.start_time) as shift_date FROM vol_applications a JOIN vol_shifts s ON a.shift_id = s.id WHERE a.user_id = ? AND a.tenant_id = ? AND a.status = 'approved' AND s.start_time >= NOW() AND s.start_time <= DATE_ADD(NOW(), INTERVAL 7 DAY)",
                [$userId, $tenantId]
            );
            $busyDays = array_map(fn($r) => $r->shift_date, $busyRows);
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
            $rows = DB::select(
                "SELECT id, mood, note, created_at FROM vol_mood_checkins WHERE user_id = ? AND tenant_id = ? ORDER BY created_at DESC LIMIT 10",
                [$userId, $tenantId]
            );
            $recentCheckins = array_map(fn($row) => [
                'id' => (int) $row->id,
                'mood' => (int) $row->mood,
                'note' => $row->note,
                'created_at' => $row->created_at,
            ], $rows);
        } catch (\Throwable $e) { /* table may not exist yet */ }

        return $this->respondWithData([
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

    public function wellbeingCheckin(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_wellbeing_checkin', 10, 60);

        $mood = (int) $this->input('mood');
        if ($mood < 1 || $mood > 5) {
            return $this->respondWithError('VALIDATION_ERROR', 'Mood must be between 1 and 5', 'mood', 400);
        }

        $note = $this->input('note');
        if ($note) {
            $note = trim(mb_substr($note, 0, 500));
        }

        $tenantId = TenantContext::getId();

        try {
            DB::insert(
                "INSERT INTO vol_mood_checkins (tenant_id, user_id, mood, note, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$tenantId, $userId, $mood, $note ?: null]
            );

            return $this->respondWithData([
                'id' => (int) DB::getPdo()->lastInsertId(),
                'mood' => $mood,
                'note' => $note ?: null,
            ]);
        } catch (\Throwable $e) {
            error_log("Wellbeing checkin failed: " . $e->getMessage());
            return $this->respondWithError('SERVER_ERROR', 'Failed to save check-in', null, 500);
        }
    }

    // ========================================
    // LEGACY V1 INDEX
    // ========================================

    public function index(): JsonResponse
    {
        $this->ensureFeature();
        $this->getUserId();
        $this->rateLimit('volunteering_legacy_list', 60, 60);

        $opps = \Nexus\Models\VolOpportunity::search(TenantContext::getId(), '');
        return $this->respondWithCollection($opps);
    }

    // ========================================
    // HELPERS
    // ========================================

    /**
     * Check if user can coordinate/manage a shift.
     */
    private function canManageShift(int $shiftId, int $userId): bool
    {
        try {
            $tenantId = TenantContext::getId();

            $row = DB::selectOne("
                SELECT org.id AS organization_id, org.user_id AS org_owner_id
                FROM vol_shifts s
                JOIN vol_opportunities opp ON s.opportunity_id = opp.id
                JOIN vol_organizations org ON opp.organization_id = org.id
                WHERE s.id = ? AND s.tenant_id = ? AND opp.tenant_id = ? AND org.tenant_id = ?
                LIMIT 1
            ", [$shiftId, $tenantId, $tenantId, $tenantId]);

            if (!$row) {
                return false;
            }

            if ((int) $row->org_owner_id === $userId) {
                return true;
            }

            if (\Nexus\Models\OrgMember::isAdmin((int) $row->organization_id, $userId)) {
                return true;
            }

            $roleRow = DB::selectOne("SELECT role FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
            $role = $roleRow->role ?? '';

            return in_array($role, ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'], true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get user ID optionally (some endpoints work for anonymous users).
     */
    private function getUserIdOptional(): ?int
    {
        return $this->getOptionalUserId();
    }
}
