<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\CaringCommunity\ExternalIntegrationBacklogService;
use Illuminate\Http\JsonResponse;

/**
 * AG87 — External Integration Backlog admin endpoints.
 *
 * Tracks partner-dependent external integrations (banking, payment, AHV,
 * Spitex, municipal master-data, postal services, etc.) that the Caring
 * Community module may need but which depend on an external owner / DSA /
 * sandbox before they can be built and shipped.
 *
 * Tenant-scoped via TenantContext::getId() — different cantons may have
 * different status for the same integration. Feature gate `caring_community`
 * enforced inline as defence in depth on top of the route-level admin
 * middleware.
 */
class ExternalIntegrationController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly ExternalIntegrationBacklogService $service,
    ) {
    }

    /** GET /v2/admin/caring-community/external-integrations */
    public function index(): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        return $this->respondWithData(
            $this->service->list(TenantContext::getId()),
        );
    }

    /** POST /v2/admin/caring-community/external-integrations/seed-defaults */
    public function seedDefaults(): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $result = $this->service->seedDefaults(TenantContext::getId());

        if (isset($result['error']) && $result['error'] === 'already_seeded') {
            return $this->respondWithError(
                'ALREADY_SEEDED',
                'Backlog already contains items — refusing to seed defaults.',
                null,
                409,
            );
        }

        return $this->respondWithData([
            'items' => $result['items'] ?? [],
            'last_updated_at' => $result['last_updated_at'] ?? null,
        ]);
    }

    /** POST /v2/admin/caring-community/external-integrations */
    public function store(): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $payload = (array) request()->all();
        $result = $this->service->create(TenantContext::getId(), $payload);

        if (isset($result['errors']) && $result['errors'] !== []) {
            return $this->respondWithErrors($result['errors'], 422);
        }

        return $this->respondWithData(['item' => $result['item'] ?? null], null, 201);
    }

    /** PUT /v2/admin/caring-community/external-integrations/{itemId} */
    public function update(string $itemId): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $payload = (array) request()->all();
        $result = $this->service->update(TenantContext::getId(), $itemId, $payload);

        if (isset($result['error']) && $result['error'] === 'not_found') {
            return $this->respondNotFound('Integration backlog item not found.');
        }

        if (isset($result['errors']) && $result['errors'] !== []) {
            return $this->respondWithErrors($result['errors'], 422);
        }

        return $this->respondWithData(['item' => $result['item'] ?? null]);
    }

    /** DELETE /v2/admin/caring-community/external-integrations/{itemId} */
    public function destroy(string $itemId): JsonResponse
    {
        $guard = $this->guard();
        if ($guard !== null) {
            return $guard;
        }

        $result = $this->service->delete(TenantContext::getId(), $itemId);

        if (isset($result['error']) && $result['error'] === 'not_found') {
            return $this->respondNotFound('Integration backlog item not found.');
        }

        return $this->respondWithData(['ok' => true]);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    private function guard(): ?JsonResponse
    {
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondForbidden('Caring Community feature is not enabled for this tenant.');
        }

        return null;
    }
}

/*
 * Routes to register in routes/api.php:
 *   GET    /v2/admin/caring-community/external-integrations              => index
 *   POST   /v2/admin/caring-community/external-integrations/seed-defaults => seedDefaults
 *   POST   /v2/admin/caring-community/external-integrations              => store
 *   PUT    /v2/admin/caring-community/external-integrations/{itemId}     => update
 *   DELETE /v2/admin/caring-community/external-integrations/{itemId}     => destroy
 */
