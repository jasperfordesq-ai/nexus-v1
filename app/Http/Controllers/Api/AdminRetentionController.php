<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\AuditLogService;
use App\Services\RetentionPolicyService;
use Illuminate\Http\JsonResponse;

/**
 * AdminRetentionController — tenant data retention policy management
 * (IT-Data-03). Admin-only; all reads/writes scoped to the requesting
 * admin's tenant. Policy changes are themselves audit-logged.
 */
class AdminRetentionController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    /** GET /api/v2/admin/retention/policies */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        return $this->respondWithData([
            'policies' => array_values(RetentionPolicyService::getPolicies($tenantId)),
            'limits' => [
                'min_days' => RetentionPolicyService::MIN_RETENTION_DAYS,
                'max_days' => RetentionPolicyService::MAX_RETENTION_DAYS,
                'actions' => RetentionPolicyService::ACTIONS,
            ],
        ]);
    }

    /** PUT /api/v2/admin/retention/policies/{dataType} */
    public function update(string $dataType): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $retentionDays = (int) $this->input('retention_days', 0);
        $isEnabled = $this->inputBool('is_enabled', false);
        $action = (string) $this->input('action', 'delete');

        $error = RetentionPolicyService::upsertPolicy(
            $tenantId,
            $dataType,
            $retentionDays,
            $isEnabled,
            $action,
            $adminId,
        );

        if ($error !== null) {
            return $this->respondWithError('VALIDATION_ERROR', $error, 'retention_days', 422);
        }

        $this->auditLogService->logAdminAction('retention_policy_updated', $adminId, null, [
            'data_type' => $dataType,
            'retention_days' => $retentionDays,
            'is_enabled' => $isEnabled,
            'action' => $action,
        ]);

        return $this->respondWithData([
            'policy' => RetentionPolicyService::getPolicies($tenantId)[$dataType] ?? null,
        ]);
    }

    /** GET /api/v2/admin/retention/runs */
    public function runs(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $limit = (int) request()->query('limit', 50);

        return $this->respondWithData([
            'runs' => RetentionPolicyService::getRecentRuns($tenantId, $limit),
        ]);
    }
}
