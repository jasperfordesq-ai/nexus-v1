<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * DeliverableController -- Project deliverable management (CRUD, comments).
 *
 * All methods require authentication.
 */
class DeliverableController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /** GET /api/v2/deliverables */
    public function index(): JsonResponse
    {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $offset = ($page - 1) * $perPage;

        $items = DB::select(
            'SELECT * FROM deliverables WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$tenantId, $perPage, $offset]
        );
        $total = DB::selectOne(
            'SELECT COUNT(*) as cnt FROM deliverables WHERE tenant_id = ?',
            [$tenantId]
        )->cnt;

        return $this->respondWithPaginatedCollection($items, (int) $total, $page, $perPage);
    }

    /** GET /api/v2/deliverables/{id} */
    public function show(int $id): JsonResponse
    {
        $this->requireAuth();
        $tenantId = $this->getTenantId();

        $deliverable = DB::selectOne(
            'SELECT * FROM deliverables WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );

        if ($deliverable === null) {
            return $this->respondWithError('NOT_FOUND', 'Deliverable not found', null, 404);
        }

        return $this->respondWithData($deliverable);
    }

    /** POST /api/v2/deliverables */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $title = $this->requireInput('title');
        $description = $this->input('description', '');
        $dueDate = $this->input('due_date');
        $status = $this->input('status', 'pending');

        DB::insert(
            'INSERT INTO deliverables (tenant_id, user_id, title, description, due_date, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$tenantId, $userId, $title, $description, $dueDate, $status]
        );

        $id = (int) DB::getPdo()->lastInsertId();

        return $this->respondWithData(['id' => $id, 'title' => $title, 'status' => $status], null, 201);
    }

    /** PUT /api/v2/deliverables/{id} */
    public function update(int $id): JsonResponse
    {
        $this->requireAuth();
        $tenantId = $this->getTenantId();
        $data = $this->getAllInput();

        $allowed = ['title', 'description', 'due_date', 'status'];
        $sets = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $sets[] = "{$key} = ?";
                $params[] = $value;
            }
        }

        if (empty($sets)) {
            return $this->respondWithError('VALIDATION_ERROR', 'No valid fields to update');
        }

        $params[] = $id;
        $params[] = $tenantId;

        $affected = DB::update(
            'UPDATE deliverables SET ' . implode(', ', $sets) . ' WHERE id = ? AND tenant_id = ?',
            $params
        );

        if ($affected === 0) {
            return $this->respondWithError('NOT_FOUND', 'Deliverable not found', null, 404);
        }

        return $this->respondWithData(['id' => $id, 'updated' => true]);
    }

    /** POST /api/v2/deliverables/{id}/comments */
    public function addComment(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();

        $deliverable = DB::selectOne(
            'SELECT id FROM deliverables WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );

        if ($deliverable === null) {
            return $this->respondWithError('NOT_FOUND', 'Deliverable not found', null, 404);
        }

        $content = $this->requireInput('content');

        DB::insert(
            'INSERT INTO deliverable_comments (tenant_id, deliverable_id, user_id, content, created_at) VALUES (?, ?, ?, ?, NOW())',
            [$tenantId, $id, $userId, $content]
        );

        $commentId = (int) DB::getPdo()->lastInsertId();

        // Notify deliverable owner of new comment
        try {
            $owner = DB::selectOne(
                'SELECT user_id, title FROM deliverables WHERE id = ? AND tenant_id = ?',
                [$id, $tenantId]
            );
            if ($owner && (int) $owner->user_id !== $userId) {
                $commenter = \App\Models\User::find($userId);
                $commenterName = $commenter->first_name ?? $commenter->name ?? 'Someone';
                \App\Models\Notification::createNotification(
                    (int) $owner->user_id,
                    "{$commenterName} commented on your deliverable \"{$owner->title}\"",
                    "/deliverables/{$id}",
                    'comment'
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('Deliverable comment notification failed', ['deliverable_id' => $id, 'error' => $e->getMessage()]);
        }

        return $this->respondWithData(['id' => $commentId, 'deliverable_id' => $id], null, 201);
    }
}
