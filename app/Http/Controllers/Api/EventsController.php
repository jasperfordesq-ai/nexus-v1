<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Services\EventService;
use Nexus\Services\EventNotificationService;
use Nexus\Core\TenantContext;
use Nexus\Models\EventRsvp;

/**
 * EventsController - CRUD for community events, RSVPs, waitlist, series, recurring.
 *
 * Converted from legacy delegation to direct static service calls.
 * Only uploadImage() remains delegated (uses $_FILES).
 */
class EventsController extends BaseApiController
{
    protected bool $isV2Api = true;

    // ================================================================
    // LIST / SHOW
    // ================================================================

    /**
     * GET /api/v2/events
     */
    public function index(): JsonResponse
    {
        $userId = $this->getOptionalUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
            'when'  => $this->query('when', 'upcoming'),
        ];

        if ($this->query('category_id')) {
            $filters['category_id'] = $this->queryInt('category_id');
        } elseif ($this->query('category')) {
            $filters['category'] = $this->query('category');
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

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/events/{id}
     */
    public function show(int $id): JsonResponse
    {
        $userId = $this->getOptionalUserId();

        $event = EventService::getById($id, $userId);

        if (!$event) {
            return $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
        }

        return $this->respondWithData($event);
    }

    /**
     * GET /api/v2/events/nearby
     */
    public function nearby(): JsonResponse
    {
        $lat = $this->query('lat');
        $lon = $this->query('lon');

        if ($lat === null || $lon === null) {
            return $this->respondWithError('VALIDATION_ERROR', 'Latitude and longitude are required', null, 400);
        }

        $lat = (float) $lat;
        $lon = (float) $lon;

        if ($lat < -90 || $lat > 90) {
            return $this->respondWithError('VALIDATION_ERROR', 'Latitude must be between -90 and 90', 'lat', 400);
        }
        if ($lon < -180 || $lon > 180) {
            return $this->respondWithError('VALIDATION_ERROR', 'Longitude must be between -180 and 180', 'lon', 400);
        }

        $filters = [
            'radius_km' => (float) $this->query('radius_km', '25'),
            'limit'     => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('category_id')) {
            $filters['category_id'] = $this->queryInt('category_id');
        } elseif ($this->query('category')) {
            $filters['category'] = $this->query('category');
        }

        $result = EventService::getNearby($lat, $lon, $filters);

        return $this->respondWithData($result['items'], [
            'search' => [
                'type'      => 'nearby',
                'lat'       => $lat,
                'lon'       => $lon,
                'radius_km' => $filters['radius_km'],
            ],
            'per_page' => $filters['limit'],
            'has_more' => $result['has_more'],
        ]);
    }

    // ================================================================
    // CREATE / UPDATE / DELETE
    // ================================================================

    /**
     * POST /api/v2/events
     */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
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
            return $this->respondWithErrors($errors, $status);
        }

        $event = EventService::getById($eventId, $userId);

        return $this->respondWithData($event, null, 201);
    }

    /**
     * PUT /api/v2/events/{id}
     */
    public function update(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
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
            return $this->respondWithErrors($errors, $status);
        }

        // Notify attendees of meaningful changes
        try {
            EventNotificationService::notifyEventUpdated($id, $data);
        } catch (\Throwable $e) {
            error_log("Event update notification error: " . $e->getMessage());
        }

        $event = EventService::getById($id, $userId);

        return $this->respondWithData($event);
    }

    /**
     * DELETE /api/v2/events/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
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
            return $this->respondWithErrors($errors, $status);
        }

        return $this->noContent();
    }

    // ================================================================
    // RSVP
    // ================================================================

    /**
     * POST /api/v2/events/{id}/rsvp
     */
    public function rsvp($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('events_rsvp', 30, 60);

        $status = $this->input('status');

        if (empty($status)) {
            return $this->respondWithError('VALIDATION_ERROR', 'RSVP status is required', 'status', 400);
        }

        $success = EventService::rsvp($id, $userId, $status);

        if (!$success) {
            $errors = EventService::getErrors();
            $httpStatus = 422;

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
                return $this->respondWithData([
                    'status'             => 'waitlisted',
                    'waitlist_position'  => $position,
                    'rsvp_counts'        => $event['rsvp_counts'] ?? ['going' => 0, 'interested' => 0],
                    'message'            => 'Event is full. You have been added to the waitlist.',
                ]);
            }

            return $this->respondWithErrors($errors, $httpStatus);
        }

        $event = EventService::getById($id, $userId);

        // Notify event organizer of RSVP
        try {
            EventNotificationService::notifyRsvp($id, $userId, $status);
        } catch (\Throwable $e) {
            error_log("RSVP notification error: " . $e->getMessage());
        }

        return $this->respondWithData([
            'status'      => $status,
            'rsvp_counts' => $event['rsvp_counts'] ?? ['going' => 0, 'interested' => 0],
        ]);
    }

    /**
     * DELETE /api/v2/events/{id}/rsvp
     */
    public function removeRsvp($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('events_rsvp', 30, 60);

        $success = EventService::removeRsvp($id, $userId);

        if (!$success) {
            $errors = EventService::getErrors();
            return $this->respondWithErrors($errors, 400);
        }

        return $this->noContent();
    }

    // ================================================================
    // ATTENDEES / CHECK-IN
    // ================================================================

    /**
     * GET /api/v2/events/{id}/attendees
     */
    public function attendees($id): JsonResponse
    {
        $id = (int) $id;

        $event = EventService::getById($id);
        if (!$event) {
            return $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
        }

        $filters = [
            'limit'  => $this->queryInt('per_page', 20, 1, 100),
            'status' => $this->query('status', 'going'),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = EventService::getAttendees($id, $filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * POST /api/v2/events/{id}/attendees/{attendeeId}/check-in
     */
    public function checkIn($id, $attendeeId): JsonResponse
    {
        $id = (int) $id;
        $attendeeId = (int) $attendeeId;
        $userId = $this->requireAuth();
        $this->rateLimit('events_checkin', 30, 60);

        $tenantId = TenantContext::getId();

        // Verify event exists
        $event = EventService::getById($id);
        if (!$event) {
            return $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
        }

        // Only the organizer or an admin can check in attendees
        $isOrganizer = (int) $event['user_id'] === $userId;
        $isAdmin = false;
        try {
            $adminCheck = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->select(['role', 'is_super_admin', 'is_tenant_super_admin'])
                ->first();
            if ($adminCheck && (
                !empty($adminCheck->is_super_admin) ||
                !empty($adminCheck->is_tenant_super_admin) ||
                in_array($adminCheck->role, ['admin', 'tenant_admin'])
            )) {
                $isAdmin = true;
            }
        } catch (\Exception $e) {
            // Ignore — just means no admin override
        }

        if (!$isOrganizer && !$isAdmin) {
            return $this->respondWithError('FORBIDDEN', 'Only the event organizer can check in attendees', null, 403);
        }

        // Verify the attendee has an RSVP for this event
        $currentStatus = EventRsvp::getUserStatus($id, $attendeeId);
        if (!$currentStatus) {
            return $this->respondWithError('VALIDATION_ERROR', 'This user has not RSVPed to this event', null, 422);
        }
        if ($currentStatus === 'attended') {
            return $this->respondWithError('VALIDATION_ERROR', 'This attendee has already been checked in', null, 422);
        }

        // Calculate event duration (default 1 hour)
        $duration = 1;
        if (!empty($event['start_time']) && !empty($event['end_time'])) {
            $start = strtotime($event['start_time']);
            $end = strtotime($event['end_time']);
            $diff = ($end - $start) / 3600;
            $duration = round($diff, 2);
            if ($duration < 0.5) {
                $duration = 0.5;
            }
        }

        try {
            // Create time credit transaction (organizer -> attendee)
            \Nexus\Models\Transaction::create(
                $userId,
                $attendeeId,
                $duration,
                "Event Attendance: " . ($event['title'] ?? 'Event #' . $id)
            );

            // Update RSVP status to 'attended'
            EventRsvp::rsvp($id, $attendeeId, 'attended');

            return $this->respondWithData([
                'checked_in'     => true,
                'attendee_id'    => $attendeeId,
                'event_id'       => $id,
                'hours_credited' => $duration,
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError('CHECKIN_ERROR', 'Failed to check in attendee: ' . $e->getMessage(), null, 500);
        }
    }

    // ================================================================
    // CANCEL
    // ================================================================

    /**
     * POST /api/v2/events/{id}/cancel
     */
    public function cancel($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
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
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData([
            'cancelled' => true,
            'event_id'  => $id,
            'reason'    => $reason,
        ]);
    }

    // ================================================================
    // WAITLIST
    // ================================================================

    /**
     * GET /api/v2/events/{id}/waitlist
     */
    public function waitlist($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();

        $event = EventService::getById($id);
        if (!$event) {
            return $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
        }

        $result = EventService::getWaitlist($id, [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ]);

        $userPosition = EventService::getUserWaitlistPosition($id, $userId);

        return $this->respondWithData($result['items'], [
            'has_more'      => $result['has_more'],
            'user_position' => $userPosition,
        ]);
    }

    /**
     * POST /api/v2/events/{id}/waitlist
     */
    public function joinWaitlist($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('events_waitlist', 30, 60);

        $event = EventService::getById($id);
        if (!$event) {
            return $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
        }

        $success = EventService::addToWaitlist($id, $userId);

        if (!$success) {
            return $this->respondWithError('WAITLIST_FAILED', 'Failed to join waitlist', null, 400);
        }

        $position = EventService::getUserWaitlistPosition($id, $userId);

        return $this->respondWithData([
            'waitlisted' => true,
            'position'   => $position,
        ]);
    }

    /**
     * DELETE /api/v2/events/{id}/waitlist
     */
    public function leaveWaitlist($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('events_waitlist', 30, 60);

        EventService::removeFromWaitlist($id, $userId);

        return $this->noContent();
    }

    // ================================================================
    // REMINDERS
    // ================================================================

    /**
     * GET /api/v2/events/{id}/reminders
     */
    public function getReminders($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();

        $reminders = EventService::getUserReminders($id, $userId);

        return $this->respondWithData($reminders);
    }

    /**
     * PUT /api/v2/events/{id}/reminders
     */
    public function updateReminders($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('events_reminders', 20, 60);

        $reminders = $this->input('reminders');
        if (!is_array($reminders)) {
            return $this->respondWithError('VALIDATION_ERROR', 'reminders must be an array', 'reminders', 400);
        }

        $success = EventService::updateReminders($id, $userId, $reminders);

        if (!$success) {
            return $this->respondWithError('UPDATE_FAILED', 'Failed to update reminders', null, 400);
        }

        $updated = EventService::getUserReminders($id, $userId);

        return $this->respondWithData($updated);
    }

    // ================================================================
    // ATTENDANCE
    // ================================================================

    /**
     * GET /api/v2/events/{id}/attendance
     */
    public function getAttendance($id): JsonResponse
    {
        $id = (int) $id;
        $this->requireAuth();

        $event = EventService::getById($id);
        if (!$event) {
            return $this->respondWithError('NOT_FOUND', 'Event not found', null, 404);
        }

        $records = EventService::getAttendanceRecords($id);

        return $this->respondWithData($records);
    }

    /**
     * POST /api/v2/events/{id}/attendance
     */
    public function markAttendance($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('events_attendance', 30, 60);

        $attendeeId = $this->inputInt('user_id');
        if (!$attendeeId) {
            return $this->respondWithError('VALIDATION_ERROR', 'user_id is required', 'user_id', 400);
        }

        $hours = $this->input('hours') !== null ? (float) $this->input('hours') : null;
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
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData([
            'marked'   => true,
            'event_id' => $id,
            'user_id'  => $attendeeId,
        ]);
    }

    /**
     * POST /api/v2/events/{id}/attendance/bulk
     */
    public function bulkMarkAttendance($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('events_attendance', 10, 60);

        $userIds = $this->input('user_ids');
        if (!is_array($userIds) || empty($userIds)) {
            return $this->respondWithError('VALIDATION_ERROR', 'user_ids must be a non-empty array', 'user_ids', 400);
        }

        $result = EventService::bulkMarkAttended($id, $userIds, $userId);

        return $this->respondWithData($result);
    }

    // ================================================================
    // SERIES
    // ================================================================

    /**
     * GET /api/v2/events/series
     */
    public function listSeries(): JsonResponse
    {
        $result = EventService::getAllSeries([
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ]);

        return $this->respondWithData($result['items'], [
            'has_more' => $result['has_more'],
        ]);
    }

    /**
     * POST /api/v2/events/series
     */
    public function createSeries(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('events_series', 10, 60);

        $title = $this->input('title');
        $description = $this->input('description');

        if (empty($title)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Series title is required', 'title', 400);
        }

        $seriesId = EventService::createSeries($userId, $title, $description);

        if (!$seriesId) {
            $errors = EventService::getErrors();
            return $this->respondWithErrors($errors, 422);
        }

        $series = EventService::getSeriesInfo($seriesId);

        return $this->respondWithData($series, null, 201);
    }

    /**
     * GET /api/v2/events/series/{seriesId}
     */
    public function showSeries($seriesId): JsonResponse
    {
        $seriesId = (int) $seriesId;

        $series = EventService::getSeriesInfo($seriesId);
        if (!$series) {
            return $this->respondWithError('NOT_FOUND', 'Series not found', null, 404);
        }

        $events = EventService::getSeriesEvents($seriesId);

        return $this->respondWithData([
            'series' => $series,
            'events' => $events,
        ]);
    }

    /**
     * POST /api/v2/events/{id}/series
     */
    public function linkToSeries($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('events_series', 20, 60);

        $seriesId = $this->inputInt('series_id');
        if (!$seriesId) {
            return $this->respondWithError('VALIDATION_ERROR', 'series_id is required', 'series_id', 400);
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
            return $this->respondWithErrors($errors, $status);
        }

        return $this->respondWithData(['linked' => true, 'event_id' => $id, 'series_id' => $seriesId]);
    }

    // ================================================================
    // RECURRING
    // ================================================================

    /**
     * POST /api/v2/events/recurring
     */
    public function createRecurring(): JsonResponse
    {
        $userId = $this->requireAuth();
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
            return $this->respondWithErrors($errors, $status);
        }

        $template = EventService::getById($result['template_id'], $userId);

        return $this->respondWithData([
            'template'             => $template,
            'occurrences_created'  => $result['occurrences'],
        ], null, 201);
    }

    /**
     * PUT /api/v2/events/{id}/recurring
     */
    public function updateRecurring($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('events_update', 20, 60);

        $data = $this->getAllInput();
        $scope = $data['scope'] ?? 'single';

        if (!in_array($scope, ['single', 'all'])) {
            return $this->respondWithError('VALIDATION_ERROR', 'scope must be "single" or "all"', 'scope', 400);
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
            return $this->respondWithErrors($errors, $status);
        }

        $event = EventService::getById($id, $userId);

        return $this->respondWithData($event);
    }

    // ================================================================
    // DELEGATED — file upload uses $_FILES
    // ================================================================

    /**
     * POST /api/v2/events/{id}/image — uses $_FILES, kept as delegation
     */
    public function uploadImage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\EventsApiController::class, 'uploadImage', [$id]);
    }

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }
}
