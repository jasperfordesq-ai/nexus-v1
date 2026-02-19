<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Models\Event;
use Nexus\Models\EventRsvp;
use Nexus\Core\TenantContext;

/**
 * EventApiController - Legacy API for events
 *
 * Note: The v2 Events API is handled by EventsApiController.
 * This controller provides backward compatibility for the legacy /api/events endpoints.
 *
 * Response Format:
 * Success: { "data": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "..." }] }
 */
class EventApiController extends BaseApiController
{
    /**
     * GET /api/events
     *
     * List upcoming events for the current tenant.
     *
     * Response: 200 OK with array of events
     */
    public function index(): void
    {
        $userId = $this->getUserId();
        $tenantId = $this->getAuthenticatedTenantId() ?? TenantContext::getId();

        $events = Event::upcoming($tenantId);

        // Enrich with RSVP data
        foreach ($events as &$ev) {
            $ev['attendee_count'] = EventRsvp::getCount($ev['id'], 'going');
            $ev['my_status'] = EventRsvp::getUserStatus($ev['id'], $userId);
        }

        $this->respondWithData($events);
    }

    /**
     * POST /api/events/rsvp
     *
     * RSVP to an event.
     *
     * Request Body (JSON):
     * {
     *   "event_id": int (required),
     *   "status": "going" | "interested" | "not_going" (required)
     * }
     *
     * Response: 200 OK on success
     */
    public function rsvp(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('event_rsvp', 30, 60);

        $eventId = $this->inputInt('event_id');
        $status = $this->input('status');

        if (!$eventId) {
            $this->respondWithError('VALIDATION_ERROR', 'event_id is required', 'event_id', 400);
        }

        if (!$status) {
            $this->respondWithError('VALIDATION_ERROR', 'status is required', 'status', 400);
        }

        $validStatuses = ['going', 'interested', 'not_going', 'maybe'];
        if (!in_array($status, $validStatuses)) {
            $this->respondWithError(
                'VALIDATION_ERROR',
                'Invalid status. Valid values: ' . implode(', ', $validStatuses),
                'status',
                400
            );
        }

        try {
            EventRsvp::rsvp($eventId, $userId, $status);

            // Return updated counts
            $this->respondWithData([
                'rsvp_status' => $status,
                'attendee_count' => EventRsvp::getCount($eventId, 'going'),
                'interested_count' => EventRsvp::getCount($eventId, 'interested')
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('RSVP_FAILED', $e->getMessage(), null, 500);
        }
    }
}
