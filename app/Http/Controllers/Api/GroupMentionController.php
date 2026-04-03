<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\GroupMentionService;
use Illuminate\Http\JsonResponse;

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
        $this->requireAuth();

        $q = $this->query('q', '');
        $limit = $this->queryInt('limit', 10, 1, 50);

        $suggestions = GroupMentionService::getMemberSuggestions($id, $q, $limit);

        return $this->respondWithData($suggestions);
    }
}
