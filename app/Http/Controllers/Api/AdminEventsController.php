<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Services\EventService;

/**
 * AdminEventsController -- Admin event management (list, view, approve, delete, cancel).
 *
 * All methods require admin authentication.
 */
class AdminEventsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly EventService $eventService,
    ) {}

    /**
     * GET /api/v2/admin/events
     *
     * Query params: page, per_page/limit, status, search
     */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $page = $this->queryInt('page', 1, 1);
        $limit = $this->queryInt('per_page', $this->queryInt('limit', 20, 1, 100), 1, 100);
        $offset = ($page - 1) * $limit;

        $status = $this->query('status');
        $search = $this->query('search');

        $conditions = ['e.tenant_id = ?'];
        $params = [$tenantId];

        if ($status) {
            $conditions[] = 'e.status = ?';
            $params[] = $status;
        }

        if ($search) {
            $conditions[] = '(e.title LIKE ? OR e.description LIKE ?)';
            $searchPattern = '%' . $search . '%';
            $params[] = $searchPattern;
            $params[] = $searchPattern;
        }

        $where = implode(' AND ', $conditions);

        $total = (int) DB::selectOne(
            "SELECT COUNT(*) as cnt FROM events e WHERE {$where}",
            $params
        )->cnt;

        $items = DB::select(
            "SELECT e.id, e.title, e.description, e.start_date, e.end_date, e.location, e.status,
                    e.created_by, e.created_at, e.max_attendees, e.category_id,
                    u.name as creator_name
             FROM events e
             LEFT JOIN users u ON e.created_by = u.id
             WHERE {$where} ORDER BY e.created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );

        return $this->respondWithPaginatedCollection($items, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/events/{id}
     */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $event = DB::selectOne(
            "SELECT e.id, e.title, e.description, e.start_date, e.end_date, e.location, e.status,
                    e.created_by, e.created_at, e.max_attendees, e.category_id,
                    u.name as creator_name
             FROM events e
             LEFT JOIN users u ON e.created_by = u.id
             WHERE e.id = ? AND e.tenant_id = ?",
            [$id, $tenantId]
        );

        if ($event === null) {
            return $this->respondWithError('NOT_FOUND', __('api.event_not_found'), null, 404);
        }

        return $this->respondWithData($event);
    }

    /**
     * POST /api/v2/admin/events/{id}/approve
     */
    public function approve(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $event = DB::selectOne(
            'SELECT id, title, created_by FROM events WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );

        if (!$event) {
            return $this->respondWithError('NOT_FOUND', __('api.event_not_found'), null, 404);
        }

        DB::update(
            'UPDATE events SET status = ? WHERE id = ? AND tenant_id = ?',
            ['active', $id, $tenantId]
        );

        // Notify the event organizer (unless the admin is the organizer)
        try {
            if ((int) $event->created_by !== $adminId) {
                Notification::createNotification(
                    (int) $event->created_by,
                    'Your event has been approved!',
                    "/events/{$id}",
                    'info',
                    false,
                    $tenantId
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to send event approval notification', [
                'event_id' => $id,
                'user_id' => $event->created_by,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->respondWithData(['id' => $id, 'status' => 'active']);
    }

    /**
     * DELETE /api/v2/admin/events/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $event = $this->eventService->getById($id);
        if (!$event) {
            return $this->respondWithError('NOT_FOUND', __('api.event_not_found'), null, 404);
        }

        $deleted = $this->eventService->delete($id, $adminId);

        if ($deleted) {
            return $this->respondWithData(['id' => $id, 'deleted' => true]);
        }

        return $this->respondWithError('DELETE_FAILED', __('api.delete_failed', ['resource' => 'event']), null, 400);
    }

    /**
     * POST /api/v2/admin/events/{id}/cancel
     */
    public function cancel(int $id): JsonResponse
    {
        $adminId = $this->requireAdmin();

        $reason = $this->input('reason', 'Cancelled by admin');
        $cancelled = $this->eventService->cancelEvent($id, $adminId, $reason);

        if ($cancelled) {
            ActivityLog::log($adminId, 'admin_cancel_event', "Cancelled event #{$id}: {$reason}");
            return $this->respondWithData(['cancelled' => true, 'id' => $id]);
        }

        return $this->respondWithError('CANCEL_FAILED', __('api.update_failed', ['resource' => 'event cancellation']), null, 400);
    }
}
