<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Admin;

use Nexus\Core\Database;
use Nexus\Core\View;
use Nexus\Core\Auth;
use Nexus\Helpers\UrlHelper;

class ListingController
{
    private function requireAdmin()
    {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $role = $_SESSION['user_role'] ?? '';
        $isAdmin = in_array($role, ['admin', 'tenant_admin']) || !empty($_SESSION['is_super_admin']) || !empty($_SESSION['is_admin']);
        if (!$isAdmin) {
            header('Location: /login');
            exit;
        }
    }

    /**
     * Display a paginated list of all listings across all tenants.
     */
    /**
     * Display a unified directory of all user-generated content.
     */
    public function index()
    {
        // 1. Ensure Admin
        $this->requireAdmin();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $tenantIdFilter = $_GET['tenant_id'] ?? null;
        $statusFilter = $_GET['status'] ?? null;

        // 2a. Fetch Tenants for Filter
        $tenants = [];
        try {
            $tenants = Database::query("SELECT id, name FROM tenants ORDER BY name ASC")->fetchAll();
        } catch (\Throwable $e) {
            $tenants = [];
        }

        // 2b. Build Query based on status filter
        // When filtering by status, we only query listings table (other content types don't have status)
        $params = [];

        if ($statusFilter === 'pending') {
            // Pending Review: Only listings with status = 'pending' or NULL (not yet approved)
            $sql = "
                SELECT l.id, l.tenant_id, l.user_id, l.title, l.description, l.created_at,
                       l.status, 'listing' as content_type,
                       t.name as tenant_name, u.name as author_name
                FROM listings l
                LEFT JOIN tenants t ON l.tenant_id = t.id
                LEFT JOIN users u ON l.user_id = u.id
                WHERE (l.status = 'pending' OR l.status IS NULL OR l.status = '')
            ";

            if ($tenantIdFilter && is_numeric($tenantIdFilter)) {
                $sql .= " AND l.tenant_id = ?";
                $params[] = $tenantIdFilter;
            }

            $sql .= " ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            // Count for pending only
            $countSql = "SELECT COUNT(*) as c FROM listings WHERE (status = 'pending' OR status IS NULL OR status = '')";
            if ($tenantIdFilter && is_numeric($tenantIdFilter)) {
                $countSql .= " AND tenant_id = ?";
            }
        } else {
            // Default: Unified Query for all content types
            $sql = "
                SELECT combined_content.*, t.name as tenant_name, u.name as author_name FROM (
                    SELECT id, tenant_id, user_id, title, description, created_at, status, 'listing' as content_type FROM listings
                    UNION ALL
                    SELECT id, tenant_id, user_id, title, description, start_time as created_at, 'active' as status, 'event' as content_type FROM events
                    UNION ALL
                    SELECT id, tenant_id, user_id, question as title, description, created_at, 'active' as status, 'poll' as content_type FROM polls
                    UNION ALL
                    SELECT id, tenant_id, user_id, title, description, created_at, 'active' as status, 'goal' as content_type FROM goals
                    UNION ALL
                    SELECT id, tenant_id, user_id, title, description, created_at, 'active' as status, 'resource' as content_type FROM resources
                    UNION ALL
                    SELECT id, tenant_id, created_by as user_id, title, description, created_at, 'active' as status, 'volunteer' as content_type FROM vol_opportunities
                ) AS combined_content
                LEFT JOIN tenants t ON combined_content.tenant_id = t.id
                LEFT JOIN users u ON combined_content.user_id = u.id
            ";

            if ($tenantIdFilter && is_numeric($tenantIdFilter)) {
                $sql .= " WHERE combined_content.tenant_id = ?";
                $params[] = $tenantIdFilter;
            }

            $sql .= " ORDER BY combined_content.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            $countSql = null; // Use sum of all tables
        }

        // Query: Fetch Data
        try {
            $listings = Database::query($sql, $params)->fetchAll();
        } catch (\PDOException $e) {
            $listings = [];
            error_log("Admin Directory Query Failed: " . $e->getMessage());
        }

        // 3. Count Total
        $total = 0;
        try {
            if ($statusFilter === 'pending') {
                $countParams = ($tenantIdFilter && is_numeric($tenantIdFilter)) ? [$tenantIdFilter] : [];
                $total = Database::query($countSql, $countParams)->fetch()['c'];
            } else {
                $total += Database::query("SELECT COUNT(*) as c FROM listings")->fetch()['c'];
                $total += Database::query("SELECT COUNT(*) as c FROM events")->fetch()['c'];
                $total += Database::query("SELECT COUNT(*) as c FROM polls")->fetch()['c'];
                $total += Database::query("SELECT COUNT(*) as c FROM goals")->fetch()['c'];
                $total += Database::query("SELECT COUNT(*) as c FROM resources")->fetch()['c'];
                $total += Database::query("SELECT COUNT(*) as c FROM vol_opportunities")->fetch()['c'];
            }
        } catch (\Throwable $e) {
        }

        $totalPages = ceil($total / $limit);

        // 4. Render View
        $pageTitle = $statusFilter === 'pending' ? 'Pending Review' : 'Global Content Directory';

        View::render('admin/listings/index', [
            'listings' => $listings,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'pageTitle' => $pageTitle,
            'tenants' => $tenants,
            'currentTenantId' => $tenantIdFilter,
            'currentStatus' => $statusFilter
        ]);
    }

    /**
     * Delete a listing.
     */
    /**
     * Delete a content item.
     */
    public function delete($id)
    {
        // 1. Ensure Admin
        $this->requireAdmin();

        $type = $_GET['type'] ?? 'listing';

        // 2. Dynamic Table Selection
        switch ($type) {
            case 'event':
                $table = 'events';
                break;
            case 'poll':
                $table = 'polls';
                break;
            case 'goal':
                $table = 'goals';
                break;
            case 'resource':
                $table = 'resources';
                break;
            case 'volunteer':
                $table = 'vol_opportunities';
                break;
            case 'listing':
            default:
                $table = 'listings';
                break;
        }

        // 3. Execute Delete (Hard Delete for Admin) - SECURITY: Include tenant_id check
        $tenantId = \Nexus\Core\TenantContext::getId();
        Database::query("DELETE FROM `$table` WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

        // 4. Redirect back
        $redirect = UrlHelper::safeReferer('/admin-legacy/listings?status=deleted');
        header("Location: $redirect");
        exit;
    }

    public function edit($id)
    {
        $this->requireAdmin();
        header("Location: /listings/edit/$id");
        exit;
    }

    /**
     * Approve a pending listing.
     */
    public function approve($id)
    {
        $this->requireAdmin();

        $tenantId = \Nexus\Core\TenantContext::getId();

        // Update status to 'active'
        Database::query(
            "UPDATE listings SET status = 'active' WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        // Redirect back to pending review
        $redirect = UrlHelper::safeReferer('/admin-legacy/listings?status=pending');
        header("Location: $redirect");
        exit;
    }
}
