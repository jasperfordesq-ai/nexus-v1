<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * SkillsController -- Skill categories and search for matching.
 */
class SkillsController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET /api/v2/skills/categories */
    public function categories(): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $results = DB::select(
            "SELECT id, name, slug, icon FROM skill_categories WHERE tenant_id = ? ORDER BY name",
            [$tenantId]
        );

        return $this->respondWithData(array_map(fn($r) => (array)$r, $results));
    }

    /** GET /api/v2/skills/search?q= */
    public function search(): JsonResponse
    {
        $q = $this->query('q', '');
        $limit = $this->queryInt('limit', 20, 1, 100);
        $tenantId = $this->getTenantId();

        $results = DB::select(
            "SELECT id, name, category_id FROM skills WHERE tenant_id = ? AND name LIKE ? ORDER BY name LIMIT ?",
            [$tenantId, '%' . $q . '%', $limit]
        );

        return $this->respondWithData(array_map(fn($r) => (array)$r, $results));
    }
}
