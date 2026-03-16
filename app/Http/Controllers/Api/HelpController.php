<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\HelpService;

/**
 * HelpController -- FAQ and help content for members.
 */
class HelpController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly HelpService $helpService,
    ) {}

    /** GET /api/v2/help/faqs */
    public function faqs(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $categoryId = $this->queryInt('category_id');
        $q = $this->query('q');
        
        $faqs = $this->helpService->getFaqs($tenantId, $categoryId, $q);
        
        return $this->respondWithData($faqs);
    }

}
