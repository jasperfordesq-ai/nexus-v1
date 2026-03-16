<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\EventService;
use Illuminate\Http\JsonResponse;

/**
 * EventsController - CRUD for community events.
 *
 * Endpoints (v2):
 *   GET    /api/v2/events          index()
 *   GET    /api/v2/events/{id}     show()
 *   POST   /api/v2/events          store()
 *   PUT    /api/v2/events/{id}     update()
 *   DELETE /api/v2/events/{id}     destroy()
 */
class EventsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly EventService $eventService,
    ) {}

    /**
     * List events with optional filtering and pagination.
     *
     * Query params: category_id, q, upcoming, cursor, per_page.
     */
    public function index(): JsonResponse
    {
        $userId = $this->getOptionalUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('category_id')) {
            $filters['category_id'] = $this->queryInt('category_id');
        }
        if ($this->query('q')) {
            $filters['search'] = $this->query('q');
        }
        if ($this->queryBool('upcoming')) {
            $filters['upcoming'] = true;
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }
        if ($userId !== null) {
            $filters['current_user_id'] = $userId;
        }

        $result = $this->eventService->getAll($filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'] ?? null,
            $filters['limit'],
            $result['has_more'] ?? false
        );
    }

    /**
     * Get a single event by ID.
     */
    public function show(int $id): JsonResponse
    {
        $userId = $this->getOptionalUserId();
        $event = $this->eventService->getById($id, $userId);

        if ($event === null) {
            return $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
        }

        return $this->respondWithData($event);
    }

    /**
     * Create a new event. Requires authentication.
     */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('event_create', 10, 60);

        $event = $this->eventService->create($userId, $this->getAllInput());

        return $this->respondWithData($event, null, 201);
    }

    /**
     * Update an existing event. Only the creator may update.
     */
    public function update(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('event_update', 20, 60);

        $existing = $this->eventService->getById($id);

        if ($existing === null) {
            return $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
        }
        if ((int) ($existing['user_id'] ?? 0) !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'You do not own this event', null, 403);
        }

        $event = $this->eventService->update($id, $this->getAllInput());

        return $this->respondWithData($event);
    }

    /**
     * Delete an event. Only the creator may delete.
     */
    public function destroy(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('event_delete', 10, 60);

        $existing = $this->eventService->getById($id);

        if ($existing === null) {
            return $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
        }
        if ((int) ($existing['user_id'] ?? 0) !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'You do not own this event', null, 403);
        }

        $this->eventService->delete($id);

        return $this->noContent();
    }
}
