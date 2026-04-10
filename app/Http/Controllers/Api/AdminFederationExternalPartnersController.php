<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\FederationExternalApiClient;
use App\Services\FederationExternalPartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * AdminFederationExternalPartnersController -- CRUD for external federation partners.
 *
 * Manages external partner connections including health checks and API call logs.
 */
class AdminFederationExternalPartnersController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/federation/external-partners
     *
     * List all external partners for the current tenant.
     */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $partners = FederationExternalPartnerService::getAll($tenantId);
            return $this->respondWithData($partners);
        } catch (\Exception $e) {
            return $this->respondWithError('FETCH_FAILED', __('api.external_partners_fetch_failed'), null, 500);
        }
    }

    /**
     * POST /api/v2/admin/federation/external-partners
     *
     * Create a new external partner.
     */
    public function store(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $input = $this->getAllInput();

        $result = FederationExternalPartnerService::create($input, $tenantId, $adminId);

        if (!$result['success']) {
            return $this->respondWithError('CREATE_FAILED', $result['error'] ?? __('api.external_partner_create_failed'),
                null,
                422
            );
        }

        return $this->respondWithData(['id' => $result['id']], null, 201);
    }

    /**
     * PUT /api/v2/admin/federation/external-partners/{id}
     *
     * Update an existing external partner.
     */
    public function update(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $input = $this->getAllInput();

        $result = FederationExternalPartnerService::update($id, $input, $tenantId, $adminId);

        if (!$result['success']) {
            return $this->respondWithError('UPDATE_FAILED', $result['error'] ?? __('api.external_partner_update_failed'),
                null,
                422
            );
        }

        return $this->respondWithData(['id' => $result['id'] ?? $id]);
    }

    /**
     * DELETE /api/v2/admin/federation/external-partners/{id}
     *
     * Delete an external partner and its logs.
     */
    public function destroy(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $result = FederationExternalPartnerService::delete($id, $tenantId, $adminId);

        if (!$result['success']) {
            return $this->respondWithError('DELETE_FAILED', $result['error'] ?? __('api.external_partner_delete_failed'),
                null,
                422
            );
        }

        return $this->respondWithData(['deleted' => true]);
    }

    /**
     * POST /api/v2/admin/federation/external-partners/{id}/health-check
     *
     * Run a health check against an external partner's API.
     */
    public function healthCheck(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        // Verify partner exists and belongs to tenant
        $partner = FederationExternalPartnerService::getById($id, $tenantId);
        if (!$partner) {
            return $this->respondWithError('NOT_FOUND', __('api.partnership_not_found'), null, 404);
        }

        $startTime = microtime(true);
        $result = FederationExternalApiClient::healthCheck($id);
        $elapsed = (int) round((microtime(true) - $startTime) * 1000);

        // On successful health check, sync partner metadata (member count, name, version)
        if ($result['success']) {
            try {
                $timebankInfo = FederationExternalApiClient::get($id, '/timebanks');
                if (($timebankInfo['success'] ?? false) && !empty($timebankInfo['data'])) {
                    $tbData = $timebankInfo['data']['data'][0] ?? $timebankInfo['data'][0] ?? null;
                    if ($tbData) {
                        DB::update(
                            "UPDATE federation_external_partners SET
                                partner_member_count = ?,
                                partner_name = COALESCE(?, partner_name),
                                last_sync_at = NOW(),
                                error_count = 0,
                                last_error = NULL
                             WHERE id = ? AND tenant_id = ?",
                            [
                                (int) ($tbData['member_count'] ?? 0),
                                $tbData['name'] ?? null,
                                $id,
                                $tenantId,
                            ]
                        );
                    }
                }
            } catch (\Throwable $e) {
                // Non-critical — don't fail the health check
                error_log("FederationExternalPartner: metadata sync failed for #{$id}: " . $e->getMessage());
            }
        }

        // Always return 200 — the health check result is in the payload.
        // Using 502 here causes Cloudflare to replace our JSON with its own
        // error page, stripping CORS headers and breaking the browser request.
        return $this->respondWithData([
            'healthy' => $result['success'],
            'response_time_ms' => $elapsed,
            'status_code' => $result['status_code'] ?? 0,
            'error' => $result['success'] ? null : ($result['error'] ?? 'Health check failed'),
        ]);
    }

    /**
     * GET /api/v2/admin/federation/external-partners/{id}/logs
     *
     * Get recent API call logs for a partner.
     */
    public function logs(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        // Verify partner exists and belongs to tenant
        $partner = FederationExternalPartnerService::getById($id, $tenantId);
        if (!$partner) {
            return $this->respondWithError('NOT_FOUND', __('api.partnership_not_found'), null, 404);
        }

        try {
            $logs = FederationExternalPartnerService::getLogs($id, 100);
            return $this->respondWithData($logs);
        } catch (\Exception $e) {
            return $this->respondWithError('FETCH_FAILED', __('api.partner_logs_fetch_failed'), null, 500);
        }
    }
}
