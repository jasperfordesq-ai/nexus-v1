<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\VolunteerService;

/**
 * VolunteerController -- Volunteering opportunities and applications.
 */
class VolunteerController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly VolunteerService $volunteerService,
    ) {}

    /** GET /api/v2/volunteer/opportunities */
    public function opportunities(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        
        $result = $this->volunteerService->getOpportunities($tenantId, $page, $perPage);
        
        return $this->respondWithPaginatedCollection(
            $result['items'], $result['total'], $page, $perPage
        );
    }

    /** GET /api/v2/volunteer/{id} */
    public function show(int $id): JsonResponse
    {
        $opportunity = $this->volunteerService->getById($id, $this->getTenantId());
        
        if ($opportunity === null) {
            return $this->respondWithError('NOT_FOUND', 'Opportunity not found', null, 404);
        }
        
        return $this->respondWithData($opportunity);
    }

    /** POST /api/v2/volunteer/{id}/apply */
    public function apply(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('volunteer_apply', 10, 60);
        
        $data = $this->getAllInput();
        $application = $this->volunteerService->apply($id, $userId, $this->getTenantId(), $data);
        
        if ($application === null) {
            return $this->respondWithError('NOT_FOUND', 'Opportunity not found', null, 404);
        }
        
        return $this->respondWithData($application, null, 201);
    }

    /** GET /api/v2/volunteer/my-applications */
    public function myApplications(): JsonResponse
    {
        $userId = $this->requireAuth();
        $applications = $this->volunteerService->getUserApplications($userId, $this->getTenantId());
        
        return $this->respondWithData($applications);
    }


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


    public function createOpportunity(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'createOpportunity');
    }


    public function showOpportunity($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'showOpportunity', [$id]);
    }


    public function updateOpportunity($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateOpportunity', [$id]);
    }


    public function deleteOpportunity($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'deleteOpportunity', [$id]);
    }


    public function shifts($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'shifts', [$id]);
    }


    public function opportunityApplications($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'opportunityApplications', [$id]);
    }


    public function handleApplication($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'handleApplication', [$id]);
    }


    public function withdrawApplication($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'withdrawApplication', [$id]);
    }


    public function myShifts(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myShifts');
    }


    public function signUp($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'signUp', [$id]);
    }


    public function cancelSignup($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'cancelSignup', [$id]);
    }


    public function myHours(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myHours');
    }


    public function logHours(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'logHours');
    }


    public function hoursSummary(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'hoursSummary');
    }


    public function pendingHoursReview(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'pendingHoursReview');
    }


    public function verifyHours($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'verifyHours', [$id]);
    }


    public function myOrganisations(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myOrganisations');
    }


    public function organisations(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'organisations');
    }


    public function createOrganisation(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'createOrganisation');
    }


    public function showOrganisation($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'showOrganisation', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'reviewExpense', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'verifyTraining', [$id]);
    }


    public function rejectTraining($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'rejectTraining', [$id]);
    }


    public function adminIncidents(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'adminIncidents');
    }


    public function updateIncident($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateIncident', [$id]);
    }


    public function assignDlp($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'assignDlp', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateCustomField', [$id]);
    }


    public function deleteCustomField($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'deleteCustomField', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'reviewCommunityProject', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateWebhook', [$id]);
    }


    public function deleteWebhook($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'deleteWebhook', [$id]);
    }


    public function testWebhook($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'testWebhook', [$id]);
    }


    public function getWebhookLogs($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getWebhookLogs', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateGivingDay', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'deleteCredential', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'respondToEmergencyAlert', [$id]);
    }


    public function cancelEmergencyAlert($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'cancelEmergencyAlert', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'respondToSwap', [$id]);
    }


    public function cancelSwap($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'cancelSwap', [$id]);
    }


    public function myWaitlists(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myWaitlists');
    }


    public function joinWaitlist($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'joinWaitlist', [$id]);
    }


    public function leaveWaitlist($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'leaveWaitlist', [$id]);
    }


    public function promoteFromWaitlist($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'promoteFromWaitlist', [$id]);
    }


    public function myGroupReservations(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myGroupReservations');
    }


    public function groupReserve($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'groupReserve', [$id]);
    }


    public function addGroupMember($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'addGroupMember', [$id]);
    }


    public function removeGroupMember($id, $userId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'removeGroupMember', [$id, $userId]);
    }


    public function cancelGroupReservation($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'cancelGroupReservation', [$id]);
    }


    public function getCheckIn($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getCheckIn', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'shiftCheckIns', [$id]);
    }


    public function recurringPatterns($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'recurringPatterns', [$id]);
    }


    public function createRecurringPattern($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'createRecurringPattern', [$id]);
    }


    public function updateRecurringPattern($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateRecurringPattern', [$id]);
    }


    public function deleteRecurringPattern($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'deleteRecurringPattern', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getExpense', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'withdrawGuardianConsent', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getIncident', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getCommunityProject', [$id]);
    }


    public function updateCommunityProject($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateCommunityProject', [$id]);
    }


    public function supportCommunityProject($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'supportCommunityProject', [$id]);
    }


    public function unsupportCommunityProject($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'unsupportCommunityProject', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getGivingDayStats', [$id]);
    }


    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteeringApiController::class, 'index');
    }

}
