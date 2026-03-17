<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\VolunteerService;
use Nexus\Core\TenantContext;

/**
 * VolunteerController -- Volunteering opportunities, applications, shifts, hours, and organisations.
 *
 * Core methods use Eloquent via VolunteerService; complex/admin methods delegate to legacy.
 */
class VolunteerController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly VolunteerService $volunteerService,
    ) {}

    /**
     * Ensure the volunteering feature is enabled for this tenant.
     */
    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('volunteering')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', 'Volunteering module is not enabled for this community', null, 403)
            );
        }
    }

    // ========================================
    // OPPORTUNITIES
    // ========================================

    /** GET /api/v2/volunteering/opportunities — list with filters + cursor pagination */
    public function opportunities(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('volunteering_list', 60, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 50),
        ];

        if ($this->query('organization_id')) {
            $filters['organization_id'] = (int) $this->query('organization_id');
        }
        if ($this->query('category_id')) {
            $filters['category_id'] = (int) $this->query('category_id');
        }
        if ($this->query('search')) {
            $filters['search'] = $this->query('search');
        }
        if ($this->queryBool('is_remote')) {
            $filters['is_remote'] = true;
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->volunteerService->getOpportunities($filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /** GET /api/v2/volunteering/opportunities/{id} — single opportunity detail */
    public function showOpportunity($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('volunteering_show', 120, 60);

        $opportunity = $this->volunteerService->getById((int) $id);

        if (!$opportunity) {
            return $this->respondWithError('NOT_FOUND', 'Opportunity not found', null, 404);
        }

        return $this->respondWithData($opportunity);
    }

    /** POST /api/v2/volunteering/opportunities — create new opportunity (org admin) */
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

    /** POST /api/v2/volunteering/opportunities/{id}/apply — apply to volunteer */
    public function apply(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_apply', 20, 60);

        $data = [
            'message'  => trim($this->input('message', '')),
            'shift_id' => $this->inputInt('shift_id') ?: null,
        ];

        $application = $this->volunteerService->apply($id, $userId, $data);

        return $this->respondWithData($application, null, 201);
    }

    /** GET /api/v2/volunteering/applications — current user's applications */
    public function myApplications(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_my_apps', 60, 60);

        $applications = $this->volunteerService->getMyApplications($userId);

        return $this->respondWithData($applications);
    }

    // ========================================
    // SHIFTS
    // ========================================

    /** GET /api/v2/volunteering/shifts — current user's shifts */
    public function myShifts(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_my_shifts', 60, 60);

        $shifts = $this->volunteerService->getMyShifts($userId);

        return $this->respondWithData($shifts);
    }

    // ========================================
    // HOURS
    // ========================================

    /** GET /api/v2/volunteering/hours — current user's logged hours */
    public function myHours(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_my_hours', 60, 60);

        $hours = $this->volunteerService->getMyHours($userId);

        return $this->respondWithData($hours);
    }

    /** GET /api/v2/volunteering/hours/summary — hours summary/stats */
    public function hoursSummary(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_hours_summary', 60, 60);

        $summary = $this->volunteerService->getHoursSummary($userId);

        return $this->respondWithData($summary);
    }

    // ========================================
    // ORGANISATIONS
    // ========================================

    /** GET /api/v2/volunteering/organisations — list volunteer organisations */
    public function organisations(): JsonResponse
    {
        $this->ensureFeature();
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

        $result = $this->volunteerService->getOrganisations($filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /** GET /api/v2/volunteering/organisations/{id} — single organisation detail */
    public function showOrganisation($id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('volunteering_org_show', 120, 60);

        $org = $this->volunteerService->getOrganisationById((int) $id);

        if (!$org) {
            return $this->respondWithError('NOT_FOUND', 'Organisation not found', null, 404);
        }

        return $this->respondWithData($org);
    }

    // ========================================
    // DELEGATION — complex/admin methods via legacy
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

    public function show(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'show', func_get_args());
    }

    public function updateOpportunity($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateOpportunity', [(int) $id]);
    }

    public function deleteOpportunity($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'deleteOpportunity', [(int) $id]);
    }

    public function shifts($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'shifts', [(int) $id]);
    }

    public function opportunityApplications($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'opportunityApplications', [(int) $id]);
    }

    public function handleApplication($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'handleApplication', [(int) $id]);
    }

    public function withdrawApplication($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'withdrawApplication', [(int) $id]);
    }

    public function signUp($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'signUp', [(int) $id]);
    }

    public function cancelSignup($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'cancelSignup', [(int) $id]);
    }

    public function logHours(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'logHours');
    }

    public function pendingHoursReview(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'pendingHoursReview');
    }

    public function verifyHours($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'verifyHours', [(int) $id]);
    }

    public function myOrganisations(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myOrganisations');
    }

    public function createOrganisation(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'createOrganisation');
    }

    public function createReview(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'createReview');
    }

    public function getReviews($type, $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getReviews', [$type, $id]);
    }

    public function adminExpenses(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'adminExpenses');
    }

    public function reviewExpense($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'reviewExpense', [(int) $id]);
    }

    public function exportExpenses(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'exportExpenses');
    }

    public function getExpensePolicies(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getExpensePolicies');
    }

    public function updateExpensePolicy(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateExpensePolicy');
    }

    public function adminGuardianConsents(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'adminGuardianConsents');
    }

    public function adminTraining(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'adminTraining');
    }

    public function verifyTraining($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'verifyTraining', [(int) $id]);
    }

    public function rejectTraining($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'rejectTraining', [(int) $id]);
    }

    public function adminIncidents(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'adminIncidents');
    }

    public function updateIncident($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateIncident', [(int) $id]);
    }

    public function assignDlp($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'assignDlp', [(int) $id]);
    }

    public function adminCustomFields(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'adminCustomFields');
    }

    public function createCustomField(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'createCustomField');
    }

    public function updateCustomField($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateCustomField', [(int) $id]);
    }

    public function deleteCustomField($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'deleteCustomField', [(int) $id]);
    }

    public function getReminderSettings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getReminderSettings');
    }

    public function updateReminderSettings(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateReminderSettings');
    }

    public function reviewCommunityProject($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'reviewCommunityProject', [(int) $id]);
    }

    public function getWebhooks(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getWebhooks');
    }

    public function createWebhook(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'createWebhook');
    }

    public function updateWebhook($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateWebhook', [(int) $id]);
    }

    public function deleteWebhook($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'deleteWebhook', [(int) $id]);
    }

    public function testWebhook($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'testWebhook', [(int) $id]);
    }

    public function getWebhookLogs($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getWebhookLogs', [(int) $id]);
    }

    public function adminGivingDays(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'adminGivingDays');
    }

    public function createGivingDay(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'createGivingDay');
    }

    public function updateGivingDay($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateGivingDay', [(int) $id]);
    }

    public function exportDonations(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'exportDonations');
    }

    public function recommendedShifts(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'recommendedShifts');
    }

    public function myCertificates(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myCertificates');
    }

    public function generateCertificate(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'generateCertificate');
    }

    public function verifyCertificate($code): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'verifyCertificate', [$code]);
    }

    public function certificateHtml($code): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'certificateHtml', [$code]);
    }

    public function myCredentials(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myCredentials');
    }

    public function uploadCredential(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'uploadCredential');
    }

    public function deleteCredential($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'deleteCredential', [(int) $id]);
    }

    public function myEmergencyAlerts(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myEmergencyAlerts');
    }

    public function createEmergencyAlert(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'createEmergencyAlert');
    }

    public function respondToEmergencyAlert($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'respondToEmergencyAlert', [(int) $id]);
    }

    public function cancelEmergencyAlert($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'cancelEmergencyAlert', [(int) $id]);
    }

    public function wellbeingDashboard(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'wellbeingDashboard');
    }

    public function wellbeingCheckin(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'wellbeingCheckin');
    }

    public function myWellbeingStatus(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myWellbeingStatus');
    }

    public function getSwapRequests(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getSwapRequests');
    }

    public function requestSwap(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'requestSwap');
    }

    public function respondToSwap($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'respondToSwap', [(int) $id]);
    }

    public function cancelSwap($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'cancelSwap', [(int) $id]);
    }

    public function myWaitlists(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myWaitlists');
    }

    public function joinWaitlist($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'joinWaitlist', [(int) $id]);
    }

    public function leaveWaitlist($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'leaveWaitlist', [(int) $id]);
    }

    public function promoteFromWaitlist($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'promoteFromWaitlist', [(int) $id]);
    }

    public function myGroupReservations(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myGroupReservations');
    }

    public function groupReserve($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'groupReserve', [(int) $id]);
    }

    public function addGroupMember($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'addGroupMember', [(int) $id]);
    }

    public function removeGroupMember($id, $userId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'removeGroupMember', [(int) $id, (int) $userId]);
    }

    public function cancelGroupReservation($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'cancelGroupReservation', [(int) $id]);
    }

    public function getCheckIn($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getCheckIn', [(int) $id]);
    }

    public function verifyCheckIn($token): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'verifyCheckIn', [$token]);
    }

    public function checkOut($token): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'checkOut', [$token]);
    }

    public function shiftCheckIns($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'shiftCheckIns', [(int) $id]);
    }

    public function recurringPatterns($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'recurringPatterns', [(int) $id]);
    }

    public function createRecurringPattern($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'createRecurringPattern', [(int) $id]);
    }

    public function updateRecurringPattern($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateRecurringPattern', [(int) $id]);
    }

    public function deleteRecurringPattern($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'deleteRecurringPattern', [(int) $id]);
    }

    public function myExpenses(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myExpenses');
    }

    public function submitExpense(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'submitExpense');
    }

    public function getExpense($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getExpense', [(int) $id]);
    }

    public function myGuardianConsents(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myGuardianConsents');
    }

    public function requestGuardianConsent(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'requestGuardianConsent');
    }

    public function verifyGuardianConsent($token): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'verifyGuardianConsent', [$token]);
    }

    public function withdrawGuardianConsent($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'withdrawGuardianConsent', [(int) $id]);
    }

    public function myTraining(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myTraining');
    }

    public function recordTraining(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'recordTraining');
    }

    public function reportIncident(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'reportIncident');
    }

    public function getIncidents(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getIncidents');
    }

    public function getIncident($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getIncident', [(int) $id]);
    }

    public function getCustomFields(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getCustomFields');
    }

    public function myAccessibilityNeeds(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myAccessibilityNeeds');
    }

    public function updateAccessibilityNeeds(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateAccessibilityNeeds');
    }

    public function getCommunityProjects(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getCommunityProjects');
    }

    public function proposeCommunityProject(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'proposeCommunityProject');
    }

    public function getCommunityProject($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getCommunityProject', [(int) $id]);
    }

    public function updateCommunityProject($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateCommunityProject', [(int) $id]);
    }

    public function supportCommunityProject($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'supportCommunityProject', [(int) $id]);
    }

    public function unsupportCommunityProject($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'unsupportCommunityProject', [(int) $id]);
    }

    public function getDonations(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getDonations');
    }

    public function createDonation(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'createDonation');
    }

    public function getGivingDays(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getGivingDays');
    }

    public function getGivingDayStats($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getGivingDayStats', [(int) $id]);
    }

    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteeringApiController::class, 'index');
    }
}
