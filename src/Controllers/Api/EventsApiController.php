<?php

namespace Nexus\Controllers\Api;

use Nexus\Services\EventService;
use Nexus\Core\ImageUploader;

/**
 * EventsApiController - RESTful API for events
 *
 * Provides event management endpoints with standardized response format.
 *
 * Endpoints:
 * - GET    /api/v2/events              - List events (cursor paginated)
 * - GET    /api/v2/events/{id}         - Get single event
 * - POST   /api/v2/events              - Create event
 * - PUT    /api/v2/events/{id}         - Update event
 * - DELETE /api/v2/events/{id}         - Delete event
 * - POST   /api/v2/events/{id}/rsvp    - Set RSVP status
 * - DELETE /api/v2/events/{id}/rsvp    - Remove RSVP
 * - GET    /api/v2/events/{id}/attendees - List attendees
 * - POST   /api/v2/events/{id}/image   - Upload event image
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
        }

        $success = EventService::rsvp($id, $userId, $status);

        if (!$success) {
            $errors = EventService::getErrors();
            $httpStatus = 422;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $httpStatus = 404;
                    break;
                }
            }

            $this->respondWithErrors($errors, $httpStatus);
        }

        // Get updated event with RSVP counts
        $event = EventService::getById($id, $userId);

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
        }
    }
}
