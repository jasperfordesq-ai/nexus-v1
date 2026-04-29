<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Api\BaseApiController;
use App\Jobs\ProvisionTenantJob;
use App\Services\TenantProvisioning\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * AG44 — Super-admin queue for tenant provisioning.
 *
 * Routes (all require super_admin):
 *   GET  /v2/super-admin/provisioning-requests
 *   GET  /v2/super-admin/provisioning-requests/{id}
 *   POST /v2/super-admin/provisioning-requests/{id}/approve
 *   POST /v2/super-admin/provisioning-requests/{id}/reject
 *   POST /v2/super-admin/provisioning-requests/{id}/retry
 */
class TenantProvisioningController extends BaseApiController
{
    protected bool $isV2Api = true;

    private function requireSuperAdmin(): int
    {
        $userId = $this->requireAuth();
        $user = $this->resolveUser();
        $role = $user->role ?? 'member';
        $isSuper = ($user->is_super_admin ?? false)
            || in_array($role, ['super_admin', 'god'], true);

        if (! $isSuper) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->error(__('api.admin_access_required'), 403, 'AUTH_INSUFFICIENT_PERMISSIONS')
            );
        }

        return $userId;
    }

    public function index(): JsonResponse
    {
        $this->requireSuperAdmin();

        if (! TenantProvisioningService::isAvailable()) {
            return $this->respondWithData([]);
        }

        $status = $this->query('status');
        $rows   = TenantProvisioningService::listRequests($status ?: null);
        return $this->respondWithData($rows);
    }

    public function show(int $id): JsonResponse
    {
        $this->requireSuperAdmin();

        if (! TenantProvisioningService::isAvailable()) {
            return $this->respondNotFound(__('api.provisioning_request_not_found'));
        }

        $row = TenantProvisioningService::getRequest($id);
        if (! $row) {
            return $this->respondNotFound(__('api.provisioning_request_not_found'));
        }
        return $this->respondWithData($row);
    }

    public function approve(int $id): JsonResponse
    {
        $reviewerId = $this->requireSuperAdmin();

        if (! TenantProvisioningService::isAvailable()) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', __('api.provisioning_service_unavailable'), null, 503);
        }

        $row = TenantProvisioningService::getRequest($id);
        if (! $row) {
            return $this->respondNotFound(__('api.provisioning_request_not_found'));
        }

        // Dispatch async — pipeline can take a few seconds.
        ProvisionTenantJob::dispatch($id, $reviewerId);

        return $this->respondWithData(['queued' => true, 'request_id' => $id]);
    }

    public function reject(int $id): JsonResponse
    {
        $reviewerId = $this->requireSuperAdmin();

        if (! TenantProvisioningService::isAvailable()) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', __('api.provisioning_service_unavailable'), null, 503);
        }

        $reason = (string) $this->input('reason', '');
        if (trim($reason) === '') {
            return $this->respondWithError('VALIDATION_ERROR', __('api.provisioning_reject_reason_required'), 'reason', 422);
        }

        try {
            $row = TenantProvisioningService::reject($id, $reason, $reviewerId);
            return $this->respondWithData($row);
        } catch (InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        }
    }

    public function retry(int $id): JsonResponse
    {
        $reviewerId = $this->requireSuperAdmin();

        if (! TenantProvisioningService::isAvailable()) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', __('api.provisioning_service_unavailable'), null, 503);
        }

        ProvisionTenantJob::dispatch($id, $reviewerId);
        return $this->respondWithData(['queued' => true, 'request_id' => $id]);
    }
}
