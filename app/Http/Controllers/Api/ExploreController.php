<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\ExploreService;
use Illuminate\Http\JsonResponse;

/**
 * ExploreController — Discover/Explore page API endpoints.
 *
 * Serves aggregated discovery data for the frontend Explore page.
 *
 * Endpoints (v2):
 *   GET  /api/v2/explore                    index()
 *   GET  /api/v2/explore/trending           trending()
 *   GET  /api/v2/explore/popular-listings   popularListings()
 *   GET  /api/v2/explore/category/{slug}    category()
 */
class ExploreController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly ExploreService $exploreService,
    ) {}

    /**
     * Get full explore page data — all sections in one call.
     */
    public function index(): JsonResponse
    {
        $userId = $this->getOptionalUserId();
        $tenantId = $this->getTenantId();

        if ($userId) {
            $data = $this->exploreService->getExploreData($userId);
        } else {
            // Unauthenticated users get global data without personalized recommendations
            $data = $this->exploreService->getExploreData(0);
        }

        return $this->respondWithData($data);
    }

    /**
     * Get trending posts with pagination.
     */
    public function trending(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);

        $result = $this->exploreService->getTrendingPostsPaginated($tenantId, $page, $perPage);

        return $this->respondWithPaginatedCollection(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * Get popular listings with pagination.
     */
    public function popularListings(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);

        $result = $this->exploreService->getPopularListingsPaginated($tenantId, $page, $perPage);

        return $this->respondWithPaginatedCollection(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page']
        );
    }

    /**
     * Browse listings by category.
     */
    public function category(string $slug): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);

        $result = $this->exploreService->getListingsByCategory($tenantId, $slug, $page, $perPage);

        if ($result['category'] === null) {
            return $this->respondWithError('CATEGORY_NOT_FOUND', 'Category not found', null, 404);
        }

        return $this->respondWithPaginatedCollection(
            $result['items'],
            $result['total'],
            $result['page'],
            $result['per_page'],
        );
    }
}
