<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\EventService;
use App\Services\EventNotificationService;
use App\Models\Poll;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;
use App\Models\EventRsvp;

/**
 * EventsController - CRUD for community events, RSVPs, waitlist, series, recurring.
 *
 * All methods use Laravel DI services — no legacy static calls.
 */
class EventsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly EventService $eventService,
        private readonly EventNotificationService $eventNotificationService,
    ) {}

    // ================================================================
    // LIST / SHOW
    // ================================================================

    /**
     * GET /api/v2/events
     */
    public function index(): JsonResponse
    {
        $userId = $this->getOptionalUserId() ?? $this->resolveSanctumUserOptionally();

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

        // Proximity / radius filter (near_lat, near_lng, radius_km)
        $nearLat = $this->query('near_lat');
        $nearLng = $this->query('near_lng');
        $radiusKm = $this->query('radius_km');
        if ($nearLat !== null && $nearLng !== null && $radiusKm !== null) {
            $lat = (float) $nearLat;
            $lng = (float) $nearLng;
            $km  = max(0.1, min(500, (float) $radiusKm));
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                $filters['near_lat']  = $lat;
                $filters['near_lng']  = $lng;
                $filters['radius_km'] = $km;
            }
        }

        $result = $this->eventService->getAll($filters);

        // Batch-load user RSVP statuses (single query instead of N+1).
        // Anonymous viewers always get null — never fall through to a per-event lookup.
        if (!empty($result['items'])) {
            if ($userId) {
                $eventIds = array_column($result['items'], 'id');
                $rsvpMap = $this->eventService->getUserRsvpsBatch($eventIds, $userId);
                foreach ($result['items'] as &$event) {
                    $event['user_rsvp'] = $rsvpMap[(int) $event['id']] ?? null;
                }
                unset($event);
            } else {
                foreach ($result['items'] as &$event) {
                    $event['user_rsvp'] = null;
                }
                unset($event);
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
        $userId = $this->getOptionalUserId() ?? $this->resolveSanctumUserOptionally();

        $event = $this->eventService->getById($id, $userId);

        if (!$event) {
            return $this->respondWithError('NOT_FOUND', __('api.event_not_found'), null, 404);
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
            return $this->respondWithError('VALIDATION_ERROR', __('api.event_lat_lon_required'), null, 400);
        }

        $lat = (float) $lat;
        $lon = (float) $lon;

        if ($lat < -90 || $lat > 90) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.event_lat_range'), 'lat', 400);
        }
        if ($lon < -180 || $lon > 180) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.event_lon_range'), 'lon', 400);
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

        $result = $this->eventService->getNearby($lat, $lon, $filters);

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

        $result = $this->eventService->create($userId, $data);

        if ($result === null) {
            $errors = $this->eventService->getErrors();
            $status = 422;
            foreach ($errors as $error) {
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $status);
        }

        // create() returns an Event model (Laravel) or an int ID (legacy)
        $eventId = $result instanceof \App\Models\Event ? $result->id : (int) $result;

        // Link polls to this event
        if (! empty($data['poll_ids']) && is_array($data['poll_ids'])) {
            Poll::query()
                ->whereIn('id', array_map('intval', $data['poll_ids']))
                ->update(['event_id' => $eventId]);
        }

        $event = $this->eventService->getById($eventId, $userId);

        // Award XP for creating an event
        try {
            \App\Services\GamificationService::awardXP($userId, \App\Services\GamificationService::XP_VALUES['create_event'], 'create_event', 'Created an event');
        } catch (\Throwable $e) {
            \Log::warning('Gamification XP award failed', ['action' => 'create_event', 'user' => $userId, 'error' => $e->getMessage()]);
        }

        // Record feed activity
        try {
            app(\App\Services\FeedActivityService::class)->recordActivity(
                TenantContext::getId(),
                $userId,
                'event',
                $eventId,
                [
                    'title'    => $data['title'] ?? null,
                    'content'  => $data['description'] ?? null,
                    'image_url' => $event['image_url'] ?? null,
                    'group_id' => $data['group_id'] ?? null,
                ]
            );
        } catch (\Throwable $e) {
            \Log::warning('Feed activity recording failed', ['type' => 'event', 'id' => $eventId, 'error' => $e->getMessage()]);
        }

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

        $success = $this->eventService->update($id, $userId, $data);

        if (!$success) {
            $errors = $this->eventService->getErrors();
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

        // Update poll associations
        if (array_key_exists('poll_ids', $data)) {
            // Unlink existing polls from this event
            Poll::query()->where('event_id', $id)->update(['event_id' => null]);
            // Link new polls
            if (! empty($data['poll_ids']) && is_array($data['poll_ids'])) {
                Poll::query()
                    ->whereIn('id', array_map('intval', $data['poll_ids']))
                    ->update(['event_id' => $id]);
            }
        }

        // Notify attendees of meaningful changes
        try {
            $this->eventNotificationService->notifyEventUpdated($id, $data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Event update notification error: " . $e->getMessage());
        }

        $event = $this->eventService->getById($id, $userId);

        return $this->respondWithData($event);
    }

    /**
     * DELETE /api/v2/events/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('events_delete', 10, 60);

        $success = $this->eventService->delete($id, $userId);

        if (!$success) {
            $errors = $this->eventService->getErrors();
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
            return $this->respondWithError('VALIDATION_ERROR', __('api.event_rsvp_status_required'), 'status', 400);
        }

        $success = $this->eventService->rsvp($id, $userId, $status);

        if (!$success) {
            $errors = $this->eventService->getErrors();
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
                if ($error['code'] === 'KISS_TREFFEN_MEMBERS_ONLY') {
                    $httpStatus = 403;
                    break;
                }
                if ($error['code'] === 'EVENT_FULL') {
                    $isWaitlisted = true;
                    break;
                }
            }

            // Waitlisted is a 200 with special response, not a true error
            if ($isWaitlisted) {
                $position = $this->eventService->getUserWaitlistPosition($id, $userId);
                $event = $this->eventService->getById($id, $userId);
                return $this->respondWithData([
                    'status'             => 'waitlisted',
                    'waitlist_position'  => $position,
                    'rsvp_counts'        => $event['rsvp_counts'] ?? ['going' => 0, 'interested' => 0],
                    'message'            => __('api.event_full_waitlisted_msg'),
                ]);
            }

            return $this->respondWithErrors($errors, $httpStatus);
        }

        $event = $this->eventService->getById($id, $userId);

        // Notify event organizer of RSVP
        try {
            $this->eventNotificationService->notifyRsvp($id, $userId, $status);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("RSVP notification error: " . $e->getMessage());
        }

        // Award XP when user RSVPs as 'going'
        if ($status === 'going') {
            try {
                \App\Services\GamificationService::awardXP($userId, \App\Services\GamificationService::XP_VALUES['attend_event'], 'attend_event', 'RSVPed to an event');
            } catch (\Throwable $e) {
                \Log::warning('Gamification XP award failed', ['action' => 'attend_event', 'user' => $userId, 'error' => $e->getMessage()]);
            }
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

        $success = $this->eventService->removeRsvp($id, $userId);

        if (!$success) {
            $errors = $this->eventService->getErrors();
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

        $event = $this->eventService->getById($id);
        if (!$event) {
            return $this->respondWithError('NOT_FOUND', __('api.event_not_found'), null, 404);
        }

        $filters = [
            'limit'  => $this->queryInt('per_page', 20, 1, 100),
            'status' => $this->query('status', 'going'),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->eventService->getAttendees($id, $filters);

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
        $event = $this->eventService->getById($id);
        if (!$event) {
            return $this->respondWithError('NOT_FOUND', __('api.event_not_found'), null, 404);
        }

        // Block check-in if event hasn't started yet (allow up to 30 min early)
        $startTime = isset($event['start_time']) ? strtotime($event['start_time']) : null;
        if ($startTime && $startTime > time() + 1800) {
            return $this->respondWithError('TOO_EARLY', __('api.event_too_early_checkin'), null, 422);
        }

        // Block check-in if event ended more than 24 hours ago
        $endTime = isset($event['end_time']) ? strtotime($event['end_time']) : $startTime;
        if ($endTime && $endTime < time() - 86400) {
            return $this->respondWithError('EVENT_ENDED', __('api.event_ended_checkin'), null, 422);
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
            return $this->respondWithError('FORBIDDEN', __('api.event_organizer_only_checkin'), null, 403);
        }

        // Verify the attendee has an RSVP for this event
        $currentStatus = EventRsvp::getUserStatus($id, $attendeeId);
        if (!$currentStatus) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.event_not_rsvped'), null, 422);
        }
        if ($currentStatus === 'attended') {
            return $this->respondWithError('VALIDATION_ERROR', __('api.event_already_checked_in'), null, 422);
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

        // Cap duration to prevent unreasonable credit awards (max 24 hours)
        if ($duration > 24) {
            $duration = 24;
        }

        try {
            DB::transaction(function () use ($userId, $attendeeId, $id, $event, $duration, $tenantId) {
                // Lock organizer row and check balance before decrementing
                $organizer = DB::table('users')
                    ->where('id', $userId)
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->first();

                if (!$organizer || (float) $organizer->balance < $duration) {
                    throw new \RuntimeException(__('api.organizer_insufficient_balance'));
                }

                // Lock attendee row for consistency
                DB::table('users')
                    ->where('id', $attendeeId)
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->first();

                // Create time credit transaction (organizer -> attendee)
                \App\Models\Transaction::create([
                    'sender_id'        => $userId,
                    'receiver_id'      => $attendeeId,
                    'amount'           => $duration,
                    'description'      => "Event Attendance: " . ($event['title'] ?? 'Event #' . $id),
                    'transaction_type' => 'event_checkin',
                    'status'           => 'completed',
                ]);

                // Update balances atomically
                DB::table('users')->where('id', $userId)->where('tenant_id', $tenantId)
                    ->decrement('balance', $duration);
                DB::table('users')->where('id', $attendeeId)->where('tenant_id', $tenantId)
                    ->increment('balance', $duration);

                // Update RSVP status to 'attended' inside the same transaction
                EventRsvp::rsvp($id, $attendeeId, 'attended');
            });

            return $this->respondWithData([
                'checked_in'     => true,
                'attendee_id'    => $attendeeId,
                'event_id'       => $id,
                'hours_credited' => $duration,
            ]);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('INSUFFICIENT_BALANCE', $e->getMessage(), null, 422);
        } catch (\Exception $e) {
            \Log::error('Event check-in failed', ['event' => $id, 'error' => $e->getMessage()]);
            return $this->respondWithError('CHECKIN_ERROR', __('api.event_checkin_failed'), null, 500);
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

        $success = $this->eventService->cancelEvent($id, $userId, $reason);

        if (!$success) {
            $errors = $this->eventService->getErrors();
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

        // Notify all attendees and waitlisted users of the cancellation
        try {
            $tenantId = TenantContext::getId();
            $this->eventNotificationService->notifyCancellation($tenantId, $id, $reason);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Event cancellation notification error: " . $e->getMessage());
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

        $event = $this->eventService->getById($id);
        if (!$event) {
            return $this->respondWithError('NOT_FOUND', __('api.event_not_found'), null, 404);
        }

        $result = $this->eventService->getWaitlist($id, [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ]);

        $userPosition = $this->eventService->getUserWaitlistPosition($id, $userId);

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

        $event = $this->eventService->getById($id);
        if (!$event) {
            return $this->respondWithError('NOT_FOUND', __('api.event_not_found'), null, 404);
        }

        $success = $this->eventService->addToWaitlist($id, $userId);

        if (!$success) {
            return $this->respondWithError('WAITLIST_FAILED', __('api.event_waitlist_failed'), null, 400);
        }

        $position = $this->eventService->getUserWaitlistPosition($id, $userId);

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

        $this->eventService->removeFromWaitlist($id, $userId);

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

        $reminders = $this->eventService->getUserReminders($id, $userId);

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
            return $this->respondWithError('VALIDATION_ERROR', __('api.event_reminders_must_be_array'), 'reminders', 400);
        }

        $success = $this->eventService->updateReminders($id, $userId, $reminders);

        if (!$success) {
            return $this->respondWithError('UPDATE_FAILED', __('api.event_reminders_update_failed'), null, 400);
        }

        $updated = $this->eventService->getUserReminders($id, $userId);

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

        $event = $this->eventService->getById($id);
        if (!$event) {
            return $this->respondWithError('NOT_FOUND', __('api.event_not_found'), null, 404);
        }

        $records = $this->eventService->getAttendanceRecords($id);

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
            return $this->respondWithError('VALIDATION_ERROR', __('api.event_user_id_required'), 'user_id', 400);
        }

        $hours = $this->input('hours') !== null ? (float) $this->input('hours') : null;
        $notes = $this->input('notes');

        $success = $this->eventService->markAttended($id, $attendeeId, $userId, $hours, $notes);

        if (!$success) {
            $errors = $this->eventService->getErrors();
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
            return $this->respondWithError('VALIDATION_ERROR', __('api.event_user_ids_array_required'), 'user_ids', 400);
        }

        $result = $this->eventService->bulkMarkAttended($id, $userIds, $userId);

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
        $result = $this->eventService->getAllSeries([
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
            return $this->respondWithError('VALIDATION_ERROR', __('api.event_series_title_required'), 'title', 400);
        }

        $seriesId = $this->eventService->createSeries($userId, $title, $description);

        if (!$seriesId) {
            $errors = $this->eventService->getErrors();
            return $this->respondWithErrors($errors, 422);
        }

        $series = $this->eventService->getSeriesInfo($seriesId);

        return $this->respondWithData($series, null, 201);
    }

    /**
     * GET /api/v2/events/series/{seriesId}
     */
    public function showSeries($seriesId): JsonResponse
    {
        $seriesId = (int) $seriesId;

        $series = $this->eventService->getSeriesInfo($seriesId);
        if (!$series) {
            return $this->respondWithError('NOT_FOUND', __('api.event_series_not_found'), null, 404);
        }

        $events = $this->eventService->getSeriesEvents($seriesId);

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
            return $this->respondWithError('VALIDATION_ERROR', __('api.event_series_id_required'), 'series_id', 400);
        }

        $success = $this->eventService->linkToSeries($id, $seriesId, $userId);

        if (!$success) {
            $errors = $this->eventService->getErrors();
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

        $result = $this->eventService->createRecurring($userId, $data);

        if (!$result) {
            $errors = $this->eventService->getErrors();
            $status = 422;
            foreach ($errors as $error) {
                if ($error['code'] === 'FORBIDDEN') {
                    $status = 403;
                    break;
                }
            }
            return $this->respondWithErrors($errors, $status);
        }

        $template = $this->eventService->getById($result['template_id'], $userId);

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
            return $this->respondWithError('VALIDATION_ERROR', __('api.event_scope_must_be_single_or_all'), 'scope', 400);
        }

        $success = $this->eventService->updateRecurring($id, $userId, $data, $scope);

        if (!$success) {
            $errors = $this->eventService->getErrors();
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

        $event = $this->eventService->getById($id, $userId);

        return $this->respondWithData($event);
    }

    // ================================================================
    // IMAGE UPLOAD
    // ================================================================

    /**
     * POST /api/v2/events/{id}/image
     *
     * Upload an image for an event. Uses request()->file() (Laravel native).
     * Field name: 'image'
     */
    public function uploadImage($id): JsonResponse
    {
        $id = (int) $id;
        $userId = $this->requireAuth();
        $this->rateLimit('events_image_upload', 10, 60);

        $file = request()->file('image');
        if (!$file || !$file->isValid()) {
            return $this->respondWithError('VALIDATION_ERROR', __('api.event_no_image_uploaded'), 'image', 400);
        }

        try {
            // Build a $_FILES-compatible array for ImageUploader::upload()
            $fileArray = [
                'name'     => $file->getClientOriginalName(),
                'type'     => $file->getMimeType(),
                'tmp_name' => $file->getRealPath(),
                'error'    => UPLOAD_ERR_OK,
                'size'     => $file->getSize(),
            ];

            $imageUrl = \App\Core\ImageUploader::upload($fileArray);

            $success = $this->eventService->updateImage($id, $userId, $imageUrl);

            if (!$success) {
                $errors = $this->eventService->getErrors();
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

            return $this->respondWithData(['image_url' => $imageUrl]);
        } catch (\Exception $e) {
            \Log::error('Event image upload failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('UPLOAD_FAILED', __('api.event_image_upload_failed'), 'image', 500);
        }
    }
}
