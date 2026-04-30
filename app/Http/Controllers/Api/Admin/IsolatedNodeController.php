<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Core\TenantContext;
use App\Http\Controllers\Api\BaseApiController;
use App\Services\CaringCommunity\IsolatedNodeReadinessService;
use Illuminate\Http\JsonResponse;

/**
 * AG85 — Isolated-Node Decision Gate admin controller.
 *
 * Captures the ownership decisions a canton-controlled NEXUS deployment must
 * make before launch (hosting, SMTP, storage, backups, update cadence, source
 * release workflow, telemetry default, federation key exchange, DPO
 * appointment, incident runbook). Gate is "closed" only when every item has
 * status='decided'.
 */
class IsolatedNodeController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly IsolatedNodeReadinessService $service,
    ) {
    }

    /**
     * GET /v2/admin/caring-community/isolated-node
     */
    public function index(): JsonResponse
    {
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError(
                'FEATURE_DISABLED',
                __('api.service_unavailable'),
                null,
                403,
            );
        }

        $tenantId = TenantContext::getId();

        return $this->respondWithData($this->service->get($tenantId));
    }

    /**
     * PUT /v2/admin/caring-community/isolated-node/items/{itemKey}
     *
     * Body: { value?: mixed, owner?: string|null, status?: string, notes?: string|null }
     */
    public function update(string $itemKey): JsonResponse
    {
        $this->requireAdmin();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondWithError(
                'FEATURE_DISABLED',
                __('api.service_unavailable'),
                null,
                403,
            );
        }

        $allowedKeys = array_keys($this->service->schema());
        if (!in_array($itemKey, $allowedKeys, true)) {
            return $this->respondWithError(
                'INVALID_ITEM_KEY',
                'Unknown decision-gate item: ' . $itemKey,
                'item_key',
                404,
            );
        }

        $tenantId = TenantContext::getId();

        $payload = [];
        foreach (['value', 'owner', 'status', 'notes'] as $field) {
            if (request()->exists($field)) {
                $payload[$field] = $this->input($field);
            }
        }

        if ($payload === []) {
            return $this->respondWithError(
                'EMPTY_PAYLOAD',
                'At least one of value, owner, status, or notes must be provided',
                null,
                422,
            );
        }

        $result = $this->service->update($tenantId, $itemKey, $payload);

        if (isset($result['errors'])) {
            return $this->respondWithErrors($result['errors'], 422);
        }

        return $this->respondWithData([
            'item' => $result['item'] ?? null,
            'gate' => $result['gate'] ?? null,
        ]);
    }
}

/*
 * Routes to register in routes/api.php:
 *   GET /v2/admin/caring-community/isolated-node => index
 *   PUT /v2/admin/caring-community/isolated-node/items/{itemKey} => update
 */
