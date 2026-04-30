<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunity\FederationPeerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use RuntimeException;

/**
 * AdminFederationPeerController — AG23 follow-up
 *
 * Tenant-admin CRUD for cross-platform federation peers. Each peer is an
 * agreement to send/receive hour transfers with a remote NEXUS install.
 *
 * The shared secret is returned on `create` and `rotate-secret` only — never
 * on subsequent reads — so the admin must copy it to the remote side
 * immediately. Listing and reading peers redacts the secret.
 */
class AdminFederationPeerController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(private readonly FederationPeerService $peers)
    {
    }

    public function index(): JsonResponse
    {
        $disabled = $this->guard();
        if ($disabled) {
            return $disabled;
        }

        return $this->respondWithData([
            'peers' => $this->peers->listForTenant(TenantContext::getId()),
        ]);
    }

    public function store(): JsonResponse
    {
        $disabled = $this->guard();
        if ($disabled) {
            return $disabled;
        }

        $input = $this->getAllInput();
        $validator = Validator::make($input, [
            'peer_slug'     => 'required|string|max:100',
            'display_name'  => 'required|string|max:255',
            'base_url'      => 'required|url|max:500',
            'shared_secret' => 'nullable|string|min:32|max:128',
            'status'        => 'nullable|in:pending,active,suspended',
            'notes'         => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                $validator->errors()->first() ?: __('api.validation_failed'),
                null,
                422,
            );
        }

        try {
            $peer = $this->peers->create(TenantContext::getId(), $input);
        } catch (InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (RuntimeException $e) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', $e->getMessage(), null, 503);
        }

        return $this->respondWithData($peer, null, 201);
    }

    public function updateStatus(int $id): JsonResponse
    {
        $disabled = $this->guard();
        if ($disabled) {
            return $disabled;
        }

        $input = $this->getAllInput();
        $validator = Validator::make($input, [
            'status' => 'required|in:pending,active,suspended',
        ]);
        if ($validator->fails()) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.validation_failed'), null, 422);
        }

        try {
            $peer = $this->peers->updateStatus(TenantContext::getId(), $id, (string) $input['status']);
        } catch (InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION_ERROR', $e->getMessage(), null, 422);
        } catch (RuntimeException $e) {
            return $this->respondWithError('PEER_NOT_FOUND', $e->getMessage(), null, 404);
        }

        return $this->respondWithData($peer);
    }

    public function rotateSecret(int $id): JsonResponse
    {
        $disabled = $this->guard();
        if ($disabled) {
            return $disabled;
        }

        try {
            $peer = $this->peers->rotateSecret(TenantContext::getId(), $id);
        } catch (RuntimeException $e) {
            return $this->respondWithError('PEER_NOT_FOUND', $e->getMessage(), null, 404);
        }

        return $this->respondWithData($peer);
    }

    public function destroy(int $id): JsonResponse
    {
        $disabled = $this->guard();
        if ($disabled) {
            return $disabled;
        }

        try {
            $this->peers->delete(TenantContext::getId(), $id);
        } catch (RuntimeException $e) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', $e->getMessage(), null, 503);
        }

        return $this->respondWithData(['deleted' => true]);
    }

    private function guard(): ?JsonResponse
    {
        $this->requireAuth();
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403);
        }
        if (! $this->peers->isAvailable()) {
            return $this->respondWithError('SERVICE_UNAVAILABLE', 'Federation peers table is not available.', null, 503);
        }
        return null;
    }
}
