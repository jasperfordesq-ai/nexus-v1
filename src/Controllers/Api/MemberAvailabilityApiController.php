<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\MemberAvailabilityService;

/**
 * MemberAvailabilityApiController - API for member availability calendar
 *
 * Endpoints:
 * - GET    /api/v2/users/me/availability       - Get own availability
 * - GET    /api/v2/users/{id}/availability      - Get member's availability
 * - PUT    /api/v2/users/me/availability        - Set bulk availability
 * - PUT    /api/v2/users/me/availability/{day}  - Set day availability
 * - POST   /api/v2/users/me/availability/date   - Add specific date availability
 * - DELETE /api/v2/users/me/availability/{id}   - Delete a slot
 * - GET    /api/v2/members/availability/compatible - Find compatible times
 * - GET    /api/v2/members/availability/available  - Get available members
 */
class MemberAvailabilityApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/users/me/availability
     */
    public function getMyAvailability(): void
    {
        $userId = $this->getUserId();
        $availability = MemberAvailabilityService::getUserAvailability($userId);
        $this->respondWithData($availability);
    }

    /**
     * GET /api/v2/users/{id}/availability
     */
    public function getUserAvailability(int $id): void
    {
        $this->rateLimit('availability_view', 30, 60);
        $availability = MemberAvailabilityService::getUserAvailability($id);
        $this->respondWithData($availability);
    }

    /**
     * PUT /api/v2/users/me/availability
     * Set bulk availability (all 7 days at once)
     *
     * Request Body:
     * {
     *   "schedule": {
     *     "0": [{ "start_time": "09:00", "end_time": "12:00" }],
     *     "1": [{ "start_time": "10:00", "end_time": "17:00" }],
     *     ...
     *   }
     * }
     */
    public function setBulkAvailability(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('availability_update', 10, 60);

        $data = $this->getAllInput();
        $schedule = $data['schedule'] ?? [];

        if (empty($schedule) || !is_array($schedule)) {
            $this->respondWithError('VALIDATION_ERROR', 'schedule is required and must be an object', 'schedule', 400);
            return;
        }

        $success = MemberAvailabilityService::setBulkAvailability($userId, $schedule);

        if (!$success) {
            $this->respondWithErrors(MemberAvailabilityService::getErrors(), 422);
        }

        $availability = MemberAvailabilityService::getUserAvailability($userId);
        $this->respondWithData($availability);
    }

    /**
     * PUT /api/v2/users/me/availability/{day}
     * Set availability for a specific day of week
     *
     * Request Body:
     * {
     *   "slots": [
     *     { "start_time": "09:00", "end_time": "12:00", "note": "Morning" },
     *     { "start_time": "14:00", "end_time": "17:00", "note": "Afternoon" }
     *   ]
     * }
     */
    public function setDayAvailability(int $day): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('availability_update', 10, 60);

        $data = $this->getAllInput();
        $slots = $data['slots'] ?? [];

        $success = MemberAvailabilityService::setDayAvailability($userId, $day, $slots);

        if (!$success) {
            $this->respondWithErrors(MemberAvailabilityService::getErrors(), 422);
        }

        $availability = MemberAvailabilityService::getUserAvailability($userId);
        $this->respondWithData($availability);
    }

    /**
     * POST /api/v2/users/me/availability/date
     * Add a one-off availability for a specific date
     *
     * Request Body:
     * {
     *   "date": "2026-03-15",
     *   "start_time": "10:00",
     *   "end_time": "15:00",
     *   "note": "Available for special event"
     * }
     */
    public function addSpecificDate(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('availability_add_date', 10, 60);

        $data = $this->getAllInput();
        $slotId = MemberAvailabilityService::addSpecificDate($userId, $data);

        if ($slotId === null) {
            $this->respondWithErrors(MemberAvailabilityService::getErrors(), 422);
        }

        $availability = MemberAvailabilityService::getUserAvailability($userId);
        $this->respondWithData($availability, null, 201);
    }

    /**
     * DELETE /api/v2/users/me/availability/{id}
     */
    public function deleteSlot(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();

        MemberAvailabilityService::deleteSlot($userId, $id);
        $this->respondWithData(['message' => 'Slot deleted']);
    }

    /**
     * GET /api/v2/members/availability/compatible
     * Find compatible times with another member
     *
     * Query: ?user_id=123
     */
    public function findCompatibleTimes(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('availability_compatible', 20, 60);

        $otherUserId = $this->queryInt('user_id');
        if (!$otherUserId) {
            $this->respondWithError('VALIDATION_ERROR', 'user_id query parameter is required', 'user_id', 400);
            return;
        }

        $compatible = MemberAvailabilityService::findCompatibleTimes($userId, $otherUserId);
        $this->respondWithData($compatible);
    }

    /**
     * GET /api/v2/members/availability/available
     * Get members available on a specific day/time
     *
     * Query: ?day=1&time=14:00&limit=20
     */
    public function getAvailableMembers(): void
    {
        $this->rateLimit('availability_search', 20, 60);

        $day = $this->queryInt('day');
        if ($day === null || $day < 0 || $day > 6) {
            $this->respondWithError('VALIDATION_ERROR', 'day query parameter is required (0-6)', 'day', 400);
            return;
        }

        $time = $this->query('time');
        $limit = $this->queryInt('limit', 50, 1, 100);

        $members = MemberAvailabilityService::getAvailableMembers($day, $time, $limit);
        $this->respondWithData($members);
    }
}
