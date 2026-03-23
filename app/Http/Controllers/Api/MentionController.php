<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\MentionService;
use Illuminate\Http\JsonResponse;

/**
 * MentionController — API endpoints for @mention autocomplete and user mentions.
 *
 * Endpoints:
 *   GET  /api/v2/mentions/search?q=...  — Autocomplete user search for @mention
 *   GET  /api/v2/mentions/me            — Get posts/comments where current user was mentioned
 */
class MentionController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/mentions/search?q=john
     *
     * Autocomplete user search for @mention suggestions.
     * Returns top 10 matching users, prioritizing connections.
     */
    public function search(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('mention_search', 60, 60);

        $query = trim($this->query('q', ''));

        if (strlen($query) < 1) {
            return $this->respondWithData([]);
        }

        $tenantId = $this->getTenantId();
        $limit = $this->queryInt('limit', 10, 1, 20);

        $users = MentionService::searchUsers($query, $tenantId, $userId, $limit);

        return $this->respondWithData($users);
    }

    /**
     * GET /api/v2/mentions/me
     *
     * Get posts/comments where the current user was mentioned.
     * Supports cursor-based pagination.
     */
    public function myMentions(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('mention_list', 30, 60);

        $limit = $this->queryInt('limit', 20, 1, 50);
        $cursor = $this->query('cursor');

        $result = MentionService::getMentionsForUser($userId, $limit, $cursor);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $limit,
            $result['has_more']
        );
    }
}
