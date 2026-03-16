<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AdminFederationController -- Federation management (controls, timebank listing).
 *
 * All methods require admin authentication.
 */
class AdminFederationController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /** GET /api/v2/admin/federation */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $features = DB::select(
            'SELECT * FROM federation_tenant_features WHERE tenant_id = ?',
            [$tenantId]
        );
        $whitelist = DB::select(
            'SELECT * FROM federation_tenant_whitelist WHERE tenant_id = ?',
            [$tenantId]
        );

        return $this->respondWithData([
            'features' => $features,
            'whitelist' => $whitelist,
        ]);
    }

    /** GET /api/v2/admin/federation/timebanks */
    public function timebanks(): JsonResponse
    {
        $this->requireAdmin();

        $timebanks = DB::select(
            'SELECT id, name, slug, domain FROM tenants WHERE status = ? ORDER BY name',
            ['active']
        );

        return $this->respondWithData($timebanks);
    }

    /** GET /api/v2/admin/federation/controls */
    public function controls(): JsonResponse
    {
        $this->requireAdmin();

        $controls = DB::select('SELECT * FROM federation_system_control ORDER BY control_key');

        $result = [];
        foreach ($controls as $c) {
            $result[$c->control_key] = $c->control_value;
        }

        return $this->respondWithData($result);
    }

    /** PUT /api/v2/admin/federation/controls */
    public function updateControls(): JsonResponse
    {
        $this->requireSuperAdmin();
        $data = $this->getAllInput();
        $updated = 0;

        foreach ($data as $key => $value) {
            $affected = DB::update(
                'UPDATE federation_system_control SET control_value = ? WHERE control_key = ?',
                [$value, $key]
            );
            $updated += $affected;
        }

        return $this->respondWithData(['updated' => $updated]);
    }
}
