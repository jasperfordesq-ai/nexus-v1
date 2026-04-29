<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\TenantProvisioning\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

/**
 * AG44 — Public endpoints for self-service tenant provisioning.
 *
 * Routes:
 *   POST /v2/provisioning-requests
 *   GET  /v2/provisioning-requests/check-slug/{slug}
 *   GET  /v2/provisioning-requests/status/{token}
 */
class TenantProvisioningController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function submit(): JsonResponse
    {
        if (! (bool) config('provisioning.public_form_enabled', false)) {
            return $this->respondWithError(
                'PROVISIONING_DISABLED',
                __('api.provisioning_form_disabled'),
                null,
                503
            );
        }

        if (! TenantProvisioningService::isAvailable()) {
            return $this->respondWithError(
                'SERVICE_UNAVAILABLE',
                __('api.provisioning_service_unavailable'),
                null,
                503
            );
        }

        $this->rateLimit('provisioning_submit:' . request()->ip(), 5, 3600);

        $data = $this->getAllInput();

        try {
            $row = TenantProvisioningService::submitRequest(
                $data,
                hash('sha256', (string) request()->ip())
            );
            return $this->respondWithData([
                'id'           => (int) ($row['id'] ?? 0),
                'status'       => $row['status'] ?? 'pending',
                'status_token' => $row['status_token'] ?? null,
            ], null, 201);
        } catch (InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return $this->respondServerError(__('api.provisioning_submit_failed'));
        }
    }

    public function checkSlug(string $slug): JsonResponse
    {
        if (! TenantProvisioningService::isAvailable()) {
            return $this->respondWithData(['available' => false, 'reason' => 'service_unavailable']);
        }

        $this->rateLimit('provisioning_slug:' . request()->ip(), 30, 60);

        $result = TenantProvisioningService::validateSlugAvailable($slug);
        return $this->respondWithData($result);
    }

    public function status(string $token): JsonResponse
    {
        if (! TenantProvisioningService::isAvailable()) {
            return $this->respondNotFound(__('api.provisioning_request_not_found'));
        }

        $row = TenantProvisioningService::getRequestByToken($token);
        if (! $row) {
            return $this->respondNotFound(__('api.provisioning_request_not_found'));
        }

        return $this->respondWithData([
            'org_name'              => $row['org_name'] ?? '',
            'requested_slug'        => $row['requested_slug'] ?? '',
            'status'                => $row['status'] ?? 'pending',
            'provisioned_tenant_id' => $row['provisioned_tenant_id'] ?? null,
            'created_at'            => $row['created_at'] ?? null,
            'reviewed_at'           => $row['reviewed_at'] ?? null,
        ]);
    }
}
