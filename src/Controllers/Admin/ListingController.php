<?php

namespace Nexus\Controllers\Admin;

use Nexus\Core\Database;
use Nexus\Core\View;
use Nexus\Core\Auth;

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

        $page = $_GET['page'] ?? 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $tenantIdFilter = $_GET['tenant_id'] ?? null;

        // 2a. Fetch Tenants for Filter
        // We need to fetch all tenants for the dropdown
        // Assuming Tenant model exists and has all() method
        $tenants = [];
        try {
            $tenants = Database::query("SELECT id, name FROM tenants ORDER BY name ASC")->fetchAll();
        } catch (\Throwable $e) {
            $tenants = [];
        }

        // 2b. Unified Query
        // We select minimal common fields: id, title, created_at, user_id, tenant_id, and a static 'type'
        // Note: Event uses start_time as date; Vol uses created_by as user_id.
        $sql = "
            SELECT combined_content.*, t.name as tenant_name, u.name as author_name FROM (
                SELECT id, tenant_id, user_id, title, description, created_at, 'listing' as content_type FROM listings
                UNION ALL
                SELECT id, tenant_id, user_id, title, description, start_time as created_at, 'event' as content_type FROM events
                UNION ALL
                SELECT id, tenant_id, user_id, question as title, description, created_at, 'poll' as content_type FROM polls
                UNION ALL
                SELECT id, tenant_id, user_id, title, description, created_at, 'goal' as content_type FROM goals
                UNION ALL
                SELECT id, tenant_id, user_id, title, description, created_at, 'resource' as content_type FROM resources
                UNION ALL
                SELECT id, tenant_id, created_by as user_id, title, description, created_at, 'volunteer' as content_type FROM vol_opportunities
            ) AS combined_content
            LEFT JOIN tenants t ON combined_content.tenant_id = t.id
            LEFT JOIN users u ON combined_content.user_id = u.id
        ";

        // Apply Filter to the Outer Query
        $params = [];
        if ($tenantIdFilter && is_numeric($tenantIdFilter)) {
            $sql .= " WHERE combined_content.tenant_id = ?";
            $params[] = $tenantIdFilter;
        }

        $sql .= " ORDER BY combined_content.created_at DESC LIMIT $limit OFFSET $offset";

        // Query: Fetch Data
        try {
            $listings = Database::query($sql, $params)->fetchAll();
            // No post-processing needed now that we have explicit aliases
        } catch (\PDOException $e) {
            // Fallback for missing tables during dev
            $listings = [];
            error_log("Admin Directory Union Query Failed: " . $e->getMessage());
        }

        // 3. Count Total (Simplified estimation or separate count queries)
        // Calculating true total for UNION is expensive/complex without a wrapper.
        // For now, we'll fetch a rough count or just a static 'many'.
        // Let's do a quick separate sums query.
        $total = 0;
        try {
            $total += Database::query("SELECT COUNT(*) as c FROM listings")->fetch()['c'];
            $total += Database::query("SELECT COUNT(*) as c FROM events")->fetch()['c'];
            $total += Database::query("SELECT COUNT(*) as c FROM polls")->fetch()['c'];
            $total += Database::query("SELECT COUNT(*) as c FROM goals")->fetch()['c'];
            $total += Database::query("SELECT COUNT(*) as c FROM resources")->fetch()['c'];
            $total += Database::query("SELECT COUNT(*) as c FROM vol_opportunities")->fetch()['c'];
        } catch (\Throwable $e) {
        }

        $totalPages = ceil($total / $limit);

        // 4. Render View (Gold Standard - standalone admin)
        View::render('admin/listings/index', [
            'listings' => $listings,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'pageTitle' => 'Global Content Directory',
            'tenants' => $tenants,
            'currentTenantId' => $tenantIdFilter
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
        Database::query("DELETE FROM $table WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);

        // 4. Redirect back
        $redirect = $_SERVER['HTTP_REFERER'] ?? '/admin/listings?status=deleted';
        header("Location: $redirect");
        exit;
    }

    public function edit($id)
    {
        $this->requireAdmin();
        header("Location: /listings/edit/$id");
        exit;
    }
}
