<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Services\IdeationChallengeService;

/**
 * Admin Ideation / Challenges API Controller
 *
 * GET    /api/v2/admin/ideation                  - List all challenges
 * GET    /api/v2/admin/ideation/{id}             - Challenge detail
 * DELETE /api/v2/admin/ideation/{id}             - Delete challenge
 * POST   /api/v2/admin/ideation/{id}/status      - Update challenge status
 */
class AdminIdeationApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function index(): void
    {
        $this->requireAdmin();

        $filters = [
            'status' => $this->query('status'),
            'search' => $this->query('search'),
            'page' => max(1, $this->queryInt('page', 1)),
            'limit' => min(200, max(1, $this->queryInt('limit', 50))),
        ];

        $result = IdeationChallengeService::getAllChallenges($filters);

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

        $challenge = IdeationChallengeService::getChallengeById($id);

        if (!$challenge) {
            $this->respondWithError('NOT_FOUND', 'Challenge not found', null, 404);
            return;
        }

        $this->respondWithData($challenge);
    }

    public function destroy(int $id): void
    {
        $adminId = $this->requireAdmin();

        $challenge = IdeationChallengeService::getChallengeById($id);
        if (!$challenge) {
            $this->respondWithError('NOT_FOUND', 'Challenge not found', null, 404);
            return;
        }

        $deleted = IdeationChallengeService::deleteChallenge($id, $adminId);

        if ($deleted) {
            $this->respondWithData(['deleted' => true, 'id' => $id]);
        } else {
            $this->respondWithError('DELETE_FAILED', 'Failed to delete challenge', null, 400);
        }
    }

    public function updateStatus(int $id): void
    {
        $adminId = $this->requireAdmin();

        $status = $this->input('status');
        if (!$status || !in_array($status, ['open', 'closed', 'reviewing', 'archived'], true)) {
            $this->respondWithError('VALIDATION_ERROR', 'Invalid status. Valid: open, closed, reviewing, archived', 'status', 400);
            return;
        }

        $updated = IdeationChallengeService::updateChallengeStatus($id, $adminId, $status);

        if ($updated) {
            $this->respondWithData(['id' => $id, 'status' => $status]);
        } else {
            $this->respondWithError('UPDATE_FAILED', 'Failed to update challenge status', null, 400);
        }
    }
}
