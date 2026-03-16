<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AdminSettingsController -- Tenant settings and feature toggles.
 *
 * All methods require admin authentication.
 */
class AdminSettingsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /** GET /api/v2/admin/settings */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $settings = DB::select(
            'SELECT setting_key, setting_value FROM tenant_settings WHERE tenant_id = ?',
            [$tenantId]
        );

        $result = [];
        foreach ($settings as $s) {
            $result[$s->setting_key] = $s->setting_value;
        }

        return $this->respondWithData($result);
    }

    /** PUT /api/v2/admin/settings */
    public function update(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $data = $this->getAllInput();

        foreach ($data as $key => $value) {
            DB::statement(
                'INSERT INTO tenant_settings (tenant_id, setting_key, setting_value) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                [$tenantId, $key, $value]
            );
        }

        return $this->respondWithData(['updated' => count($data)]);
    }

    /** GET /api/v2/admin/settings/features */
    public function features(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $features = DB::select(
            'SELECT feature_key, is_enabled FROM tenant_features WHERE tenant_id = ?',
            [$tenantId]
        );

        $result = [];
        foreach ($features as $f) {
            $result[$f->feature_key] = (bool) $f->is_enabled;
        }

        return $this->respondWithData($result);
    }

    /** POST /api/v2/admin/settings/features/toggle */
    public function toggleFeature(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $feature = $this->requireInput('feature');
        $enabled = $this->inputBool('enabled', true);

        DB::statement(
            'INSERT INTO tenant_features (tenant_id, feature_key, is_enabled) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)',
            [$tenantId, $feature, $enabled ? 1 : 0]
        );

        return $this->respondWithData(['feature' => $feature, 'enabled' => $enabled]);
    }
}
