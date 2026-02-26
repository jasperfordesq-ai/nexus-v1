<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\ApiErrorCodes;
use Nexus\Core\TenantContext;
use Nexus\Models\Page;

/**
 * PagesPublicApiController - Public API for viewing published CMS pages
 *
 * Endpoints:
 * - GET /api/v2/pages/{slug} - Get a published page by slug
 */
class PagesPublicApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/pages/{slug}
     * Returns a published page's content for public display.
     */
    public function show(string $slug): void
    {
        $tenantId = TenantContext::getId();

        // If resolved to master tenant (1) and an explicit context_tenant is provided by the
        // React frontend, use it. This handles unauthenticated public page requests where
        // the X-Tenant-ID header is unavailable (no JWT), but the React app knows the tenant
        // from the URL (e.g. /hour-timebank/page/test) and passes it as a query param.
        if ($tenantId === 1 && !empty($_GET['context_tenant']) && is_numeric($_GET['context_tenant'])) {
            $contextTenantId = (int) $_GET['context_tenant'];
            if ($contextTenantId > 1) {
                $tenantId = $contextTenantId;
            }
        }

        $page = Page::findBySlug($slug, $tenantId);

        if (!$page) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Page not found', null, 404);
            return;
        }

        $this->respondWithData([
            'id' => (int) $page['id'],
            'title' => $page['title'] ?? '',
            'slug' => $page['slug'] ?? '',
            'content' => $page['content'] ?? '',
            'meta_description' => $page['meta_description'] ?? '',
            'created_at' => $page['created_at'],
            'updated_at' => $page['updated_at'],
        ]);
    }
}
