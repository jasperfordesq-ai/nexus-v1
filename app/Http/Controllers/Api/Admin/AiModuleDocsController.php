<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Services\AI\AiModuleDocsService;
use Illuminate\Http\JsonResponse;

/**
 * Admin CRUD for ai_module_docs — the per-tenant, plain-language
 * "how each module works" content injected into the AI chat prompt.
 */
class AiModuleDocsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(private readonly AiModuleDocsService $service) {}

    /** GET /api/v2/admin/ai-module-docs */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        return $this->respondWithData($this->service->listForTenant($tenantId));
    }

    /** POST /api/v2/admin/ai-module-docs */
    public function store(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $userId = $this->requireAuth();
        try {
            $doc = $this->service->upsert($tenantId, $userId, request()->all());
            return $this->respondWithData($doc, null, 201);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION', $e->getMessage(), null, 422);
        }
    }

    /** PUT /api/v2/admin/ai-module-docs/{id} */
    public function update(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $userId = $this->requireAuth();
        // Force the slug to match the stored doc so admin can't rename it
        // mid-edit (avoids breaking keyword references elsewhere).
        try {
            $existing = $this->service->getById($tenantId, $id);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('NOT_FOUND', $e->getMessage(), null, 404);
        }
        $payload = request()->all();
        $payload['module_slug'] = $existing['module_slug'];
        try {
            $doc = $this->service->upsert($tenantId, $userId, $payload);
            return $this->respondWithData($doc);
        } catch (\InvalidArgumentException $e) {
            return $this->respondWithError('VALIDATION', $e->getMessage(), null, 422);
        }
    }

    /** DELETE /api/v2/admin/ai-module-docs/{id} */
    public function destroy(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $deleted = $this->service->delete($tenantId, $id);
        return $this->respondWithData(['deleted' => $deleted]);
    }

    /** POST /api/v2/admin/ai-module-docs/seed-defaults */
    public function seedDefaults(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $userId = $this->requireAuth();
        $inserted = $this->service->seedDefaultsForTenant($tenantId, $userId);
        return $this->respondWithData(['inserted' => $inserted]);
    }
}
