<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AdminFederationCreditAgreementsController -- Federation credit agreement management.
 *
 * Handles CRUD for federation credit agreements between partner tenants
 * and lists active federation partners.
 */
class AdminFederationCreditAgreementsController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/federation/credit-agreements
     *
     * List credit agreements for the current tenant.
     */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        if (class_exists(\App\Services\FederationCreditService::class)) {
            try {
                $agreements = \App\Services\FederationCreditService::listAgreementsStatic($tenantId);
                return $this->respondWithData($agreements);
            } catch (\Exception $e) {
                return $this->respondWithError('FETCH_FAILED', 'Failed to load credit agreements', null, 500);
            }
        }

        return $this->respondWithError('SERVICE_UNAVAILABLE', 'FederationCreditService not available', null, 503);
    }

    /**
     * POST /api/v2/admin/federation/credit-agreements
     *
     * Create a new credit agreement with a partner tenant.
     */
    public function store(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $input = $this->getAllInput();

        $partnerTenantId = (int) ($input['partner_tenant_id'] ?? 0);
        $exchangeRate = (float) ($input['exchange_rate'] ?? 0);
        $monthlyLimit = (float) ($input['monthly_limit'] ?? 0);

        if ($partnerTenantId <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', 'Partner tenant ID is required', 'partner_tenant_id');
        }
        if ($partnerTenantId === $tenantId) {
            return $this->respondWithError('VALIDATION_ERROR', 'Cannot create agreement with your own community', 'partner_tenant_id');
        }
        if ($exchangeRate <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', 'Exchange rate must be greater than zero', 'exchange_rate');
        }
        if ($monthlyLimit <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', 'Monthly limit must be greater than zero', 'monthly_limit');
        }

        if (class_exists(\App\Services\FederationCreditService::class)) {
            try {
                $agreement = \App\Services\FederationCreditService::createAgreementStatic(
                    $tenantId,
                    $partnerTenantId,
                    $exchangeRate,
                    $monthlyLimit,
                    $adminId
                );
                return $this->respondWithData($agreement, null, 201);
            } catch (\Exception $e) {
                return $this->respondWithError('CREATE_FAILED', 'Failed to create credit agreement: ' . $e->getMessage());
            }
        }

        return $this->respondWithError('SERVICE_UNAVAILABLE', 'FederationCreditService not available', null, 503);
    }

    /**
     * POST /api/v2/admin/federation/credit-agreements/{id}/{action}
     *
     * Approve, reject, suspend, or activate a credit agreement.
     */
    public function action(int $id, string $action): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $validActions = ['approve', 'reject', 'suspend', 'activate'];
        if (!in_array($action, $validActions, true)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid action. Must be one of: ' . implode(', ', $validActions), 'action');
        }

        $statusMap = [
            'approve'  => 'active',
            'reject'   => 'rejected',
            'suspend'  => 'suspended',
            'activate' => 'active',
        ];

        try {
            $updated = DB::update(
                "UPDATE federation_credit_agreements SET status = ?, updated_at = NOW() WHERE id = ? AND (tenant_a_id = ? OR tenant_b_id = ?)",
                [$statusMap[$action], $id, $tenantId, $tenantId]
            );

            if ($updated === 0) {
                return $this->respondWithError('NOT_FOUND', 'Credit agreement not found', null, 404);
            }

            return $this->respondWithData(['success' => true]);
        } catch (\Exception $e) {
            return $this->respondWithError('UPDATE_FAILED', 'Failed to update credit agreement', null, 500);
        }
    }

    /**
     * GET /api/v2/admin/federation/partners
     *
     * List active federation partners for the current tenant.
     */
    public function partners(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $partners = DB::select(
                "SELECT fp.*, t.name as partner_name, t.slug as partner_slug
                 FROM federation_partnerships fp
                 JOIN tenants t ON (CASE WHEN fp.tenant_a_id = ? THEN fp.tenant_b_id ELSE fp.tenant_a_id END) = t.id
                 WHERE (fp.tenant_a_id = ? OR fp.tenant_b_id = ?) AND fp.status = 'active'",
                [$tenantId, $tenantId, $tenantId]
            );

            return $this->respondWithData(array_map(fn($r) => (array) $r, $partners));
        } catch (\Exception $e) {
            // Table may not exist yet — return empty array gracefully
            return $this->respondWithData([]);
        }
    }
}
