<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\GroupExchangeService;

/**
 * GroupExchangesApiController
 *
 * API endpoints for multi-participant group exchanges.
 * Supports creating, managing participants, confirming, and completing exchanges.
 *
 * Endpoints:
 * GET    /api/v2/group-exchanges               - List user's group exchanges
 * POST   /api/v2/group-exchanges               - Create new group exchange
 * GET    /api/v2/group-exchanges/{id}           - Get exchange details with participants
 * PUT    /api/v2/group-exchanges/{id}           - Update exchange details (organizer)
 * DELETE /api/v2/group-exchanges/{id}           - Cancel exchange (organizer)
 * POST   /api/v2/group-exchanges/{id}/participants          - Add participant
 * DELETE /api/v2/group-exchanges/{id}/participants/{userId}  - Remove participant
 * POST   /api/v2/group-exchanges/{id}/confirm   - Confirm participation
 * POST   /api/v2/group-exchanges/{id}/complete  - Complete exchange (organizer)
 */
class GroupExchangesApiController extends BaseApiController
{
    /**
     * List user's group exchanges
     *
     * GET /api/v2/group-exchanges
     * Query params: status, limit, offset
     */
    public function index(): void
    {
        $userId = $this->requireAuth();

        $filters = [
            'status' => $this->query('status'),
            'limit' => $this->queryInt('limit', 20, 1, 100),
            'offset' => $this->queryInt('offset', 0, 0),
        ];

        $result = GroupExchangeService::listForUser($userId, $filters);

        $this->jsonResponse([
            'data' => $result['items'],
            'has_more' => $result['has_more'],
        ]);
    }

    /**
     * Create a new group exchange
     *
     * POST /api/v2/group-exchanges
     * Body: { title, description?, split_type?, total_hours, listing_id?, participants?[] }
     */
    public function store(): void
    {
        $userId = $this->requireAuth();

        $data = $this->getAllInput();

        if (empty($data['title'])) {
            $this->error('Title is required', 400);
            return;
        }

        if (empty($data['total_hours']) || (float) $data['total_hours'] <= 0) {
            $this->error('Total hours must be greater than 0', 400);
            return;
        }

        $id = GroupExchangeService::create($userId, $data);

        if (!$id) {
            $this->error('Failed to create exchange', 500);
            return;
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

        $this->jsonResponse(['data' => $exchange], 201);
    }

    /**
     * Get exchange details with participants and calculated split
     *
     * GET /api/v2/group-exchanges/{id}
     */
    public function show(int $id): void
    {
        $this->requireAuth();

        $exchange = GroupExchangeService::get($id);

        if (!$exchange) {
            $this->error('Not found', 404);
            return;
        }

        // Include calculated split
        $exchange['calculated_split'] = GroupExchangeService::calculateSplit($id);

        $this->jsonResponse(['data' => $exchange]);
    }

    /**
     * Update exchange details (organizer only)
     *
     * PUT /api/v2/group-exchanges/{id}
     * Body: { title?, description?, split_type?, total_hours? }
     */
    public function update(int $id): void
    {
        $userId = $this->requireAuth();

        $exchange = GroupExchangeService::get($id);

        if (!$exchange) {
            $this->error('Not found', 404);
            return;
        }

        if ((int) $exchange['organizer_id'] !== $userId) {
            $this->error('Only the organizer can update', 403);
            return;
        }

        if (in_array($exchange['status'], ['completed', 'cancelled'], true)) {
            $this->error('Cannot update a completed or cancelled exchange', 400);
            return;
        }

        $data = $this->getAllInput();
        GroupExchangeService::update($id, $data);

        $updated = GroupExchangeService::get($id);

        $this->jsonResponse(['data' => $updated]);
    }

    /**
     * Cancel exchange (organizer only)
     *
     * DELETE /api/v2/group-exchanges/{id}
     */
    public function destroy(int $id): void
    {
        $userId = $this->requireAuth();

        $exchange = GroupExchangeService::get($id);

        if (!$exchange) {
            $this->error('Not found', 404);
            return;
        }

        if ((int) $exchange['organizer_id'] !== $userId) {
            $this->error('Only the organizer can cancel', 403);
            return;
        }

        GroupExchangeService::updateStatus($id, 'cancelled');

        $this->jsonResponse(['message' => 'Exchange cancelled']);
    }

    /**
     * Add a participant to the exchange
     *
     * POST /api/v2/group-exchanges/{id}/participants
     * Body: { user_id, role, hours?, weight? }
     */
    public function addParticipant(int $id): void
    {
        $this->requireAuth();

        $exchange = GroupExchangeService::get($id);

        if (!$exchange) {
            $this->error('Not found', 404);
            return;
        }

        $data = $this->getAllInput();

        if (empty($data['user_id']) || empty($data['role'])) {
            $this->error('user_id and role are required', 400);
            return;
        }

        $ok = GroupExchangeService::addParticipant(
            $id,
            (int) $data['user_id'],
            $data['role'],
            (float) ($data['hours'] ?? 0),
            (float) ($data['weight'] ?? 1.0)
        );

        if (!$ok) {
            $this->error('Failed to add participant (may already exist)', 400);
            return;
        }

        $updated = GroupExchangeService::get($id);

        $this->jsonResponse(['data' => $updated]);
    }

    /**
     * Remove a participant from the exchange
     *
     * DELETE /api/v2/group-exchanges/{id}/participants/{userId}
     */
    public function removeParticipant(int $id, int $userId): void
    {
        $this->requireAuth();

        GroupExchangeService::removeParticipant($id, $userId);

        $updated = GroupExchangeService::get($id);

        $this->jsonResponse(['data' => $updated]);
    }

    /**
     * Confirm participation (current user confirms their hours)
     *
     * POST /api/v2/group-exchanges/{id}/confirm
     */
    public function confirm(int $id): void
    {
        $userId = $this->requireAuth();

        $exchange = GroupExchangeService::get($id);

        if (!$exchange) {
            $this->error('Not found', 404);
            return;
        }

        GroupExchangeService::confirmParticipation($id, $userId);

        $updated = GroupExchangeService::get($id);

        $this->jsonResponse(['data' => $updated]);
    }

    /**
     * Complete the exchange (organizer only)
     * Creates all transactions atomically after all participants confirm.
     *
     * POST /api/v2/group-exchanges/{id}/complete
     */
    public function complete(int $id): void
    {
        $userId = $this->requireAuth();

        $exchange = GroupExchangeService::get($id);

        if (!$exchange) {
            $this->error('Not found', 404);
            return;
        }

        if ((int) $exchange['organizer_id'] !== $userId) {
            $this->error('Only the organizer can complete', 403);
            return;
        }

        $result = GroupExchangeService::complete($id);

        if (!$result['success']) {
            $this->error($result['error'], 400);
            return;
        }

        $this->jsonResponse([
            'message' => 'Exchange completed successfully',
            'transaction_ids' => $result['transaction_ids'],
        ]);
    }
}
