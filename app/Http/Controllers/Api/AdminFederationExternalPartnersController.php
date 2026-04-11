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

        // On successful health check, sync partner metadata
        if ($result['success']) {
            try {
                // Extract version from health check response if available
                $healthData = $result['data'] ?? [];
                $partnerVersion = $healthData['version'] ?? $healthData['api_version'] ?? $healthData['platform_version'] ?? null;

                // Fetch the partner's timebank info — with the response envelope
                // now unwrapped, data is the array of timebanks directly.
                $timebankInfo = FederationExternalApiClient::get($id, '/timebanks');
                $memberCount = 0;
                $partnerDisplayName = null;
                $partnerMetadata = null;

                if (($timebankInfo['success'] ?? false) && !empty($timebankInfo['data'])) {
                    $timebanks = $timebankInfo['data'];
                    // The first timebank is the partner's own tenant
                    $firstTb = is_array($timebanks) ? ($timebanks[0] ?? null) : null;
                    if ($firstTb) {
                        $memberCount = (int) ($firstTb['member_count'] ?? 0);
                        $partnerDisplayName = $firstTb['name'] ?? null;

                        // Collect metadata from the partner's response
                        $metaFields = array_intersect_key(
                            $firstTb,
                            array_flip(['location', 'country', 'currency', 'timezone', 'language', 'features', 'description', 'tagline'])
                        );
                        if (!empty($metaFields)) {
                            $partnerMetadata = json_encode($metaFields, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                        }
                    }
                }

                // Also try version from timebank data if not found in health response
                if (!$partnerVersion && !empty($healthData['platform'])) {
                    $partnerVersion = $healthData['platform'];
                }

                DB::update(
                    "UPDATE federation_external_partners SET
                        partner_member_count = ?,
                        partner_name = COALESCE(NULLIF(?, ''), partner_name),
                        partner_version = COALESCE(NULLIF(?, ''), partner_version),
                        partner_metadata = COALESCE(NULLIF(?, ''), partner_metadata),
                        last_sync_at = NOW(),
                        error_count = 0,
                        last_error = NULL
                     WHERE id = ? AND tenant_id = ?",
                    [$memberCount, $partnerDisplayName, $partnerVersion, $partnerMetadata, $id, $tenantId]
                );
            } catch (\Throwable $e) {
                // Non-critical — still update last_sync_at
                DB::update(
                    "UPDATE federation_external_partners SET last_sync_at = NOW(), error_count = 0, last_error = NULL WHERE id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                );
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
            $logs = FederationExternalPartnerService::getLogs($id, $tenantId, 100);
            return $this->respondWithData($logs);
        } catch (\Exception $e) {
            return $this->respondWithError('FETCH_FAILED', __('api.partner_logs_fetch_failed'), null, 500);
        }
    }
}
