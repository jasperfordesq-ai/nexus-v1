<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\PageService;

/**
 * PagesController -- Tenant-managed static content pages.
 */
class PagesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly PageService $pageService,
    ) {}

    /** GET /api/v2/pages/{slug} */
    public function show(string $slug): JsonResponse
    {
        $page = $this->pageService->getBySlug($slug, $this->getTenantId());
        
        if ($page === null) {
            return $this->respondWithError('NOT_FOUND', 'Page not found', null, 404);
        }
        
        return $this->respondWithData($page);
    }

}
