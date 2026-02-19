<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\NotificationService;

/**
 * NotificationsApiController - RESTful API for notifications
 *
 * Provides notification management endpoints with standardized response format.
 *
 * Endpoints:
 * - GET    /api/v2/notifications                - List notifications (cursor paginated)
 * - GET    /api/v2/notifications/counts         - Get unread counts by category
 * - GET    /api/v2/notifications/{id}           - Get single notification
 * - POST   /api/v2/notifications/{id}/read      - Mark notification as read
 * - POST   /api/v2/notifications/read-all       - Mark all as read
 * - DELETE /api/v2/notifications/{id}           - Delete notification
 * - DELETE /api/v2/notifications                - Delete all notifications
 *
 * Response Format:
 * Success: { "data": {...}, "meta": {...} }
 * Error:   { "errors": [{ "code": "...", "message": "...", "field": "..." }] }
 */
class NotificationsApiController extends BaseApiController
{
    /**
     * GET /api/v2/notifications
     *
     * List notifications with optional filtering and cursor-based pagination.
     *
     * Query Parameters:
     * - type: string (filter by category: messages, connections, reviews, transactions, social, events, groups, listings, system)
     * - unread_only: bool (default false)
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     *
     * Response: 200 OK with notifications array and pagination meta
     */
    public function index(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('notifications_list', 120, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('type')) {
            $filters['type'] = $this->query('type');
        }

        if ($this->query('unread_only') === 'true' || $this->query('unread_only') === '1') {
            $filters['unread_only'] = true;
        }

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = NotificationService::getNotifications($userId, $filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * GET /api/v2/notifications/counts
     *
     * Get unread notification counts by category.
     *
     * Response: 200 OK with counts object
     */
    public function counts(): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('notifications_counts', 120, 60);

        $counts = NotificationService::getUnreadCounts($userId);

        $this->respondWithData($counts);
    }

    /**
     * GET /api/v2/notifications/{id}
     *
     * Get a single notification by ID.
     *
     * Response: 200 OK with notification data
     */
    public function show(int $id): void
    {
        $userId = $this->getUserId();
        $this->rateLimit('notifications_show', 120, 60);

        $notification = NotificationService::getNotification($id, $userId);

        if (!$notification) {
            $this->respondWithError('NOT_FOUND', 'Notification not found', null, 404);
        }

        $this->respondWithData($notification);
    }

    /**
     * POST /api/v2/notifications/{id}/read
     *
     * Mark a notification as read.
     *
     * Response: 200 OK with updated notification
     */
    public function markRead(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('notifications_read', 60, 60);

        $success = NotificationService::markRead($id, $userId);

        if (!$success) {
            $errors = NotificationService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $status = 404;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        // Return updated notification
        $notification = NotificationService::getNotification($id, $userId);
        $this->respondWithData($notification);
    }

    /**
     * POST /api/v2/notifications/read-all
     *
     * Mark all notifications as read.
     *
     * Request Body (JSON, optional):
     * {
     *   "category": "string" (optional - only mark this category as read)
     * }
     *
     * Response: 200 OK with count of marked notifications
     */
    public function markAllRead(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('notifications_read_all', 10, 60);

        $category = $this->input('category');
        $count = NotificationService::markAllRead($userId, $category);

        $this->respondWithData([
            'marked_read' => $count,
        ]);
    }

    /**
     * DELETE /api/v2/notifications/{id}
     *
     * Delete (soft delete) a notification.
     *
     * Response: 204 No Content on success
     */
    public function destroy(int $id): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('notifications_delete', 30, 60);

        $success = NotificationService::delete($id, $userId);

        if (!$success) {
            $errors = NotificationService::getErrors();
            $status = 400;

            foreach ($errors as $error) {
                if ($error['code'] === 'NOT_FOUND') {
                    $status = 404;
                    break;
                }
            }

            $this->respondWithErrors($errors, $status);
        }

        $this->noContent();
    }

    /**
     * DELETE /api/v2/notifications
     *
     * Delete all notifications for the user.
     *
     * Query Parameters:
     * - category: string (optional - only delete this category)
     *
     * Response: 200 OK with count of deleted notifications
     */
    public function destroyAll(): void
    {
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('notifications_delete_all', 5, 60);

        $category = $this->query('category');
        $count = NotificationService::deleteAll($userId, $category);

        $this->respondWithData([
            'deleted' => $count,
        ]);
    }
}
