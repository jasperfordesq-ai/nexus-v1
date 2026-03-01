<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Models\ActivityLog;
use Nexus\Services\ListingFeaturedService;
use Nexus\Services\ListingModerationService;
use Nexus\Services\SearchLogService;

/**
 * AdminListingsApiController - V2 API for React admin content moderation
 *
 * Provides listing/content management for the admin panel.
 * All endpoints require admin authentication.
 *
 * Endpoints:
 * - GET    /api/v2/admin/listings                      - List all content (paginated, filterable)
 * - GET    /api/v2/admin/listings/{id}                 - Get single listing detail
 * - POST   /api/v2/admin/listings/{id}/approve         - Approve a pending listing
 * - DELETE /api/v2/admin/listings/{id}                 - Delete a listing
 * - POST   /api/v2/admin/listings/{id}/feature         - Feature a listing
 * - DELETE /api/v2/admin/listings/{id}/feature         - Unfeature a listing
 * - POST   /api/v2/admin/listings/{id}/reject          - Reject a listing (moderation)
 * - GET    /api/v2/admin/listings/moderation-queue      - Get moderation queue
 * - GET    /api/v2/admin/listings/moderation-stats      - Get moderation stats
 * - GET    /api/v2/admin/search/analytics               - Get search analytics
 * - GET    /api/v2/admin/search/trending                - Get trending searches (admin)
 * - GET    /api/v2/admin/search/zero-results            - Get zero-result queries
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
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

        $conditions = [];
        $params = [];

        // Tenant scoping: defaults to current tenant, super admins can explicitly request all
        $effectiveTenantId = $this->resolveAdminTenantFilter($isSuperAdmin, $tenantId);
        if ($effectiveTenantId !== null) {
            $conditions[] = 'l.tenant_id = ?';
            $params[] = $effectiveTenantId;
        }

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

        $where = !empty($conditions) ? implode(' AND ', $conditions) : '1=1';

        // Map sort column via allowlist (prevents SQL injection even though $sort is already whitelisted)
        $sortColumnMap = [
            'title' => 'l.title',
            'type' => 'l.type',
            'status' => 'l.status',
            'created_at' => 'l.created_at',
            'user_name' => 'user_name',
        ];
        $sortColumn = $sortColumnMap[$sort] ?? 'l.created_at';

        // Total count
        $total = (int) Database::query(
            "SELECT COUNT(*) as cnt FROM listings l WHERE {$where}",
            $params
        )->fetch()['cnt'];

        // Paginated results — join tenants table for cross-tenant name display
        // nosemgrep: tainted-sql-string — $sortColumn from allowlist map, $order validated against ['ASC','DESC'], $where from parameterized conditions
        $items = Database::query(
            "SELECT l.id, l.title, l.description, l.type, l.status, l.created_at, l.updated_at,
                    l.user_id, l.category_id, l.price, l.tenant_id,
                    CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name,
                    u.email as user_email, u.avatar_url as user_avatar,
                    c.name as category_name,
                    t.name as tenant_name
             FROM listings l
             LEFT JOIN users u ON l.user_id = u.id
             LEFT JOIN categories c ON l.category_id = c.id
             LEFT JOIN tenants t ON l.tenant_id = t.id
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
                'tenant_id' => (int) $row['tenant_id'],
                'tenant_name' => $row['tenant_name'] ?? 'Unknown',
                'user_id' => (int) ($row['user_id'] ?? 0),
                'user_name' => trim($row['user_name'] ?? ''),
                'user_email' => $row['user_email'] ?? '',
                'user_avatar' => $row['user_avatar'] ?? null,
                'category_id' => $row['category_id'] ? (int) $row['category_id'] : null,
                'category_name' => $row['category_name'] ?? null,
                'price' => $row['price'] ? (float) $row['price'] : null,
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        // Super admins can view listings from any tenant
        if ($isSuperAdmin) {
            $item = Database::query(
                "SELECT l.*,
                        CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name,
                        u.email as user_email, u.avatar_url as user_avatar,
                        c.name as category_name,
                        t.name as tenant_name
                 FROM listings l
                 LEFT JOIN users u ON l.user_id = u.id
                 LEFT JOIN categories c ON l.category_id = c.id
                 LEFT JOIN tenants t ON l.tenant_id = t.id
                 WHERE l.id = ?",
                [$id]
            )->fetch();
        } else {
            $item = Database::query(
                "SELECT l.*,
                        CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as user_name,
                        u.email as user_email, u.avatar_url as user_avatar,
                        c.name as category_name,
                        t.name as tenant_name
                 FROM listings l
                 LEFT JOIN users u ON l.user_id = u.id
                 LEFT JOIN categories c ON l.category_id = c.id
                 LEFT JOIN tenants t ON l.tenant_id = t.id
                 WHERE l.id = ? AND l.tenant_id = ?",
                [$id, $tenantId]
            )->fetch();
        }

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
            'tenant_id' => (int) $item['tenant_id'],
            'tenant_name' => $item['tenant_name'] ?? 'Unknown',
            'user_id' => (int) ($item['user_id'] ?? 0),
            'user_name' => trim($item['user_name'] ?? ''),
            'user_email' => $item['user_email'] ?? '',
            'user_avatar' => $item['user_avatar'] ?? null,
            'category_id' => $item['category_id'] ? (int) $item['category_id'] : null,
            'category_name' => $item['category_name'] ?? null,
            'price' => $item['price'] ? (float) $item['price'] : null,
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
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        // Super admins can approve listings from any tenant
        if ($isSuperAdmin) {
            $item = Database::query(
                "SELECT id, title, status, tenant_id FROM listings WHERE id = ?",
                [$id]
            )->fetch();
        } else {
            $item = Database::query(
                "SELECT id, title, status, tenant_id FROM listings WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();
        }

        if (!$item) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Listing not found', null, 404);
            return;
        }

        // Use the record's own tenant_id for the write
        $itemTenantId = (int) $item['tenant_id'];

        Database::query(
            "UPDATE listings SET status = 'active' WHERE id = ? AND tenant_id = ?",
            [$id, $itemTenantId]
        );

        ActivityLog::log(
            $adminId,
            'admin_approve_listing',
            "Approved listing #{$id}: {$item['title']}" . ($isSuperAdmin ? " (tenant {$itemTenantId})" : '')
        );

        $this->respondWithData(['approved' => true, 'id' => $id]);
    }

    /**
     * DELETE /api/v2/admin/listings/{id}
     */
    public function destroy(int $id): void
    {
        $adminId = $this->requireAdmin();
        $isSuperAdmin = $this->isAuthenticatedSuperAdmin();
        $tenantId = TenantContext::getId();

        // Super admins can delete listings from any tenant
        if ($isSuperAdmin) {
            $item = Database::query(
                "SELECT id, title, tenant_id FROM listings WHERE id = ?",
                [$id]
            )->fetch();
        } else {
            $item = Database::query(
                "SELECT id, title, tenant_id FROM listings WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            )->fetch();
        }

        if (!$item) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Listing not found', null, 404);
            return;
        }

        // Use the record's own tenant_id for the delete (safety: always keep AND tenant_id = ?)
        $itemTenantId = (int) $item['tenant_id'];

        Database::query(
            "DELETE FROM listings WHERE id = ? AND tenant_id = ?",
            [$id, $itemTenantId]
        );

        ActivityLog::log(
            $adminId,
            'admin_delete_listing',
            "Deleted listing #{$id}: {$item['title']}" . ($isSuperAdmin ? " (tenant {$itemTenantId})" : '')
        );

        $this->respondWithData(['deleted' => true, 'id' => $id]);
    }

    /**
     * POST /api/v2/admin/listings/{id}/feature
     *
     * Feature a listing. Optional body: { "days": 7 }
     */
    public function feature(int $id): void
    {
        $this->requireAdmin();
        $data = $this->getAllInput();
        $days = isset($data['days']) ? (int)$data['days'] : null;

        $result = ListingFeaturedService::featureListing($id, $days);

        if (!$result['success']) {
            $this->respondWithError('FEATURE_FAILED', $result['error'], null, 404);
            return;
        }

        $this->respondWithData([
            'featured' => true,
            'id' => $id,
            'featured_until' => $result['featured_until'],
        ]);
    }

    /**
     * DELETE /api/v2/admin/listings/{id}/feature
     *
     * Unfeature a listing.
     */
    public function unfeature(int $id): void
    {
        $this->requireAdmin();

        $result = ListingFeaturedService::unfeatureListing($id);

        if (!$result['success']) {
            $this->respondWithError('UNFEATURE_FAILED', $result['error'], null, 404);
            return;
        }

        $this->respondWithData(['featured' => false, 'id' => $id]);
    }

    /**
     * POST /api/v2/admin/listings/{id}/reject
     *
     * Reject a listing during moderation review.
     * Body: { "reason": "Rejection reason here" }
     */
    public function reject(int $id): void
    {
        $adminId = $this->requireAdmin();
        $data = $this->getAllInput();
        $reason = $data['reason'] ?? '';

        $result = ListingModerationService::reject($id, $adminId, $reason);

        if (!$result['success']) {
            $status = $result['error'] === 'Listing not found' ? 404 : 422;
            $this->respondWithError('REJECT_FAILED', $result['error'], null, $status);
            return;
        }

        $this->respondWithData(['rejected' => true, 'id' => $id]);
    }

    /**
     * GET /api/v2/admin/listings/moderation-queue
     *
     * Get pending listings for moderation review.
     * Query params: page, limit, type
     */
    public function moderationQueue(): void
    {
        $this->requireAdmin();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $type = $_GET['type'] ?? null;

        $result = ListingModerationService::getReviewQueue($page, $limit, $type);

        $this->respondWithPaginatedCollection(
            $result['items'],
            $result['total'],
            $page,
            $limit
        );
    }

    /**
     * GET /api/v2/admin/listings/moderation-stats
     *
     * Get moderation statistics.
     */
    public function moderationStats(): void
    {
        $this->requireAdmin();

        $stats = ListingModerationService::getStats();

        $this->respondWithData($stats);
    }

    /**
     * GET /api/v2/admin/search/analytics
     *
     * Get search analytics summary.
     * Query params: days (default 30)
     */
    public function searchAnalytics(): void
    {
        $this->requireAdmin();

        $days = min(90, max(1, (int)($_GET['days'] ?? 30)));

        $analytics = SearchLogService::getAnalyticsSummary($days);

        $this->respondWithData($analytics);
    }

    /**
     * GET /api/v2/admin/search/trending
     *
     * Get trending searches (admin view).
     * Query params: days, limit
     */
    public function searchTrending(): void
    {
        $this->requireAdmin();

        $days = min(90, max(1, (int)($_GET['days'] ?? 7)));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));

        $trending = SearchLogService::getTrendingSearches($days, $limit);

        $this->respondWithData($trending);
    }

    /**
     * GET /api/v2/admin/search/zero-results
     *
     * Get queries that returned zero results.
     * Query params: days, limit
     */
    public function searchZeroResults(): void
    {
        $this->requireAdmin();

        $days = min(90, max(1, (int)($_GET['days'] ?? 30)));
        $limit = min(50, max(1, (int)($_GET['limit'] ?? 20)));

        $zeroResults = SearchLogService::getZeroResultSearches($days, $limit);

        $this->respondWithData($zeroResults);
    }
}
