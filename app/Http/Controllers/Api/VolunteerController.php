<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;


/**
 * VolunteerController -- Volunteering opportunities and applications.
 */
class VolunteerController extends BaseApiController
{
    protected bool $isV2Api = true;

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

    public function opportunities(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'opportunities');
    }

    public function show(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'show', func_get_args());
    }

    public function apply(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'apply', func_get_args());
    }

    public function myApplications(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myApplications');
    }

    public function createOpportunity(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'createOpportunity');
    }

    public function showOpportunity($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'showOpportunity', func_get_args());
    }

    public function updateOpportunity($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateOpportunity', func_get_args());
    }

    public function deleteOpportunity($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'deleteOpportunity', func_get_args());
    }

    public function shifts($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'shifts', func_get_args());
    }

    public function opportunityApplications($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'opportunityApplications', func_get_args());
    }

    public function handleApplication($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'handleApplication', func_get_args());
    }

    public function withdrawApplication($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'withdrawApplication', func_get_args());
    }

    public function myShifts(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myShifts');
    }

    public function signUp($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'signUp', func_get_args());
    }

    public function cancelSignup($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'cancelSignup', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'verifyHours', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'showOrganisation', func_get_args());
    }

    public function createReview(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'createReview');
    }

    public function getReviews($type, $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getReviews', func_get_args());
    }

    public function adminExpenses(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'adminExpenses');
    }

    public function reviewExpense($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'reviewExpense', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'verifyTraining', func_get_args());
    }

    public function rejectTraining($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'rejectTraining', func_get_args());
    }

    public function adminIncidents(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'adminIncidents');
    }

    public function updateIncident($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateIncident', func_get_args());
    }

    public function assignDlp($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'assignDlp', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateCustomField', func_get_args());
    }

    public function deleteCustomField($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'deleteCustomField', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'reviewCommunityProject', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateWebhook', func_get_args());
    }

    public function deleteWebhook($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'deleteWebhook', func_get_args());
    }

    public function testWebhook($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'testWebhook', func_get_args());
    }

    public function getWebhookLogs($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getWebhookLogs', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateGivingDay', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'verifyCertificate', func_get_args());
    }

    public function certificateHtml($code): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'certificateHtml', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'deleteCredential', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'respondToEmergencyAlert', func_get_args());
    }

    public function cancelEmergencyAlert($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'cancelEmergencyAlert', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'respondToSwap', func_get_args());
    }

    public function cancelSwap($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'cancelSwap', func_get_args());
    }

    public function myWaitlists(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myWaitlists');
    }

    public function joinWaitlist($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'joinWaitlist', func_get_args());
    }

    public function leaveWaitlist($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'leaveWaitlist', func_get_args());
    }

    public function promoteFromWaitlist($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'promoteFromWaitlist', func_get_args());
    }

    public function myGroupReservations(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'myGroupReservations');
    }

    public function groupReserve($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'groupReserve', func_get_args());
    }

    public function addGroupMember($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'addGroupMember', func_get_args());
    }

    public function removeGroupMember($id, $userId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'removeGroupMember', func_get_args());
    }

    public function cancelGroupReservation($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'cancelGroupReservation', func_get_args());
    }

    public function getCheckIn($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getCheckIn', func_get_args());
    }

    public function verifyCheckIn($token): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'verifyCheckIn', func_get_args());
    }

    public function checkOut($token): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'checkOut', func_get_args());
    }

    public function shiftCheckIns($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'shiftCheckIns', func_get_args());
    }

    public function recurringPatterns($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'recurringPatterns', func_get_args());
    }

    public function createRecurringPattern($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'createRecurringPattern', func_get_args());
    }

    public function updateRecurringPattern($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateRecurringPattern', func_get_args());
    }

    public function deleteRecurringPattern($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'deleteRecurringPattern', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getExpense', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'verifyGuardianConsent', func_get_args());
    }

    public function withdrawGuardianConsent($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'withdrawGuardianConsent', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getIncident', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getCommunityProject', func_get_args());
    }

    public function updateCommunityProject($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'updateCommunityProject', func_get_args());
    }

    public function supportCommunityProject($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'supportCommunityProject', func_get_args());
    }

    public function unsupportCommunityProject($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'unsupportCommunityProject', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\VolunteerApiController::class, 'getGivingDayStats', func_get_args());
    }

    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\VolunteeringApiController::class, 'index');
    }
}
