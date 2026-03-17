<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexus\Services\GroupExchangeService;

/**
 * GroupExchangeController -- Group time exchanges.
 *
 * Converted from legacy delegation to direct static service calls.
 */
class GroupExchangeController extends BaseApiController
{
    protected bool $isV2Api = true;

    /** GET /api/v2/group-exchanges */
    public function index(): JsonResponse
    {
        $userId = $this->requireAuth();

        $filters = [
            'status' => $this->query('status'),
            'limit' => $this->queryInt('limit', 20, 1, 100),
            'offset' => $this->queryInt('offset', 0, 0),
        ];

        $result = GroupExchangeService::listForUser($userId, $filters);

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
            return $this->respondWithError('VALIDATION_ERROR', 'Title is required', 'title', 400);
        }

        if (empty($data['total_hours']) || (float) $data['total_hours'] <= 0) {
            return $this->respondWithError('VALIDATION_ERROR', 'Total hours must be greater than 0', 'total_hours', 400);
        }

        $id = GroupExchangeService::create($userId, $data);

        if (!$id) {
            return $this->respondWithError('INTERNAL_ERROR', 'Failed to create exchange', null, 500);
        }

        // Add participants if provided inline
        $participants = $data['participants'] ?? [];
        foreach ($participants as $p) {
            if (!empty($p['user_id']) && !empty($p['role'])) {
                GroupExchangeService::addParticipant(
                    $id,
                    (int) $p['user_id'],
                    $p['role'],
                    (float) ($p['hours'] ?? 0),
                    (float) ($p['weight'] ?? 1.0)
                );
            }
        }

        $exchange = GroupExchangeService::get($id);

        return $this->respondWithData($exchange, null, 201);
    }

    /** GET /api/v2/group-exchanges/{id} */
    public function show(int $id): JsonResponse
    {
        $this->requireAuth();

        $exchange = GroupExchangeService::get($id);

        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', 'Not found', null, 404);
        }

        $exchange['calculated_split'] = GroupExchangeService::calculateSplit($id);

        return $this->respondWithData($exchange);
    }

    /** PUT /api/v2/group-exchanges/{id} */
    public function update(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $exchange = GroupExchangeService::get($id);

        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', 'Not found', null, 404);
        }

        if ((int) $exchange['organizer_id'] !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'Only the organizer can update', null, 403);
        }

        if (in_array($exchange['status'], ['completed', 'cancelled'], true)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Cannot update a completed or cancelled exchange', null, 400);
        }

        $data = $this->getAllInput();
        GroupExchangeService::update($id, $data);

        $updated = GroupExchangeService::get($id);

        return $this->respondWithData($updated);
    }

    /** DELETE /api/v2/group-exchanges/{id} */
    public function destroy(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $exchange = GroupExchangeService::get($id);

        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', 'Not found', null, 404);
        }

        if ((int) $exchange['organizer_id'] !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'Only the organizer can cancel', null, 403);
        }

        GroupExchangeService::updateStatus($id, 'cancelled');

        return $this->respondWithData(['message' => 'Exchange cancelled']);
    }

    /** POST /api/v2/group-exchanges/{id}/participants */
    public function addParticipant($id): JsonResponse
    {
        $this->requireAuth();

        $exchange = GroupExchangeService::get((int) $id);

        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', 'Not found', null, 404);
        }

        $data = $this->getAllInput();

        if (empty($data['user_id']) || empty($data['role'])) {
            return $this->respondWithError('VALIDATION_ERROR', 'user_id and role are required', null, 400);
        }

        $ok = GroupExchangeService::addParticipant(
            (int) $id,
            (int) $data['user_id'],
            $data['role'],
            (float) ($data['hours'] ?? 0),
            (float) ($data['weight'] ?? 1.0)
        );

        if (!$ok) {
            return $this->respondWithError('VALIDATION_ERROR', 'Failed to add participant (may already exist)', null, 400);
        }

        $updated = GroupExchangeService::get((int) $id);

        return $this->respondWithData($updated);
    }

    /** DELETE /api/v2/group-exchanges/{id}/participants/{userId} */
    public function removeParticipant($id, $userId): JsonResponse
    {
        $this->requireAuth();

        GroupExchangeService::removeParticipant((int) $id, (int) $userId);

        $updated = GroupExchangeService::get((int) $id);

        return $this->respondWithData($updated);
    }

    /** POST /api/v2/group-exchanges/{id}/confirm */
    public function confirm(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $exchange = GroupExchangeService::get($id);

        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', 'Not found', null, 404);
        }

        GroupExchangeService::confirmParticipation($id, $userId);

        $updated = GroupExchangeService::get($id);

        return $this->respondWithData($updated);
    }

    /** POST /api/v2/group-exchanges/{id}/complete */
    public function complete(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $exchange = GroupExchangeService::get($id);

        if (!$exchange) {
            return $this->respondWithError('NOT_FOUND', 'Not found', null, 404);
        }

        if ((int) $exchange['organizer_id'] !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'Only the organizer can complete', null, 403);
        }

        $result = GroupExchangeService::complete($id);

        if (!$result['success']) {
            return $this->respondWithError('VALIDATION_ERROR', $result['error'], null, 400);
        }

        return $this->respondWithData([
            'message' => 'Exchange completed successfully',
            'transaction_ids' => $result['transaction_ids'],
        ]);
    }
}
