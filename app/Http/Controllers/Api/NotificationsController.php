<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

/**
 * NotificationsController - User notification management.
 *
 * Endpoints (v2):
 *   GET    /api/v2/notifications            index()
 *   GET    /api/v2/notifications/counts     counts()
 *   POST   /api/v2/notifications/read-all   markAllRead()
 *   GET    /api/v2/notifications/{id}       show()
 *   DELETE /api/v2/notifications/{id}       destroy()
 */
class NotificationsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * List notifications for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $userId = $this->requireAuth();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }
        if ($this->queryBool('unread_only')) {
            $filters['unread_only'] = true;
        }

        $result = $this->notificationService->getAll($userId, $filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'] ?? null,
            $filters['limit'],
            $result['has_more'] ?? false
        );
    }

    /**
     * Get notification counts (total unread, by type).
     */
    public function counts(): JsonResponse
    {
        $userId = $this->requireAuth();

        $counts = $this->notificationService->getCounts($userId);

        return $this->respondWithData($counts);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(): JsonResponse
    {
        $userId = $this->requireAuth();

        $this->notificationService->markAllRead($userId);

        return $this->respondWithData(['marked_all_read' => true]);
    }

    /**
     * Get a single notification by ID.
     */
    public function show(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $notification = $this->notificationService->getById($id, $userId);

        if ($notification === null) {
            return $this->respondWithError('NOT_FOUND', 'Notification not found', null, 404);
        }

        return $this->respondWithData($notification);
    }

    /**
     * Delete a notification.
     */
    public function destroy(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $existing = $this->notificationService->getById($id, $userId);

        if ($existing === null) {
            return $this->respondWithError('NOT_FOUND', 'Notification not found', null, 404);
        }

        $this->notificationService->delete($id, $userId);

        return $this->noContent();
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


    public function destroyAll(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\NotificationsApiController::class, 'destroyAll');
    }


    public function markRead($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\NotificationsApiController::class, 'markRead', [$id]);
    }


    public function poll(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\NotificationController::class, 'poll');
    }


    public function delete(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\NotificationController::class, 'delete');
    }

}
