<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Core\TenantContext;
use App\Services\MarketplaceGroupService;
use Illuminate\Support\Facades\DB;

/**
 * MarketplaceGroupController — Group-scoped marketplace endpoints.
 *
 * Provides endpoints for viewing marketplace listings within a group
 * and group marketplace statistics. Requires authentication and
 * group membership.
 */
class MarketplaceGroupController extends BaseApiController
{
    protected bool $isV2Api = true;

    // =====================================================================
    //  Feature gate
    // =====================================================================

    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('marketplace')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', 'The marketplace feature is not enabled for this community.', null, 403)
            );
        }
    }

    /**
     * Ensure the group exists and belongs to the current tenant.
     */
    private function ensureGroup(int $groupId): object
    {
        $group = DB::table('groups')
            ->where('id', $groupId)
            ->where('tenant_id', TenantContext::getId())
            ->first();

        if (!$group) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('NOT_FOUND', 'Group not found.', null, 404)
            );
        }

        return $group;
    }

    // =====================================================================
    //  Endpoints
    // =====================================================================

    /**
     * GET /v2/marketplace/groups/{groupId}/listings
     *
     * Returns marketplace listings created by group members.
     * Requires authentication and group membership.
     */
    public function listings(Request $request, int $groupId): JsonResponse
    {
        $this->ensureFeature();
        $this->ensureGroup($groupId);

        $userId = $request->user()?->id;
        if (!$userId) {
            return $this->respondWithError('UNAUTHORIZED', 'Authentication required.', null, 401);
        }

        // Verify group membership
        if (!MarketplaceGroupService::isGroupMember($groupId, $userId)) {
            return $this->respondWithError('FORBIDDEN', 'You must be a group member to view group marketplace listings.', null, 403);
        }

        $filters = [
            'category_id' => $request->integer('category_id') ?: null,
            'search' => $request->string('search')->toString() ?: null,
            'price_min' => $request->float('price_min') ?: null,
            'price_max' => $request->float('price_max') ?: null,
            'condition' => $request->string('condition')->toString() ?: null,
            'sort' => $request->string('sort')->toString() ?: 'newest',
            'limit' => $request->integer('limit', 20),
            'cursor' => $request->string('cursor')->toString() ?: null,
            'current_user_id' => $userId,
        ];

        $result = MarketplaceGroupService::getGroupListings($groupId, $filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /v2/marketplace/groups/{groupId}/stats
     *
     * Returns marketplace statistics for a group.
     * Requires authentication and group membership.
     */
    public function stats(Request $request, int $groupId): JsonResponse
    {
        $this->ensureFeature();
        $this->ensureGroup($groupId);

        $userId = $request->user()?->id;
        if (!$userId) {
            return $this->respondWithError('UNAUTHORIZED', 'Authentication required.', null, 401);
        }

        if (!MarketplaceGroupService::isGroupMember($groupId, $userId)) {
            return $this->respondWithError('FORBIDDEN', 'You must be a group member to view group marketplace stats.', null, 403);
        }

        $stats = MarketplaceGroupService::getGroupMarketplaceStats($groupId);

        return $this->respondWithData($stats);
    }
}
