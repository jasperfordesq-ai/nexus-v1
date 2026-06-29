<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\StaticPublicPageContentService;
use Illuminate\Http\JsonResponse;

class StaticPublicPageController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly StaticPublicPageContentService $content,
    ) {
    }

    public function show(string $pageKey): JsonResponse
    {
        $page = $this->content->find($pageKey);

        if ($page === null) {
            return $this->respondNotFound(__('api.page_not_found'), 'RESOURCE_NOT_FOUND');
        }

        return $this->respondWithData($page);
    }
}
