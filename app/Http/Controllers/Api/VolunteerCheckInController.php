<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Services\VolunteerCheckInService;
use App\Services\WebhookDispatchService;
use App\Core\TenantContext;
use App\Models\OrgMember;

/**
 * VolunteerCheckInController -- QR check-in, check-out, and verification.
 */
class VolunteerCheckInController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly VolunteerCheckInService $volunteerCheckInService,
        private readonly WebhookDispatchService $webhookDispatchService,
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

            if (OrgMember::isAdmin((int) $row->organization_id, $userId)) {
                return true;
            }

            $roleRow = DB::selectOne("SELECT role FROM users WHERE id = ? AND tenant_id = ?", [$userId, $tenantId]);
            $role = $roleRow->role ?? '';

            return in_array($role, ['admin', 'tenant_admin', 'tenant_super_admin', 'super_admin'], true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getCheckIn($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('volunteering_checkin_get', 60, 60);
        $shiftId = (int) $id;

        $checkin = $this->volunteerCheckInService->getUserCheckIn($shiftId, $userId);

        if (!$checkin) {
            $token = VolunteerCheckInService::generateToken($shiftId, $userId);
            if ($token) {
                $checkin = $this->volunteerCheckInService->getUserCheckIn($shiftId, $userId);
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

        $result = $this->volunteerCheckInService->verifyCheckIn($token);

        if ($result === null) {
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
        $success = $this->volunteerCheckInService->checkOut($token);

        if (!$success) {
            $errors = $this->volunteerCheckInService->getErrors();
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

        $checkins = $this->volunteerCheckInService->getShiftCheckIns($shiftId);
        return $this->respondWithData(['checkins' => $checkins]);
    }
}
