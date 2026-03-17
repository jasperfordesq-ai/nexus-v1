<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\HelpService;

/**
 * HelpController — Eloquent-powered FAQ and help content for members.
 *
 * Public FAQ endpoint is fully migrated to Eloquent. Admin endpoints
 * delegate to legacy controller for now.
 */
class HelpController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly HelpService $helpService,
    ) {}

    // -----------------------------------------------------------------
    //  GET /api/v2/help/faqs (public)
    // -----------------------------------------------------------------

    public function faqs(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $categoryId = $this->queryInt('category_id');
        $q = $this->query('q');

        $faqs = $this->helpService->getFaqs($tenantId, $categoryId, $q);

        return $this->respondWithData($faqs);
    }

    /** Alias for routes that use getFaqs instead of faqs */
    public function getFaqs(): JsonResponse
    {
        return $this->faqs();
    }

    // -----------------------------------------------------------------
    //  Admin endpoints (delegated to legacy)
    // -----------------------------------------------------------------

    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }

    public function adminGetFaqs(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\HelpApiController::class, 'adminGetFaqs');
    }

    public function adminCreateFaq(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\HelpApiController::class, 'adminCreateFaq');
    }

    public function adminUpdateFaq(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\HelpApiController::class, 'adminUpdateFaq', [$id]);
    }

    public function adminDeleteFaq(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\HelpApiController::class, 'adminDeleteFaq', [$id]);
    }

    public function feedback(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\HelpController::class, 'feedback');
    }
}
