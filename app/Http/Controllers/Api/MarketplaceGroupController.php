<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Core\TenantContext;
use App\Models\Group;
use App\Services\GroupAccessService;
use App\Services\MarketplaceGroupService;

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
                $this->respondWithError('FEATURE_DISABLED', __('api.service_unavailable'), null, 403)
            );
        }
    }

    /**
     * Ensure the group exists and belongs to the current tenant.
     */
    private function ensureGroupAccess(int $groupId, int $userId): void
    {
        if (! Group::query()->whereKey($groupId)->exists()) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('NOT_FOUND', __('api.group_not_found'), null, 404)
            );
        }

        if (! GroupAccessService::canViewMemberContent($groupId, $userId)) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FORBIDDEN', __('api.group_marketplace_member_required'), null, 403)
            );
        }
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

        $userId = $request->user()?->id;
        if (!$userId) {
            return $this->respondWithError('UNAUTHORIZED', __('api_controllers_2.marketplace_group.auth_required'), null, 401);
        }
        $this->ensureGroupAccess($groupId, (int) $userId);

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

        $userId = $request->user()?->id;
        if (!$userId) {
            return $this->respondWithError('UNAUTHORIZED', __('api_controllers_2.marketplace_group.auth_required'), null, 401);
        }
        $this->ensureGroupAccess($groupId, (int) $userId);

        $stats = MarketplaceGroupService::getGroupMarketplaceStats($groupId);

        return $this->respondWithData($stats);
    }
}
