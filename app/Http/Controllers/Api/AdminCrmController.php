<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AdminCrmController -- CRM contact management (contacts, notes).
 *
 * All methods require admin authentication.
 */
class AdminCrmController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /** GET /api/v2/admin/crm/contacts */
    public function contacts(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $search = $this->query('q');
        $offset = ($page - 1) * $perPage;

        if ($search) {
            $items = DB::select(
                'SELECT * FROM crm_contacts WHERE tenant_id = ? AND (name LIKE ? OR email LIKE ?) ORDER BY created_at DESC LIMIT ? OFFSET ?',
                [$tenantId, "%{$search}%", "%{$search}%", $perPage, $offset]
            );
            $total = DB::selectOne(
                'SELECT COUNT(*) as cnt FROM crm_contacts WHERE tenant_id = ? AND (name LIKE ? OR email LIKE ?)',
                [$tenantId, "%{$search}%", "%{$search}%"]
            )->cnt;
        } else {
            $items = DB::select(
                'SELECT * FROM crm_contacts WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
                [$tenantId, $perPage, $offset]
            );
            $total = DB::selectOne(
                'SELECT COUNT(*) as cnt FROM crm_contacts WHERE tenant_id = ?',
                [$tenantId]
            )->cnt;
        }

        return $this->respondWithPaginatedCollection($items, (int) $total, $page, $perPage);
    }

    /** GET /api/v2/admin/crm/contacts/{id} */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $contact = DB::selectOne(
            'SELECT * FROM crm_contacts WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );

        if ($contact === null) {
            return $this->respondWithError('NOT_FOUND', 'Contact not found', null, 404);
        }

        return $this->respondWithData($contact);
    }

    /** PUT /api/v2/admin/crm/contacts/{id} */
    public function update(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $data = $this->getAllInput();

        $allowed = ['name', 'email', 'phone', 'organization', 'tags', 'status'];
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
            'UPDATE crm_contacts SET ' . implode(', ', $sets) . ' WHERE id = ? AND tenant_id = ?',
            $params
        );

        if ($affected === 0) {
            return $this->respondWithError('NOT_FOUND', 'Contact not found', null, 404);
        }

        return $this->respondWithData(['id' => $id, 'updated' => true]);
    }

    /** GET /api/v2/admin/crm/contacts/{id}/notes */
    public function notes(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $contact = DB::selectOne(
            'SELECT id FROM crm_contacts WHERE id = ? AND tenant_id = ?',
            [$id, $tenantId]
        );

        if ($contact === null) {
            return $this->respondWithError('NOT_FOUND', 'Contact not found', null, 404);
        }

        $notes = DB::select(
            'SELECT * FROM crm_notes WHERE contact_id = ? ORDER BY created_at DESC',
            [$id]
        );

        return $this->respondWithData($notes);
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


    public function dashboard(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'dashboard');
    }


    public function funnel(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'funnel');
    }


    public function listAdmins(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'listAdmins');
    }


    public function listNotes(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'listNotes');
    }


    public function createNote(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'createNote');
    }


    public function updateNote($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'updateNote', [$id]);
    }


    public function deleteNote($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'deleteNote', [$id]);
    }


    public function listTasks(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'listTasks');
    }


    public function createTask(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'createTask');
    }


    public function updateTask($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'updateTask', [$id]);
    }


    public function deleteTask($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'deleteTask', [$id]);
    }


    public function listTags(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'listTags');
    }


    public function addTag(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'addTag');
    }


    public function bulkRemoveTag(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'bulkRemoveTag');
    }


    public function removeTag($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'removeTag', [$id]);
    }


    public function timeline(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'timeline');
    }


    public function exportNotes(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'exportNotes');
    }


    public function exportTasks(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'exportTasks');
    }


    public function exportDashboard(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\AdminCrmApiController::class, 'exportDashboard');
    }

}
