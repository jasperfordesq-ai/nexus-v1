<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Services\GroupAccessService;
use App\Services\GroupMentionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * GroupMentionController — API endpoints for group @mention functionality.
 */
class GroupMentionController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /v2/groups/{id}/mentions/suggest?q=...&limit=10
     *
     * Search group members for @mention autocomplete suggestions.
     */
    public function suggestions(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $parentExists = DB::table('groups')
            ->where('id', $id)
            ->where('tenant_id', (int) TenantContext::getId())
            ->exists();

        if (!$parentExists) {
            return $this->respondWithError('NOT_FOUND', __('api.group_not_found'), null, 404);
        }

        if (!GroupAccessService::canViewMemberContent($id, $userId)) {
            return $this->respondWithError('FORBIDDEN', __('api.group_mentions_member_required'), null, 403);
        }

        $q = $this->query('q', '');
        $limit = $this->queryInt('limit', 10, 1, 50);

        $suggestions = GroupMentionService::getMemberSuggestions($id, $userId, $q, $limit);

        return $this->respondWithData($suggestions);
    }
}
