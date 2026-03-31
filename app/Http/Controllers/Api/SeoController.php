<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * SeoController -- SEO metadata and redirect management.
 *
 * metadata() is public; redirects() requires admin.
 */
class SeoController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /** GET /api/v2/seo/metadata/{slug} */
    public function metadata(string $slug): JsonResponse
    {
        $tenantId = $this->getTenantId();

        // entity_type='page' with the slug path as an entity_id lookup
        $meta = DB::selectOne(
            "SELECT meta_title AS title, meta_description AS description,
                    og_image_url AS og_image, canonical_url,
                    CASE WHEN noindex = 1 THEN 'noindex, nofollow' ELSE 'index, follow' END AS robots
             FROM seo_metadata
             WHERE tenant_id = ? AND entity_type = 'page' AND entity_id = (
                 SELECT id FROM pages WHERE tenant_id = ? AND slug = ? LIMIT 1
             )",
            [$tenantId, $tenantId, $slug]
        );

        if ($meta === null) {
            return $this->respondWithData([
                'title' => null,
                'description' => null,
                'og_image' => null,
                'canonical_url' => null,
                'robots' => 'index, follow',
            ]);
        }

        return $this->respondWithData($meta);
    }

    /** GET /api/v2/seo/redirects */
    public function redirects(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 50, 1, 200);
        $offset = ($page - 1) * $perPage;

        $items = DB::select(
            'SELECT id, tenant_id, source_url, destination_url, hits, created_at
             FROM seo_redirects WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$tenantId, $perPage, $offset]
        );
        $total = DB::selectOne(
            'SELECT COUNT(*) as cnt FROM seo_redirects WHERE tenant_id = ?',
            [$tenantId]
        )->cnt;

        return $this->respondWithPaginatedCollection($items, (int) $total, $page, $perPage);
    }
}
