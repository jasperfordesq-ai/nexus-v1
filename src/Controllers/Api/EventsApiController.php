<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\EventService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ImageUploader;
use Nexus\Models\EventRsvp;

/**
 * EventsApiController - RESTful API for events
 *
 * Provides event management endpoints with standardized response format.
 *
 * Endpoints:
 * - GET    /api/v2/events              - List events (cursor paginated)
 * - GET    /api/v2/events/nearby       - Get nearby upcoming events (geospatial)
 * - GET    /api/v2/events/{id}         - Get single event
 * - POST   /api/v2/events              - Create event
 * - PUT    /api/v2/events/{id}         - Update event
 * - DELETE /api/v2/events/{id}         - Delete event
 * - POST   /api/v2/events/{id}/rsvp    - Set RSVP status
 * - DELETE /api/v2/events/{id}/rsvp    - Remove RSVP
 * - GET    /api/v2/events/{id}/attendees - List attendees
 * - POST   /api/v2/events/{id}/image   - Upload event image
 * - POST   /api/v2/events/{id}/cancel  - Cancel event (E5)
 * - GET    /api/v2/events/{id}/waitlist - Get waitlist (E3)
 * - POST   /api/v2/events/{id}/waitlist - Join waitlist (E3)
 * - DELETE /api/v2/events/{id}/waitlist - Leave waitlist (E3)
 * - GET    /api/v2/events/{id}/reminders - Get user's reminders (E4)
 * - PUT    /api/v2/events/{id}/reminders - Update reminders (E4)
 * - POST   /api/v2/events/{id}/attendance - Mark attendance (E6)
 * - POST   /api/v2/events/{id}/attendance/bulk - Bulk mark attendance (E6)
 * - GET    /api/v2/events/{id}/attendance - Get attendance records (E6)
 * - GET    /api/v2/events/series       - List event series (E7)
 * - POST   /api/v2/events/series       - Create event series (E7)
 * - GET    /api/v2/events/series/{id}  - Get series events (E7)
 * - POST   /api/v2/events/{id}/series  - Link event to series (E7)
 * - POST   /api/v2/events/recurring    - Create recurring event (E1)
 *
 * Response Format:
 * Success: { "data": {...}, "meta": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "...", "field": "..." }] }
 */
class EventsApiController extends BaseApiController
{
    /**
     * GET /api/v2/events
     *
     * List events with optional filtering and cursor-based pagination.
     *
     * Query Parameters:
     * - when: 'upcoming' (default) or 'past'
     * - category_id: int
     * - group_id: int
     * - user_id: int (filter by organizer)
     * - q: string (search term)
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     *
     * Response: 200 OK with events array and pagination meta
     */
    public function index(): void
    {
        // Optional auth - adds user's RSVP status to results if logged in
        $userId = $this->getOptionalUserId();
        $this->rateLimit('events_list', 60, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
            'when' => $this->query('when', 'upcoming'),
        ];

        if ($this->query('category_id')) {
            $filters['category_id'] = $this->queryInt('category_id');
        }

        if ($this->query('group_id')) {
            $filters['group_id'] = $this->queryInt('group_id');
        }

        if ($this->query('user_id')) {
            $filters['user_id'] = $this->queryInt('user_id');
        }

        if ($this->query('q')) {
            $filters['search'] = $this->query('q');
        }

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = EventService::getAll($filters);

        // Add user's RSVP status to each event if logged in
        if ($userId) {
            foreach ($result['items'] as &$event) {
                $event['user_rsvp'] = EventService::getUserRsvp($event['id'], $userId);
            }
        }

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/events/nearby
     *
     * Get upcoming events near a geographic point.
     *
     * Query Parameters:
     * - lat: float (required) - Latitude
     * - lon: float (required) - Longitude
     * - radius_km: float (default 25) - Search radius in kilometers
     * - category_id: int
     * - per_page: int (default 20, max 100)
     *
     * Response: 200 OK with data array and search meta
     */
    public function nearby(): void
    {
        $lat = $this->query('lat');
        $lon = $this->query('lon');

        if ($lat === null || $lon === null) {
            $this->respondWithError('VALIDATION_ERROR', 'Latitude and longitude are required', null, 400);
            return;
        }

        $lat = (float)$lat;
        $lon = (float)$lon;

        // Validate coordinates
        if ($lat < -90 || $lat > 90) {
            $this->respondWithError('VALIDATION_ERROR', 'Latitude must be between -90 and 90', 'lat', 400);
            return;
        }
        if ($lon < -180 || $lon > 180) {
            $this->respondWithError('VALIDATION_ERROR', 'Longitude must be between -180 and 180', 'lon', 400);
            return;
        }

        $filters = [
            'radius_km' => (float)$this->query('radius_km', '25'),
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('category_id')) {
            $filters['category_id'] = $this->queryInt('category_id');
        }

        $result = EventService::getNearby($lat, $lon, $filters);

        $this->respondWithData($result['items'], [
            'search' => [
                'type' => 'nearby',
                'lat' => $lat,
                'lon' => $lon,
                'radius_km' => $filters['radius_km'],
            ],
            'per_page' => $filters['limit'],
            'has_more' => $result['has_more'],
        ]);
    }

    /**
     * GET /api/v2/events/{id}
     *
     * Get a single event by ID with full details and RSVP counts.
     *
     * Response: 200 OK with event data, or 404 if not found
     */
    public function show(int $id): void
    {
        // Optional auth - adds user's RSVP status
        $userId = $this->getOptionalUserId();
        $this->rateLimit('events_show', 120, 60);

        $event = EventService::getById($id, $userId);

        if (!$event) {
            $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
            return;
        }

        $this->respondWithData($event);
    }

    /**
     * POST /api/v2/events
     *
     * Create a new event.
     *
     * Request Body (JSON):
     * {
     *   "title": "string (required)",
     *   "description": "string",
     *   "location": "string",
     *   "start_time": "datetime (required, ISO 8601)",
     *   "end_time": "datetime",
     *   "category_id": "int",
     *   "group_id": "int",
     *   "latitude": "float",
     *   "longitude": "float",
     *   "federated_visibility": "none|listed|joinable",
     *   "sdg_goals": [1, 2, 3]
     * }
     *
     * Response: 201 Created with new event data
     */
    public function store(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_create', 10, 60);

        $data = $this->getAllInput();

        $eventId = EventService::create($userId, $data);

        if ($eventId === null) {
            $errors = EventService::getErrors();
            $status = 422;

            foreach ($errors as $error) {
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        // Fetch the created event
        $event = EventService::getById($eventId, $userId);

        $this->respondWithData($event, null, 201);
    }

    /**
     * PUT /api/v2/events/{id}
     *
     * Update an existing event.
     *
     * Request Body (JSON): Same as store, all fields optional
     *
     * Response: 200 OK with updated event data
     */
    public function update(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_update', 20, 60);

        $data = $this->getAllInput();

        $success = EventService::update($id, $userId, $data);

        if (!$success) {
            $errors = EventService::getErrors();
            $status = 422;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        // Notify attendees of meaningful changes
        try {
            \Nexus\Services\EventNotificationService::notifyEventUpdated($id, $data);
        } catch (\Throwable $e) {
            error_log("Event update notification error: " . $e->getMessage());
        }

        // Fetch the updated event
        $event = EventService::getById($id, $userId);

        $this->respondWithData($event);
    }

    /**
     * DELETE /api/v2/events/{id}
     *
     * Delete an event.
     *
     * Response: 204 No Content on success
     */
    public function destroy(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_delete', 10, 60);

        $success = EventService::delete($id, $userId);

        if (!$success) {
            $errors = EventService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $this->noContent();
    }

    /**
     * POST /api/v2/events/{id}/rsvp
     *
     * Set RSVP status for an event.
     *
     * Request Body (JSON):
     * {
     *   "status": "going|interested|not_going"
     * }
     *
     * Response: 200 OK with RSVP status and updated counts
     */
    public function rsvp(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_rsvp', 30, 60);

        $status = $this->input('status');

        if (empty($status)) {
            $this->respondWithError('VALIDATION_ERROR', 'RSVP status is required', 'status', 400);
            return;
        }

        $success = EventService::rsvp($id, $userId, $status);

        if (!$success) {
            $errors = EventService::getErrors();
            $httpStatus = 422;

            // Check if user was waitlisted (special case — not a real error)
            $isWaitlisted = false;
            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $httpStatus = 404;
                    break;
                }
                if ($error['code'] === 'EVENT_CANCELLED') {
                    $httpStatus = 409;
                    break;
                }
                if ($error['code'] === 'EVENT_FULL') {
                    $isWaitlisted = true;
                    break;
                }
            }

            // Waitlisted is a 200 with special response, not a true error
            if ($isWaitlisted) {
                $position = EventService::getUserWaitlistPosition($id, $userId);
                $event = EventService::getById($id, $userId);
                $this->respondWithData([
                    'status' => 'waitlisted',
                    'waitlist_position' => $position,
                    'rsvp_counts' => $event['rsvp_counts'] ?? ['going' => 0, 'interested' => 0],
                    'message' => 'Event is full. You have been added to the waitlist.',
                ]);
                return;
            }

            $this->respondWithErrors($errors, $httpStatus);
        }

        // Get updated event with RSVP counts
        $event = EventService::getById($id, $userId);

        // Notify event organizer of RSVP
        try {
            \Nexus\Services\EventNotificationService::notifyRsvp($id, $userId, $status);
        } catch (\Throwable $e) {
            error_log("RSVP notification error: " . $e->getMessage());
        }

        $this->respondWithData([
            'status' => $status,
            'rsvp_counts' => $event['rsvp_counts'] ?? ['going' => 0, 'interested' => 0],
        ]);
    }

    /**
     * DELETE /api/v2/events/{id}/rsvp
     *
     * Remove RSVP from an event.
     *
     * Response: 204 No Content on success
     */
    public function removeRsvp(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_rsvp', 30, 60);

        $success = EventService::removeRsvp($id, $userId);

        if (!$success) {
            $errors = EventService::getErrors();
            $this->respondWithErrors($errors, 400);
        }

        $this->noContent();
    }

    /**
     * GET /api/v2/events/{id}/attendees
     *
     * List event attendees with cursor-based pagination.
     *
     * Query Parameters:
     * - status: 'going' (default), 'interested', 'invited', 'attended'
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     *
     * Response: 200 OK with attendees array and pagination meta
     */
    public function attendees(int $id): void
    {
        // Optional auth
        $this->getOptionalUserId();
        $this->rateLimit('events_attendees', 60, 60);

        // Verify event exists
        $event = EventService::getById($id);
        if (!$event) {
            $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
            return;
        }

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
            'status' => $this->query('status', 'going'),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = EventService::getAttendees($id, $filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * POST /api/v2/events/{id}/image
     *
     * Upload a cover image for an event.
     *
     * Request: multipart/form-data with 'image' file
     *
     * Response: 200 OK with image URL
     */
    public function uploadImage(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_image_upload', 10, 60);

        // Check for uploaded file
        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $this->respondWithError('VALIDATION_ERROR', 'No image file uploaded or upload error', 'image', 400);
            return;
        }

        try {
            $imageUrl = ImageUploader::upload($_FILES['image']);

            $success = EventService::updateImage($id, $userId, $imageUrl);

            if (!$success) {
                $errors = EventService::getErrors();
                $status = 400;

                foreach ($errors as $error) {
                    if ($error['code'] === 'NOT_FOUND') {
                        $status = 404;
                        break;
                    }
                    if ($error['code'] === 'FORBIDDEN') {
                        $status = 403;
                        break;
                    }
                }

                $this->respondWithErrors($errors, $status);
            }

            $this->respondWithData(['image_url' => $imageUrl]);
        } catch (\Exception $e) {
            $this->respondWithError('UPLOAD_FAILED', 'Failed to upload image: ' . $e->getMessage(), 'image', 400);
            return;
        }
    }

    /**
     * POST /api/v2/events/{id}/attendees/{attendeeId}/check-in
     *
     * Check in an attendee at an event. Only the event organizer (or admin) can do this.
     * Creates a time credit transaction for the attendee based on event duration.
     *
     * Response: 200 OK with check-in confirmation
     */
    public function checkIn(int $id, int $attendeeId): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_checkin', 30, 60);

        $tenantId = TenantContext::getId();

        // Verify event exists and belongs to this tenant
        $event = EventService::getById($id);
        if (!$event) {
            $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
            return;
        }

        // Only the organizer or an admin can check in attendees
        $isOrganizer = (int) $event['user_id'] === $userId;
        $isAdmin = false;
        try {
            $adminCheck = Database::query(
                "SELECT role, is_super_admin, is_tenant_super_admin FROM users WHERE id = ? AND tenant_id = ?",
                [$userId, $tenantId]
            )->fetch();
            if ($adminCheck && (
                !empty($adminCheck['is_super_admin']) ||
                !empty($adminCheck['is_tenant_super_admin']) ||
                in_array($adminCheck['role'], ['admin', 'tenant_admin'])
            )) {
                $isAdmin = true;
            }
        } catch (\Exception $e) {
            // Ignore — just means no admin override
        }

        if (!$isOrganizer && !$isAdmin) {
            $this->respondWithError('FORBIDDEN', 'Only the event organizer can check in attendees', null, 403);
            return;
        }

        // Verify the attendee has an RSVP for this event
        $currentStatus = EventRsvp::getUserStatus($id, $attendeeId);
        if (!$currentStatus) {
            $this->respondWithError('VALIDATION_ERROR', 'This user has not RSVPed to this event', null, 422);
            return;
        }
        if ($currentStatus === 'attended') {
            $this->respondWithError('VALIDATION_ERROR', 'This attendee has already been checked in', null, 422);
            return;
        }

        // Calculate event duration (default 1 hour)
        $duration = 1;
        if (!empty($event['start_time']) && !empty($event['end_time'])) {
            $start = strtotime($event['start_time']);
            $end = strtotime($event['end_time']);
            $diff = ($end - $start) / 3600;
            $duration = round($diff, 2);
            if ($duration < 0.5) $duration = 0.5;
        }

        try {
            // Create time credit transaction (organizer → attendee)
            \Nexus\Models\Transaction::create(
                $userId,
                $attendeeId,
                $duration,
                "Event Attendance: " . ($event['title'] ?? 'Event #' . $id)
            );

            // Update RSVP status to 'attended'
            EventRsvp::rsvp($id, $attendeeId, 'attended');

            $this->respondWithData([
                'checked_in' => true,
                'attendee_id' => $attendeeId,
                'event_id' => $id,
                'hours_credited' => $duration,
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('CHECKIN_ERROR', 'Failed to check in attendee: ' . $e->getMessage(), null, 500);
            return;
        }
    }

    // =========================================================================
    // E5: EVENT CANCELLATION
    // =========================================================================

    /**
     * POST /api/v2/events/{id}/cancel
     *
     * Cancel an event and notify all RSVPs.
     *
     * Request Body (JSON):
     * {
     *   "reason": "string (optional cancellation reason)"
     * }
     *
     * Response: 200 OK with cancellation confirmation
     */
    public function cancel(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_cancel', 5, 60);

        $reason = $this->input('reason') ?? '';

        $success = EventService::cancelEvent($id, $userId, $reason);

        if (!$success) {
            $errors = EventService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
                if ($error['code'] === 'ALREADY_CANCELLED') {
                    $status = 409;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $this->respondWithData([
            'cancelled' => true,
            'event_id' => $id,
            'reason' => $reason,
        ]);
    }

    // =========================================================================
    // E3: WAITLIST MANAGEMENT
    // =========================================================================

    /**
     * GET /api/v2/events/{id}/waitlist
     *
     * Get the waitlist for an event (organizer/admin only).
     *
     * Response: 200 OK with waitlist array
     */
    public function waitlist(int $id): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('events_waitlist', 60, 60);

        $event = EventService::getById($id);
        if (!$event) {
            $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
            return;
        }

        $result = EventService::getWaitlist($id, [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ]);

        // Include user's waitlist position if they're on it
        $userPosition = EventService::getUserWaitlistPosition($id, $userId);

        $this->respondWithData($result['items'], [
            'has_more' => $result['has_more'],
            'user_position' => $userPosition,
        ]);
    }

    /**
     * POST /api/v2/events/{id}/waitlist
     *
     * Join the waitlist for a full event.
     *
     * Response: 200 OK with waitlist position
     */
    public function joinWaitlist(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_waitlist', 30, 60);

        $event = EventService::getById($id);
        if (!$event) {
            $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
            return;
        }

        $success = EventService::addToWaitlist($id, $userId);

        if (!$success) {
            $this->respondWithError('WAITLIST_FAILED', 'Failed to join waitlist', null, 400);
            return;
        }

        $position = EventService::getUserWaitlistPosition($id, $userId);

        $this->respondWithData([
            'waitlisted' => true,
            'position' => $position,
        ]);
    }

    /**
     * DELETE /api/v2/events/{id}/waitlist
     *
     * Leave the waitlist for an event.
     *
     * Response: 204 No Content
     */
    public function leaveWaitlist(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_waitlist', 30, 60);

        EventService::removeFromWaitlist($id, $userId);
        $this->noContent();
    }

    // =========================================================================
    // E4: EVENT REMINDERS
    // =========================================================================

    /**
     * GET /api/v2/events/{id}/reminders
     *
     * Get user's reminders for an event.
     *
     * Response: 200 OK with reminders array
     */
    public function getReminders(int $id): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('events_reminders', 60, 60);

        $reminders = EventService::getUserReminders($id, $userId);

        $this->respondWithData($reminders);
    }

    /**
     * PUT /api/v2/events/{id}/reminders
     *
     * Update user's reminder preferences for an event.
     *
     * Request Body (JSON):
     * {
     *   "reminders": [
     *     { "minutes": 60, "type": "both" },
     *     { "minutes": 1440, "type": "platform" }
     *   ]
     * }
     *
     * Response: 200 OK with updated reminders
     */
    public function updateReminders(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_reminders', 20, 60);

        $reminders = $this->input('reminders');
        if (!is_array($reminders)) {
            $this->respondWithError('VALIDATION_ERROR', 'reminders must be an array', 'reminders', 400);
            return;
        }

        $success = EventService::updateReminders($id, $userId, $reminders);

        if (!$success) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to update reminders', null, 400);
            return;
        }

        $updated = EventService::getUserReminders($id, $userId);
        $this->respondWithData($updated);
    }

    // =========================================================================
    // E6: EVENT ATTENDANCE TRACKING
    // =========================================================================

    /**
     * POST /api/v2/events/{id}/attendance
     *
     * Mark a user as attended (organizer/admin only).
     *
     * Request Body (JSON):
     * {
     *   "user_id": int (required),
     *   "hours": float (optional override),
     *   "notes": "string (optional)"
     * }
     *
     * Response: 200 OK with attendance confirmation
     */
    public function markAttendance(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_attendance', 30, 60);

        $attendeeId = $this->inputInt('user_id');
        if (!$attendeeId) {
            $this->respondWithError('VALIDATION_ERROR', 'user_id is required', 'user_id', 400);
            return;
        }

        $hours = $this->input('hours') !== null ? (float)$this->input('hours') : null;
        $notes = $this->input('notes');

        $success = EventService::markAttended($id, $attendeeId, $userId, $hours, $notes);

        if (!$success) {
            $errors = EventService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData([
            'marked' => true,
            'event_id' => $id,
            'user_id' => $attendeeId,
        ]);
    }

    /**
     * POST /api/v2/events/{id}/attendance/bulk
     *
     * Bulk mark attendance (organizer/admin only).
     *
     * Request Body (JSON):
     * {
     *   "user_ids": [1, 2, 3]
     * }
     *
     * Response: 200 OK with results
     */
    public function bulkMarkAttendance(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_attendance', 10, 60);

        $userIds = $this->input('user_ids');
        if (!is_array($userIds) || empty($userIds)) {
            $this->respondWithError('VALIDATION_ERROR', 'user_ids must be a non-empty array', 'user_ids', 400);
            return;
        }

        $result = EventService::bulkMarkAttended($id, $userIds, $userId);

        $this->respondWithData($result);
    }

    /**
     * GET /api/v2/events/{id}/attendance
     *
     * Get attendance records for an event.
     *
     * Response: 200 OK with attendance records
     */
    public function getAttendance(int $id): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('events_attendance', 60, 60);

        $event = EventService::getById($id);
        if (!$event) {
            $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
            return;
        }

        $records = EventService::getAttendanceRecords($id);

        $this->respondWithData($records);
    }

    // =========================================================================
    // E7: EVENT SERIES
    // =========================================================================

    /**
     * GET /api/v2/events/series
     *
     * List all event series.
     *
     * Response: 200 OK with series array
     */
    public function listSeries(): void
    {
        $this->getOptionalUserId();
        $this->rateLimit('events_series', 60, 60);

        $result = EventService::getAllSeries([
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ]);

        $this->respondWithData($result['items'], [
            'has_more' => $result['has_more'],
        ]);
    }

    /**
     * POST /api/v2/events/series
     *
     * Create a new event series.
     *
     * Request Body (JSON):
     * {
     *   "title": "string (required)",
     *   "description": "string (optional)"
     * }
     *
     * Response: 201 Created with series data
     */
    public function createSeries(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_series', 10, 60);

        $title = $this->input('title');
        $description = $this->input('description');

        if (empty($title)) {
            $this->respondWithError('VALIDATION_ERROR', 'Series title is required', 'title', 400);
            return;
        }

        $seriesId = EventService::createSeries($userId, $title, $description);

        if (!$seriesId) {
            $errors = EventService::getErrors();
            $this->respondWithErrors($errors, 422);
        }

        $series = EventService::getSeriesInfo($seriesId);
        $this->respondWithData($series, null, 201);
    }

    /**
     * GET /api/v2/events/series/{seriesId}
     *
     * Get all events in a series.
     *
     * Response: 200 OK with series info and events array
     */
    public function showSeries(int $seriesId): void
    {
        $this->getOptionalUserId();
        $this->rateLimit('events_series', 60, 60);

        $series = EventService::getSeriesInfo($seriesId);
        if (!$series) {
            $this->respondWithError('NOT_FOUND', 'Series not found', null, 404);
            return;
        }

        $events = EventService::getSeriesEvents($seriesId);

        $this->respondWithData([
            'series' => $series,
            'events' => $events,
        ]);
    }

    /**
     * POST /api/v2/events/{id}/series
     *
     * Link an event to a series.
     *
     * Request Body (JSON):
     * {
     *   "series_id": int (required)
     * }
     *
     * Response: 200 OK
     */
    public function linkToSeries(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_series', 20, 60);

        $seriesId = $this->inputInt('series_id');
        if (!$seriesId) {
            $this->respondWithError('VALIDATION_ERROR', 'series_id is required', 'series_id', 400);
            return;
        }

        $success = EventService::linkToSeries($id, $seriesId, $userId);

        if (!$success) {
            $errors = EventService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData(['linked' => true, 'event_id' => $id, 'series_id' => $seriesId]);
    }

    // =========================================================================
    // E1: RECURRING EVENTS
    // =========================================================================

    /**
     * POST /api/v2/events/recurring
     *
     * Create a recurring event with recurrence rules.
     *
     * Request Body (JSON): Same as create event, plus:
     * {
     *   "recurrence_frequency": "daily|weekly|monthly|yearly|custom",
     *   "recurrence_interval": 1,
     *   "recurrence_days": "1,3,5",
     *   "recurrence_ends_type": "never|after_count|on_date",
     *   "recurrence_ends_after_count": 10,
     *   "recurrence_ends_on_date": "2026-06-01"
     * }
     *
     * Response: 201 Created with template and occurrence count
     */
    public function createRecurring(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_create', 5, 60);

        $data = $this->getAllInput();

        $result = EventService::createRecurring($userId, $data);

        if (!$result) {
            $errors = EventService::getErrors();
            $status = 422;

            foreach ($errors as $error) {
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
            return;
        }

        $template = EventService::getById($result['template_id'], $userId);

        $this->respondWithData([
            'template' => $template,
            'occurrences_created' => $result['occurrences'],
        ], null, 201);
    }

    /**
     * PUT /api/v2/events/{id}/recurring
     *
     * Update a recurring event (single occurrence or all).
     *
     * Request Body (JSON): Same as update, plus:
     * {
     *   "scope": "single|all"
     * }
     *
     * Response: 200 OK with updated event data
     */
    public function updateRecurring(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('events_update', 20, 60);

        $data = $this->getAllInput();
        $scope = $data['scope'] ?? 'single';

        if (!in_array($scope, ['single', 'all'])) {
            $this->respondWithError('VALIDATION_ERROR', 'scope must be "single" or "all"', 'scope', 400);
            return;
        }

        $success = EventService::updateRecurring($id, $userId, $data, $scope);

        if (!$success) {
            $errors = EventService::getErrors();
            $status = 422;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $status = 404;
                    break;
                }
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
            return;
        }

        $event = EventService::getById($id, $userId);
        $this->respondWithData($event);
    }
}
