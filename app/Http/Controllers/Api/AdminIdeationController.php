<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\IdeationChallengeService;

/**
 * AdminIdeationController -- Admin ideation challenge moderation.
 *
 * All endpoints require admin authentication.
 */
class AdminIdeationController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly IdeationChallengeService $ideationChallengeService,
    ) {}

    /**
     * GET /api/v2/admin/ideation
     *
     * Query params: status, search, page, limit
     */
    public function index(): JsonResponse
    {
        $this->requireAdmin();

        $filters = [
            'status' => $this->query('status'),
            'search' => $this->query('search'),
            'page' => max(1, $this->queryInt('page', 1)),
            'limit' => min(200, max(1, $this->queryInt('limit', 50))),
        ];

        $result = $this->ideationChallengeService->getAllChallenges($filters);

        $items = $result['data'] ?? $result['items'] ?? $result;
        $total = $result['total'] ?? (is_array($items) ? count($items) : 0);
        $page = $filters['page'];
        $limit = $filters['limit'];

        if (is_array($items) && !isset($result['total'])) {
            $offset = ($page - 1) * $limit;
            $paged = array_slice($items, $offset, $limit);
            return $this->respondWithPaginatedCollection($paged, count($items), $page, $limit);
        }

        return $this->respondWithPaginatedCollection($items, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/ideation/{id}
     */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();

        $challenge = $this->ideationChallengeService->getChallengeById($id);

        if (!$challenge) {
            return $this->respondWithError('NOT_FOUND', __('api.challenge_not_found'), null, 404);
        }

        return $this->respondWithData($challenge);
    }

    /**
     * DELETE /api/v2/admin/ideation/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();

        $challenge = $this->ideationChallengeService->getChallengeById($id);
        if (!$challenge) {
            return $this->respondWithError('NOT_FOUND', __('api.challenge_not_found'), null, 404);
        }

        $deleted = $this->ideationChallengeService->deleteChallenge($id, $adminId);

        if ($deleted) {
            return $this->respondWithData(['deleted' => true, 'id' => $id]);
        }

        return $this->respondWithError('DELETE_FAILED', __('api.challenge_delete_failed'), null, 400);
    }

    /**
     * PUT /api/v2/admin/ideation/{id}/status
     */
    public function updateStatus(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();

        $status = $this->input('status');
        if (!$status || !in_array($status, ['open', 'closed', 'reviewing', 'archived'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.invalid_challenge_status'), 'status', 400);
        }

        $updated = $this->ideationChallengeService->updateChallengeStatus($id, $adminId, $status);

        if ($updated) {
            return $this->respondWithData(['id' => $id, 'status' => $status]);
        }

        return $this->respondWithError('UPDATE_FAILED', __('api.challenge_status_update_failed'), null, 400);
    }
}
