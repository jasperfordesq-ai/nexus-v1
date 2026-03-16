<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AdminVolunteerController -- Admin volunteer management (opportunities, applications, hour verification).
 *
 * All methods require admin authentication.
 */
class AdminVolunteerController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /** GET /api/v2/admin/volunteer/opportunities */
    public function opportunities(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $offset = ($page - 1) * $perPage;

        $items = DB::select(
            'SELECT * FROM volunteer_opportunities WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$tenantId, $perPage, $offset]
        );
        $total = DB::selectOne(
            'SELECT COUNT(*) as cnt FROM volunteer_opportunities WHERE tenant_id = ?',
            [$tenantId]
        )->cnt;

        return $this->respondWithPaginatedCollection($items, (int) $total, $page, $perPage);
    }

    /** GET /api/v2/admin/volunteer/applications */
    public function applications(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $status = $this->query('status', 'pending');
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $offset = ($page - 1) * $perPage;

        $items = DB::select(
            'SELECT va.* FROM volunteer_applications va
             JOIN volunteer_opportunities vo ON va.opportunity_id = vo.id
             WHERE vo.tenant_id = ? AND va.status = ?
             ORDER BY va.created_at DESC LIMIT ? OFFSET ?',
            [$tenantId, $status, $perPage, $offset]
        );
        $total = DB::selectOne(
            'SELECT COUNT(*) as cnt FROM volunteer_applications va
             JOIN volunteer_opportunities vo ON va.opportunity_id = vo.id
             WHERE vo.tenant_id = ? AND va.status = ?',
            [$tenantId, $status]
        )->cnt;

        return $this->respondWithPaginatedCollection($items, (int) $total, $page, $perPage);
    }

    /** POST /api/v2/admin/volunteer/hours/{id}/verify */
    public function verifyHours(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $affected = DB::update(
            'UPDATE volunteer_hours vh
             JOIN volunteer_opportunities vo ON vh.opportunity_id = vo.id
             SET vh.verified = 1, vh.verified_at = NOW()
             WHERE vh.id = ? AND vo.tenant_id = ?',
            [$id, $tenantId]
        );

        if ($affected === 0) {
            return $this->respondWithError('NOT_FOUND', 'Volunteer hours record not found', null, 404);
        }

        return $this->respondWithData(['id' => $id, 'verified' => true]);
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


    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVolunteeringApiController::class, 'index');
    }


    public function approvals(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVolunteeringApiController::class, 'approvals');
    }


    public function organizations(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVolunteeringApiController::class, 'organizations');
    }


    public function approveApplication($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVolunteeringApiController::class, 'approveApplication', [$id]);
    }


    public function declineApplication($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVolunteeringApiController::class, 'declineApplication', [$id]);
    }


    public function sendShiftReminders(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminVolunteeringApiController::class, 'sendShiftReminders');
    }

}
