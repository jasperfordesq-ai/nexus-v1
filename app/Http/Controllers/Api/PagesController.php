<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * PagesController — Tenant-managed static content pages.
 *
 * Native Eloquent implementation — no legacy delegation.
 */
class PagesController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/pages/{slug}
     *
     * Get a published page by slug.
     * Supports context_tenant query param for unauthenticated requests
     * where the React app knows the tenant from the URL.
     */
    public function show(string $slug): JsonResponse
    {
        $tenantId = $this->getTenantId();

        // If resolved to master tenant (1) and an explicit context_tenant is provided
        // by the React frontend, use it. Handles unauthenticated public page requests
        // where X-Tenant-ID header is unavailable (no JWT).
        $contextTenant = $this->query('context_tenant');
        if ($tenantId === 1 && $contextTenant !== null && is_numeric($contextTenant)) {
            $contextTenantId = (int) $contextTenant;
            if ($contextTenantId > 1) {
                $tenantId = $contextTenantId;
            }
        }

        $page = DB::table('pages')
            ->where('slug', $slug)
            ->where('tenant_id', $tenantId)
            ->where('is_published', true)
            ->first();

        if (! $page) {
            return $this->respondWithError('NOT_FOUND', 'Page not found', null, 404);
        }

        return $this->respondWithData([
            'id'               => (int) $page->id,
            'title'            => $page->title ?? '',
            'slug'             => $page->slug ?? '',
            'content'          => $page->content ?? '',
            'meta_description' => $page->meta_description ?? '',
            'created_at'       => $page->created_at,
            'updated_at'       => $page->updated_at,
        ]);
    }
}
