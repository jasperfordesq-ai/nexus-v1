<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Services\PollService;

/**
 * Admin Polls API Controller
 *
 * GET    /api/v2/admin/polls              - List all polls
 * GET    /api/v2/admin/polls/{id}         - Poll detail
 * DELETE /api/v2/admin/polls/{id}         - Delete poll
 */
class AdminPollsApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function index(): void
    {
        $this->requireAdmin();

        $filters = [
            'search' => $this->query('search'),
            'page' => max(1, $this->queryInt('page', 1)),
            'limit' => min(200, max(1, $this->queryInt('limit', 50))),
        ];

        $result = PollService::getAll($filters);

        $items = $result['data'] ?? $result['items'] ?? $result;
        $total = $result['total'] ?? (is_array($items) ? count($items) : 0);
        $page = $filters['page'];
        $limit = $filters['limit'];

        if (is_array($items) && !isset($result['total'])) {
            $offset = ($page - 1) * $limit;
            $paged = array_slice($items, $offset, $limit);
            $this->respondWithPaginatedCollection($paged, count($items), $page, $limit);
        } else {
            $this->respondWithPaginatedCollection($items, $total, $page, $limit);
        }
    }

    public function show(int $id): void
    {
        $this->requireAdmin();

        $poll = PollService::getById($id);

        if (!$poll) {
            $this->respondWithError('NOT_FOUND', 'Poll not found', null, 404);
            return;
        }

        $this->respondWithData($poll);
    }

    public function destroy(int $id): void
    {
        $adminId = $this->requireAdmin();

        $poll = PollService::getById($id);
        if (!$poll) {
            $this->respondWithError('NOT_FOUND', 'Poll not found', null, 404);
            return;
        }

        $deleted = PollService::delete($id, $adminId);

        if ($deleted) {
            $this->respondWithData(['deleted' => true, 'id' => $id]);
        } else {
            $this->respondWithError('DELETE_FAILED', 'Failed to delete poll', null, 400);
            return;
        }
    }
}
