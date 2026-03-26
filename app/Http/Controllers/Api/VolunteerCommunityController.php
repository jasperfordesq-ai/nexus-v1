<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Services\ShiftSwapService;
use App\Services\ShiftWaitlistService;
use App\Services\ShiftGroupReservationService;
use App\Services\RecurringShiftService;
use App\Services\VolunteerFormService;
use App\Services\CommunityProjectService;
use App\Services\VolunteerDonationService;
use App\Services\GuardianConsentService;
use App\Services\WebhookDispatchService;
use App\Services\VolunteerReminderService;
use App\Core\TenantContext;

/**
 * VolunteerCommunityController -- Swaps, waitlist, group reservations, recurring shifts,
 * custom fields, accessibility, community projects, donations, giving days, webhooks,
 * reminders, and guardian consents.
 */
class VolunteerCommunityController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly ShiftSwapService $shiftSwapService,
        private readonly ShiftWaitlistService $shiftWaitlistService,
        private readonly ShiftGroupReservationService $shiftGroupReservationService,
        private readonly RecurringShiftService $recurringShiftService,
        private readonly VolunteerFormService $volunteerFormService,
        private readonly CommunityProjectService $communityProjectService,
        private readonly VolunteerDonationService $volunteerDonationService,
        private readonly GuardianConsentService $guardianConsentService,
        private readonly WebhookDispatchService $webhookDispatchService,
        private readonly VolunteerReminderService $volunteerReminderService,
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
    // WAITLIST
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
    // SHIFT SWAPPING
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
        return $this->respondWithData($requests);
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

        if ($action === 'accept') {
            // Check if the swap went to admin_pending instead of being directly accepted
            $actualStatus = DB::table('vol_shift_swap_requests')->where('id', (int) $id)->value('status');
            if ($actualStatus === 'admin_pending') {
                return $this->respondWithData(['id' => (int) $id, 'status' => 'admin_pending', 'message' => 'Swap accepted but requires admin approval']);
            }
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
    // GROUP RESERVATIONS
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
    // RECURRING SHIFTS
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

        $pattern = $this->recurringShiftService->getPattern($patternId);
        return $this->respondWithData($pattern, null, 201);
    }

    public function updateRecurringPattern($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_recurring_update', 10, 60);
        $patternId = (int) $id;

        $success = $this->recurringShiftService->updatePattern($patternId, $this->getAllInput(), $userId);

        if (!$success) {
            $errors = $this->recurringShiftService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }

        $pattern = $this->recurringShiftService->getPattern($patternId);
        return $this->respondWithData($pattern);
    }

    public function deleteRecurringPattern($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_recurring_delete', 10, 60);
        $patternId = (int) $id;

        $deactivated = $this->recurringShiftService->deactivatePattern($patternId, $userId);

        if (!$deactivated) {
            $errors = $this->recurringShiftService->getErrors();
            return $this->respondWithErrors($errors, $this->getErrorStatus($errors));
        }

        $deleted = $this->recurringShiftService->deleteFutureShifts($patternId, $userId);

        return $this->respondWithData([
            'message' => 'Recurring pattern deactivated',
            'future_shifts_removed' => $deleted,
        ]);
    }

    // ========================================
    // CUSTOM FIELDS
    // ========================================

    /** Public endpoint -- custom fields needed for application forms */
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
            error_log("VolunteerCommunityController::createCustomField error: " . $e->getMessage());
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
    // ACCESSIBILITY
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
    // COMMUNITY PROJECTS
    // ========================================

    /** Public endpoint -- community project listings */
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

    /** Public endpoint -- individual project pages */
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
    // DONATIONS
    // ========================================

    /** Public endpoint -- donation listings */
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

        $csv = $this->volunteerDonationService->exportDonations($filters);

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="volunteer_donations_' . date('Y-m-d') . '.csv"');
    }

    // ========================================
    // GIVING DAYS
    // ========================================

    public function getGivingDays(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('vol_giving_days', 30, 60);

        $result = $this->volunteerDonationService->getGivingDays();
        return $this->respondWithData($result);
    }

    /** Public endpoint -- giving day stats for fundraising pages */
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
    // WEBHOOKS
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

        $result = $this->webhookDispatchService->testWebhook((int) $id);
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

        $logs = $this->webhookDispatchService->getLogs((int) $id, $filters);
        return $this->respondWithData($logs);
    }

    // ========================================
    // REMINDERS
    // ========================================

    public function getReminderSettings(): JsonResponse
    {
        $this->ensureFeature();
        $this->requireAdmin();

        $settings = $this->volunteerReminderService->getSettings();
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

        $result = $this->volunteerReminderService->updateSetting($type, $data);
        return $this->respondWithData(['success' => $result]);
    }

    // ========================================
    // GUARDIAN CONSENTS
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
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    /** Public endpoint -- no auth required */
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

        $consents = $this->guardianConsentService->getConsentsForAdmin($filters);
        return $this->respondWithData($consents);
    }
}
