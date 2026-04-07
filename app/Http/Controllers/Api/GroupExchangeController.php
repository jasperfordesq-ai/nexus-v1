<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupExchangeService;

/**
 * GroupExchangeController -- Group time exchanges.
 *
 * Converted from legacy delegation to direct static service calls.
 */
class GroupExchangeController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GroupExchangeService $groupExchangeService,
    ) {}

    /** GET /api/v2/group-exchanges */
    public function index(): JsonResponse
    {
        $userId = $this->requireAuth();

        $filters = [
            'status' => $this->query('status'),
            'limit' => $this->queryInt('limit', 20, 1, 100),
            'offset' => $this->queryInt('offset', 0, 0),
        ];

        $result = $this->groupExchangeService->listForUser($userId, $filters);

        return $this->respondWithData([
            'data' => $result['items'],
            'has_more' => $result['has_more'],
        ]);
    }

    /** POST /api/v2/group-exchanges */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();

        $data = $this->getAllInput();

        if (empty($data['title'])) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.title_required'), 'title', 400);
        }

        if (empty($data['total_hours']) || (float) $data['total_hours'] <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.total_hours_gt_zero'), 'total_hours', 400);
        }

        $id = $this->groupExchangeService->create($userId, $data);

        if (!$id) {
            return $this->respondWithError('INTERNAL_ERROR', __('api.create_failed', ['resource' => 'exchange']), null, 500);
        }

        // Add participants if provided inline
        $participants = $data['participants'] ?? [];
        foreach ($participants as $p) {
            if (!empty($p['user_id']) && !empty($p['role'])) {
                $this->groupExchangeService->addParticipant(
                    $id,
                    (int) $p['user_id'],
                    $p['role'],
                    (float) ($p['hours'] ?? 0),
                    (float) ($p['weight'] ?? 1.0)
                );
            }
        }

        $exchange = $this->groupExchangeService->get($id);

        return $this->respondWithData($exchange, null, 201);
    }

    /** GET /api/v2/group-exchanges/{id} */
    public function show(int $id): JsonResponse
    {
        $this->requireAuth();

        $exchange = $this->groupExchangeService->get($id);

        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', __('api.not_found', ['model' => 'Exchange']), null, 404);
        }

        $exchange['calculated_split'] = $this->groupExchangeService->calculateSplit($id);

        return $this->respondWithData($exchange);
    }

    /** PUT /api/v2/group-exchanges/{id} */
    public function update(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $exchange = $this->groupExchangeService->get($id);

        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', __('api.not_found', ['model' => 'Exchange']), null, 404);
        }

        if ((int) $exchange['organizer_id'] !== $userId) {
            return $this->respondWithError('FORBIDDEN', __('api.organizer_only_update'), null, 403);
        }

        if (in_array($exchange['status'], ['completed', 'cancelled'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.cannot_update_completed_exchange'), null, 400);
        }

        $data = $this->getAllInput();
        $this->groupExchangeService->update($id, $data);

        $updated = $this->groupExchangeService->get($id);

        return $this->respondWithData($updated);
    }

    /** DELETE /api/v2/group-exchanges/{id} */
    public function destroy(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $exchange = $this->groupExchangeService->get($id);

        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', __('api.not_found', ['model' => 'Exchange']), null, 404);
        }

        if ((int) $exchange['organizer_id'] !== $userId) {
            return $this->respondWithError('FORBIDDEN', __('api.organizer_only_cancel'), null, 403);
        }

        $this->groupExchangeService->updateStatus($id, 'cancelled');

        return $this->respondWithData(['message' => __('api_controllers_1.group_exchange.exchange_cancelled')]);
    }

    /** POST /api/v2/group-exchanges/{id}/participants */
    public function addParticipant($id): JsonResponse
    {
        $this->requireAuth();

        $exchange = $this->groupExchangeService->get((int) $id);

        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', __('api.not_found', ['model' => 'Exchange']), null, 404);
        }

        $data = $this->getAllInput();

        if (empty($data['user_id']) || empty($data['role'])) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.user_id_and_role_required'), null, 400);
        }

        $ok = $this->groupExchangeService->addParticipant(
            (int) $id,
            (int) $data['user_id'],
            $data['role'],
            (float) ($data['hours'] ?? 0),
            (float) ($data['weight'] ?? 1.0)
        );

        if (!$ok) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.failed_add_participant'), null, 400);
        }

        $updated = $this->groupExchangeService->get((int) $id);

        return $this->respondWithData($updated);
    }

    /** DELETE /api/v2/group-exchanges/{id}/participants/{userId} */
    public function removeParticipant($id, $userId): JsonResponse
    {
        $this->requireAuth();

        $this->groupExchangeService->removeParticipant((int) $id, (int) $userId);

        $updated = $this->groupExchangeService->get((int) $id);

        return $this->respondWithData($updated);
    }

    /** POST /api/v2/group-exchanges/{id}/confirm */
    public function confirm(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $exchange = $this->groupExchangeService->get($id);

        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', __('api.not_found', ['model' => 'Exchange']), null, 404);
        }

        $this->groupExchangeService->confirmParticipation($id, $userId);

        $updated = $this->groupExchangeService->get($id);

        return $this->respondWithData($updated);
    }

    /** POST /api/v2/group-exchanges/{id}/complete */
    public function complete(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $exchange = $this->groupExchangeService->get($id);

        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', __('api.not_found', ['model' => 'Exchange']), null, 404);
        }

        if ((int) $exchange['organizer_id'] !== $userId) {
            return $this->respondWithError('FORBIDDEN', __('api.organizer_only_complete'), null, 403);
        }

        $result = $this->groupExchangeService->complete($id);

        if (!$result['success']) {
            return $this->respondWithError('VALIDATION_ERROR', $result['error'], null, 400);
        }

        return $this->respondWithData([
            'message' => __('api_controllers_1.group_exchange.exchange_completed'),
            'transaction_ids' => $result['transaction_ids'],
        ]);
    }
}
