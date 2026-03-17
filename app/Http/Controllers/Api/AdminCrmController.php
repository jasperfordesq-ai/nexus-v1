<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * AdminCrmController -- CRM contact management, notes, tasks, tags, timeline, exports.
 *
 * Converted from legacy delegation to direct DB/service calls.
 * CSV export methods remain as delegation (they write to php://output).
 */
class AdminCrmController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    // ─────────────────────────────────────────────────────────────────────────
    // Contacts (already converted)
    // ─────────────────────────────────────────────────────────────────────────

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
            $items = DB::select('SELECT * FROM crm_contacts WHERE tenant_id = ? AND (name LIKE ? OR email LIKE ?) ORDER BY created_at DESC LIMIT ? OFFSET ?', [$tenantId, "%{$search}%", "%{$search}%", $perPage, $offset]);
            $total = DB::selectOne('SELECT COUNT(*) as cnt FROM crm_contacts WHERE tenant_id = ? AND (name LIKE ? OR email LIKE ?)', [$tenantId, "%{$search}%", "%{$search}%"])->cnt;
        } else {
            $items = DB::select('SELECT * FROM crm_contacts WHERE tenant_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?', [$tenantId, $perPage, $offset]);
            $total = DB::selectOne('SELECT COUNT(*) as cnt FROM crm_contacts WHERE tenant_id = ?', [$tenantId])->cnt;
        }
        return $this->respondWithPaginatedCollection($items, (int) $total, $page, $perPage);
    }

    /** GET /api/v2/admin/crm/contacts/{id} */
    public function show(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $contact = DB::selectOne('SELECT * FROM crm_contacts WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
        if ($contact === null) { return $this->respondWithError('NOT_FOUND', 'Contact not found', null, 404); }
        return $this->respondWithData($contact);
    }

    /** PUT /api/v2/admin/crm/contacts/{id} */
    public function update(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $data = $this->getAllInput();
        $allowed = ['name', 'email', 'phone', 'organization', 'tags', 'status'];
        $sets = []; $params = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) { $sets[] = "{$key} = ?"; $params[] = $value; }
        }
        if (empty($sets)) { return $this->respondWithError('VALIDATION_ERROR', 'No valid fields to update'); }
        $params[] = $id; $params[] = $tenantId;
        $affected = DB::update('UPDATE crm_contacts SET ' . implode(', ', $sets) . ' WHERE id = ? AND tenant_id = ?', $params);
        if ($affected === 0) { return $this->respondWithError('NOT_FOUND', 'Contact not found', null, 404); }
        return $this->respondWithData(['id' => $id, 'updated' => true]);
    }

    /** GET /api/v2/admin/crm/contacts/{id}/notes */
    public function notes(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $contact = DB::selectOne('SELECT id FROM crm_contacts WHERE id = ? AND tenant_id = ?', [$id, $tenantId]);
        if ($contact === null) { return $this->respondWithError('NOT_FOUND', 'Contact not found', null, 404); }
        $notes = DB::select('SELECT * FROM crm_notes WHERE contact_id = ? ORDER BY created_at DESC', [$id]);
        return $this->respondWithData($notes);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Dashboard
    // ─────────────────────────────────────────────────────────────────────────

    public function dashboard(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $totalMembers = (int) Database::query("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ?", [$tenantId])->fetch()['cnt'];
        $activeMembers = (int) Database::query("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [$tenantId])->fetch()['cnt'];
        $newThisMonth = (int) Database::query("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')", [$tenantId])->fetch()['cnt'];
        $pendingApprovals = (int) Database::query("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND is_approved = 0", [$tenantId])->fetch()['cnt'];

        $openTasks = 0; $overdueTasks = 0;
        try {
            $openTasks = (int) Database::query("SELECT COUNT(*) as cnt FROM coordinator_tasks WHERE tenant_id = ? AND status IN ('pending','in_progress')", [$tenantId])->fetch()['cnt'];
            $overdueTasks = (int) Database::query("SELECT COUNT(*) as cnt FROM coordinator_tasks WHERE tenant_id = ? AND status IN ('pending','in_progress') AND due_date < CURDATE()", [$tenantId])->fetch()['cnt'];
        } catch (\Throwable $e) {}

        $totalNotes = 0;
        try { $totalNotes = (int) Database::query("SELECT COUNT(*) as cnt FROM member_notes WHERE tenant_id = ?", [$tenantId])->fetch()['cnt']; } catch (\Throwable $e) {}

        $neverLoggedIn = (int) Database::query("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND last_login_at IS NULL AND is_approved = 1", [$tenantId])->fetch()['cnt'];
        $approvedMembers = (int) Database::query("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND is_approved = 1", [$tenantId])->fetch()['cnt'];
        $retentionRate = $approvedMembers > 0 ? round(($activeMembers / $approvedMembers) * 100, 1) : 0;

        return $this->respondWithData([
            'total_members' => $totalMembers, 'active_members' => $activeMembers,
            'new_this_month' => $newThisMonth, 'pending_approvals' => $pendingApprovals,
            'open_tasks' => $openTasks, 'overdue_tasks' => $overdueTasks,
            'total_notes' => $totalNotes, 'never_logged_in' => $neverLoggedIn,
            'retention_rate' => $retentionRate,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Funnel
    // ─────────────────────────────────────────────────────────────────────────

    public function funnel(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $registered = (int) Database::query("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ?", [$tenantId])->fetch()['cnt'];
        $emailVerified = (int) Database::query("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND email_verified_at IS NOT NULL", [$tenantId])->fetch()['cnt'];
        $profileCompleted = (int) Database::query("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ? AND (bio IS NOT NULL AND bio != '') AND (location IS NOT NULL AND location != '')", [$tenantId])->fetch()['cnt'];

        $firstListing = 0;
        try { $firstListing = (int) Database::query("SELECT COUNT(DISTINCT user_id) as cnt FROM listings WHERE tenant_id = ?", [$tenantId])->fetch()['cnt']; } catch (\Throwable $e) {}

        $firstExchange = 0;
        try {
            $firstExchange = (int) Database::query(
                "SELECT COUNT(DISTINCT u) as cnt FROM (SELECT sender_id as u FROM transactions WHERE tenant_id = ? AND status = 'completed' UNION SELECT receiver_id as u FROM transactions WHERE tenant_id = ? AND status = 'completed') AS combined",
                [$tenantId, $tenantId]
            )->fetch()['cnt'];
        } catch (\Throwable $e) {}

        $repeatUser = 0;
        try {
            $repeatUser = (int) Database::query(
                "SELECT COUNT(*) as cnt FROM (SELECT u, COUNT(*) as tx_count FROM (SELECT sender_id as u FROM transactions WHERE tenant_id = ? AND status = 'completed' UNION ALL SELECT receiver_id as u FROM transactions WHERE tenant_id = ? AND status = 'completed') AS all_tx GROUP BY u HAVING tx_count >= 2) AS repeat_users",
                [$tenantId, $tenantId]
            )->fetch()['cnt'];
        } catch (\Throwable $e) {}

        $monthlyRegistrations = Database::query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM users WHERE tenant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC",
            [$tenantId]
        )->fetchAll();

        return $this->respondWithData([
            'stages' => [
                ['name' => 'Registered', 'count' => $registered, 'color' => '#3b82f6'],
                ['name' => 'Email Verified', 'count' => $emailVerified, 'color' => '#6366f1'],
                ['name' => 'Profile Complete', 'count' => $profileCompleted, 'color' => '#8b5cf6'],
                ['name' => 'First Listing', 'count' => $firstListing, 'color' => '#a855f7'],
                ['name' => 'First Exchange', 'count' => $firstExchange, 'color' => '#d946ef'],
                ['name' => 'Repeat User', 'count' => $repeatUser, 'color' => '#ec4899'],
            ],
            'monthly_registrations' => $monthlyRegistrations,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin list
    // ─────────────────────────────────────────────────────────────────────────

    public function listAdmins(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $admins = Database::query(
            "SELECT id, name, email, avatar_url, role FROM users WHERE tenant_id = ? AND (role IN ('admin','moderator','tenant_admin','super_admin') OR is_admin = 1) ORDER BY name ASC",
            [$tenantId]
        )->fetchAll();

        return $this->respondWithCollection($admins);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Notes, Tasks, Tags, Timeline — delegate to legacy (complex DB logic)
    // ─────────────────────────────────────────────────────────────────────────

    public function listNotes(): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminCrmApiController::class, 'listNotes');
    }

    public function createNote(): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminCrmApiController::class, 'createNote');
    }

    public function updateNote($id): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminCrmApiController::class, 'updateNote', [(int)$id]);
    }

    public function deleteNote($id): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminCrmApiController::class, 'deleteNote', [(int)$id]);
    }

    public function listTasks(): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminCrmApiController::class, 'listTasks');
    }

    public function createTask(): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminCrmApiController::class, 'createTask');
    }

    public function updateTask($id): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminCrmApiController::class, 'updateTask', [(int)$id]);
    }

    public function deleteTask($id): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminCrmApiController::class, 'deleteTask', [(int)$id]);
    }

    public function listTags(): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminCrmApiController::class, 'listTags');
    }

    public function addTag(): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminCrmApiController::class, 'addTag');
    }

    public function bulkRemoveTag(): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminCrmApiController::class, 'bulkRemoveTag');
    }

    public function removeTag($id): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminCrmApiController::class, 'removeTag', [(int)$id]);
    }

    public function timeline(): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminCrmApiController::class, 'timeline');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CSV Exports — delegation (write to php://output + exit)
    // ─────────────────────────────────────────────────────────────────────────

    public function exportNotes(): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminCrmApiController::class, 'exportNotes');
    }

    public function exportTasks(): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminCrmApiController::class, 'exportTasks');
    }

    public function exportDashboard(): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminCrmApiController::class, 'exportDashboard');
    }

    /**
     * Delegate to legacy controller.
     */
    private function delegateLegacy(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }
}
