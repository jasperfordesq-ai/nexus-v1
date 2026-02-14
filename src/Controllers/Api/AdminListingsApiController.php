<?php

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Models\ActivityLog;

/**
 * AdminListingsApiController - V2 API for React admin content moderation
 *
 * Provides listing/content management for the admin panel.
 * All endpoints require admin authentication.
 *
 * Endpoints:
 * - GET    /api/v2/admin/listings              - List all content (paginated, filterable)
 * - GET    /api/v2/admin/listings/{id}         - Get single listing detail
 * - POST   /api/v2/admin/listings/{id}/approve - Approve a pending listing
 * - DELETE /api/v2/admin/listings/{id}         - Delete a listing
 */
class AdminListingsApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/admin/listings
     *
     * Query params: page, limit, status (all|pending|active|inactive), type, search, sort, order
     */
    public function index(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $status = $_GET['status'] ?? null;
        $type = $_GET['type'] ?? null;
        $search = $_GET['search'] ?? null;
        $sort = $_GET['sort'] ?? 'created_at';
        $order = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

        // Whitelist sort columns
        $allowedSorts = ['title', 'type', 'status', 'created_at', 'user_name'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'created_at';
        }

        $conditions = ['l.tenant_id = ?'];
        $params = [$tenantId];

        // Status filter
        if ($status && $status !== 'all') {
            switch ($status) {
                case 'pending':
                    $conditions[] = "l.status = 'pending'";
                    break;
                case 'active':
                    $conditions[] = "l.status = 'active'";
                    break;
                case 'inactive':
                    $conditions[] = "l.status IN ('inactive', 'expired', 'closed')";
                    break;
            }
        }

        // Type filter
        if ($type) {
            $conditions[] = 'l.type = ?';
            $params[] = $type;
        }

        // Search filter
        if ($search) {
            $conditions[] = "(l.title LIKE ? OR l.description LIKE ?)";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $where = implode(' AND ', $conditions);

        // Map 'user_name' sort
        $sortColumn = $sort === 'user_name' ? 'user_name' : "l.{$sort}";

        // Total count
        $total = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM listings l WHERE {$where}",
            $params
        )->fetch()['cnt'];

        // Paginated results
        $items = Database::query(
            "SELECT l.id, l.title, l.description, l.type, l.status, l.created_at, l.updated_at,
                    l.user_id, l.category_id, l.hours_estimated,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name,
                    u.email as user_email, u.avatar_url as user_avatar,
                    c.name as category_name
             FROM listings l
             LEFT JOIN users u ON l.user_id = u.id
             LEFT JOIN categories c ON l.category_id = c.id
             WHERE {$where}
             ORDER BY {$sortColumn} {$order}
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        )->fetchAll();

        // Format for frontend
        $formatted = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'title' => $row['title'] ?? '',
                'description' => $row['description'] ?? '',
                'type' => $row['type'] ?? 'listing',
                'status' => $row['status'] ?? 'active',
                'user_id' => (int) ($row['user_id'] ?? 0),
                'user_name' => trim($row['user_name'] ?? ''),
                'user_email' => $row['user_email'] ?? '',
                'user_avatar' => $row['user_avatar'] ?? null,
                'category_id' => $row['category_id'] ? (int) $row['category_id'] : null,
                'category_name' => $row['category_name'] ?? null,
                'hours_estimated' => $row['hours_estimated'] ? (float) $row['hours_estimated'] : null,
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'] ?? null,
            ];
        }, $items);

        $this->respondWithPaginatedCollection($formatted, $total, $page, $limit);
    }

    /**
     * GET /api/v2/admin/listings/{id}
     */
    public function show(int $id): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $item = Database::query(
            "SELECT l.*,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name,
                    u.email as user_email, u.avatar_url as user_avatar,
                    c.name as category_name
             FROM listings l
             LEFT JOIN users u ON l.user_id = u.id
             LEFT JOIN categories c ON l.category_id = c.id
             WHERE l.id = ? AND l.tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$item) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Listing not found', null, 404);
            return;
        }

        $this->respondWithData([
            'id' => (int) $item['id'],
            'title' => $item['title'] ?? '',
            'description' => $item['description'] ?? '',
            'type' => $item['type'] ?? 'listing',
            'status' => $item['status'] ?? 'active',
            'user_id' => (int) ($item['user_id'] ?? 0),
            'user_name' => trim($item['user_name'] ?? ''),
            'user_email' => $item['user_email'] ?? '',
            'user_avatar' => $item['user_avatar'] ?? null,
            'category_id' => $item['category_id'] ? (int) $item['category_id'] : null,
            'category_name' => $item['category_name'] ?? null,
            'hours_estimated' => $item['hours_estimated'] ? (float) $item['hours_estimated'] : null,
            'location' => $item['location'] ?? null,
            'created_at' => $item['created_at'],
            'updated_at' => $item['updated_at'] ?? null,
        ]);
    }

    /**
     * POST /api/v2/admin/listings/{id}/approve
     */
    public function approve(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $item = Database::query(
            "SELECT id, title, status FROM listings WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$item) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Listing not found', null, 404);
            return;
        }

        Database::query(
            "UPDATE listings SET status = 'active' WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        ActivityLog::log($adminId, 'admin_approve_listing', "Approved listing #{$id}: {$item['title']}");

        $this->respondWithData(['approved' => true, 'id' => $id]);
    }

    /**
     * DELETE /api/v2/admin/listings/{id}
     */
    public function destroy(int $id): void
    {
        $adminId = $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $item = Database::query(
            "SELECT id, title FROM listings WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        )->fetch();

        if (!$item) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Listing not found', null, 404);
            return;
        }

        Database::query(
            "DELETE FROM listings WHERE id = ? AND tenant_id = ?",
            [$id, $tenantId]
        );

        ActivityLog::log($adminId, 'admin_delete_listing', "Deleted listing #{$id}: {$item['title']}");

        $this->respondWithData(['deleted' => true, 'id' => $id]);
    }
}
