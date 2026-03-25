<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\SafeguardingPreferenceService;
use Illuminate\Http\JsonResponse;

/**
 * Admin CRUD for tenant safeguarding options.
 *
 * These are the checkboxes/options shown to members during onboarding
 * and in their settings. Admins configure them per tenant, optionally
 * starting from a country preset.
 *
 * GET    /v2/admin/safeguarding/options          — List all options (incl. inactive)
 * POST   /v2/admin/safeguarding/options          — Create option
 * PUT    /v2/admin/safeguarding/options/{id}     — Update option
 * DELETE /v2/admin/safeguarding/options/{id}     — Deactivate option
 * PUT    /v2/admin/safeguarding/options/reorder  — Reorder options
 */
class AdminSafeguardingOptionsController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET /v2/admin/safeguarding/options */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        return $this->respondWithData(
            SafeguardingPreferenceService::getAllOptionsForTenant($tenantId)
        );
    }

    /** POST /v2/admin/safeguarding/options */
    public function store(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $optionKey = trim($this->input('option_key', ''));
        $label = trim($this->input('label', ''));

        if (empty($optionKey)) {
            return $this->respondWithError('VALIDATION_ERROR', 'option_key is required', 'option_key', 422);
        }

        if (empty($label)) {
            return $this->respondWithError('VALIDATION_ERROR', 'label is required', 'label', 422);
        }

        // Sanitize option_key: lowercase, alphanumeric + underscores only
        $optionKey = preg_replace('/[^a-z0-9_]/', '_', strtolower($optionKey));

        $data = [
            'option_key' => $optionKey,
            'option_type' => $this->input('option_type', 'checkbox'),
            'label' => $label,
            'description' => $this->input('description'),
            'help_url' => $this->input('help_url'),
            'sort_order' => (int) $this->input('sort_order', 0),
            'is_active' => filter_var($this->input('is_active', true), FILTER_VALIDATE_BOOLEAN),
            'is_required' => filter_var($this->input('is_required', false), FILTER_VALIDATE_BOOLEAN),
            'select_options' => $this->input('select_options'),
            'triggers' => $this->input('triggers', []),
        ];

        // Validate option_type
        $allowedTypes = ['checkbox', 'info', 'select'];
        if (!in_array($data['option_type'], $allowedTypes, true)) {
            return $this->respondWithError('VALIDATION_ERROR', 'option_type must be one of: checkbox, info, select', 'option_type', 422);
        }

        // Validate triggers
        if (!empty($data['triggers']) && !is_array($data['triggers'])) {
            return $this->respondWithError('VALIDATION_ERROR', 'triggers must be a JSON object', 'triggers', 422);
        }

        $existingCount = \App\Models\TenantSafeguardingOption::where('tenant_id', $tenantId)->count();
        if ($existingCount >= 50) {
            return $this->respondWithError('LIMIT_EXCEEDED', 'Maximum 50 safeguarding options per tenant', null, 422);
        }

        try {
            $option = SafeguardingPreferenceService::createOption($tenantId, $data);
            return $this->respondWithData($option->toArray(), 201);
        } catch (\Illuminate\Database\QueryException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return $this->respondWithError('DUPLICATE', "An option with key '{$optionKey}' already exists", 'option_key', 409);
            }
            throw $e;
        }
    }

    /** PUT /v2/admin/safeguarding/options/{id} */
    public function update(int $id): JsonResponse
    {
        $this->requireAdmin();

        $data = $this->getAllInput();
        unset($data['id'], $data['tenant_id'], $data['option_key']); // Immutable fields

        if (empty($data)) {
            return $this->respondWithError('VALIDATION_ERROR', 'No updateable fields provided', null, 422);
        }

        $success = SafeguardingPreferenceService::updateOption($id, $data);

        if (!$success) {
            return $this->respondWithError('NOT_FOUND', 'Option not found', null, 404);
        }

        return $this->respondWithData(['message' => 'Option updated']);
    }

    /** DELETE /v2/admin/safeguarding/options/{id} */
    public function destroy(int $id): JsonResponse
    {
        $this->requireAdmin();

        $success = SafeguardingPreferenceService::deleteOption($id);

        if (!$success) {
            return $this->respondWithError('NOT_FOUND', 'Option not found', null, 404);
        }

        return $this->respondWithData(['message' => 'Option deactivated']);
    }

    /** PUT /v2/admin/safeguarding/options/reorder */
    public function reorder(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $order = $this->input('order', []);
        if (empty($order) || !is_array($order)) {
            return $this->respondWithError('VALIDATION_ERROR', 'order must be a non-empty object of {id: sort_order}', 'order', 422);
        }

        // Validate all option IDs belong to current tenant
        $submittedIds = array_map('intval', array_keys($order));
        $validCount = \App\Models\TenantSafeguardingOption::where('tenant_id', $tenantId)
            ->whereIn('id', $submittedIds)
            ->count();

        if ($validCount !== count($submittedIds)) {
            return $this->respondWithError('VALIDATION_ERROR', 'One or more option IDs are invalid', 'order', 422);
        }

        SafeguardingPreferenceService::reorderOptions($tenantId, $order);

        return $this->respondWithData(['message' => 'Options reordered']);
    }
}
