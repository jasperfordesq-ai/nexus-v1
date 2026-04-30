<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\CaringCommunity\SuccessStoryService;
use Illuminate\Http\JsonResponse;

/**
 * AG91 — Success-Story Proof Cards member-facing endpoint.
 *
 * Returns only PUBLISHED stories for the current tenant. Available to any
 * authenticated member when the caring_community feature is enabled. This is
 * not a public page — members must be logged in to read the gallery.
 */
class SuccessStoryController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly SuccessStoryService $service,
    ) {
    }

    /** GET /v2/caring-community/success-stories */
    public function index(): JsonResponse
    {
        $this->requireAuth();

        if (!TenantContext::hasFeature('caring_community')) {
            return $this->respondForbidden('Caring Community feature is not enabled for this tenant.');
        }

        $items = $this->service->listStories(TenantContext::getId(), true);

        return $this->respondWithData([
            'items' => $items,
        ]);
    }
}

/*
 * Routes to register in routes/api.php:
 *   GET /v2/caring-community/success-stories => index  (->withoutMiddleware(EnsureIsAdmin))
 */
