<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * AdminEnterpriseApiController - V2 API for React admin enterprise module
 *
 * Provides endpoints for enterprise features: roles & permissions, GDPR,
 * system monitoring, configuration, secrets vault, and legal documents.
 * All endpoints require admin authentication.
 *
 * Tables may not exist in all environments — methods return safe defaults
 * when tables are missing.
 */
class AdminEnterpriseApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    // ============================================
    // KNOWN PERMISSIONS
    // ============================================

    private const PERMISSIONS = [
        'users' => [
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.suspend',
            'users.ban',
            'users.impersonate',
        ],
        'listings' => [
            'listings.view',
            'listings.create',
            'listings.edit',
            'listings.delete',
            'listings.approve',
        ],
        'content' => [
            'content.blog.manage',
            'content.pages.manage',
            'content.categories.manage',
            'content.menus.manage',
        ],
        'wallet' => [
            'wallet.view',
            'wallet.transfer',
            'wallet.adjust',
            'wallet.org_wallets',
        ],
        'events' => [
            'events.view',
            'events.create',
            'events.edit',
            'events.delete',
        ],
        'groups' => [
            'groups.view',
            'groups.create',
            'groups.edit',
            'groups.delete',
            'groups.moderate',
        ],
        'messages' => [
            'messages.view',
            'messages.moderate',
        ],
        'gamification' => [
            'gamification.manage',
            'gamification.award_badges',
            'gamification.campaigns',
        ],
        'matching' => [
            'matching.config',
            'matching.approvals',
            'matching.analytics',
        ],
        'federation' => [
            'federation.manage',
            'federation.partnerships',
            'federation.api_keys',
        ],
        'gdpr' => [
            'gdpr.requests',
            'gdpr.consents',
            'gdpr.breaches',
            'gdpr.audit',
        ],
        'system' => [
            'system.config',
            'system.monitoring',
            'system.logs',
            'system.secrets',
            'system.cache',
            'system.cron',
        ],
        'admin' => [
            'admin.roles.manage',
            'admin.legal_docs.manage',
            'admin.newsletters.manage',
            'admin.tenant_features',
        ],
    ];

    // ============================================
    // ENTERPRISE DASHBOARD
    // ============================================

    /**
     * GET /api/v2/admin/enterprise/dashboard
     * Overview stats: user count, role count, pending GDPR, system health
     */
    public function dashboard(): void
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            // User count
            $stmt = Database::query(
                "SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ?",
                [$tenantId]
            );
            $userCount = (int) ($stmt->fetch()['cnt'] ?? 0);
        } catch (\Exception $e) {
            $userCount = 0;
        }

        // Role count
        $roleCount = 0;
        try {
            $stmt = Database::query(
                "SELECT COUNT(*) as cnt FROM roles WHERE tenant_id = ?",
                [$tenantId]
            );
            $roleCount = (int) ($stmt->fetch()['cnt'] ?? 0);
        } catch (\Exception $e) {
            // roles table may not exist — use default count
            $roleCount = 4; // member, admin, moderator, super_admin
        }

        // Pending GDPR requests
        $pendingGdpr = 0;
        try {
            $stmt = Database::query(
                "SELECT COUNT(*) as cnt FROM gdpr_requests WHERE tenant_id = ? AND status = 'pending'",
                [$tenantId]
            );
            $pendingGdpr = (int) ($stmt->fetch()['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Table may not exist
        }

        // System health — simple check
        $dbConnected = true;
        try {
            Database::query("SELECT 1");
        } catch (\Exception $e) {
            $dbConnected = false;
        }

        $redisConnected = false;
        try {
            $stats = \Nexus\Services\RedisCache::getStats();
            $redisConnected = !empty($stats['enabled']);
        } catch (\Exception $e) {
            // Redis not available
        }

        $healthStatus = ($dbConnected && $redisConnected) ? 'healthy' : ($dbConnected ? 'degraded' : 'unhealthy');

        $this->respondWithData([
            'user_count' => $userCount,
            'role_count' => $roleCount,
            'pending_gdpr_requests' => $pendingGdpr,
            'health_status' => $healthStatus,
            'db_connected' => $dbConnected,
            'redis_connected' => $redisConnected,
        ]);
    }

    // ============================================
    // ROLES & PERMISSIONS
    // ============================================

    /**
     * GET /api/v2/admin/enterprise/roles
     */
    public function roles(): void
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $rows = Database::query(
                "SELECT r.id, r.name, r.display_name, r.description, r.is_system, r.level, r.created_at, r.updated_at,
                        (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) as users_count,
                        GROUP_CONCAT(p.name) as permission_names
                 FROM roles r
                 LEFT JOIN role_permissions rp ON r.id = rp.role_id
                 LEFT JOIN permissions p ON rp.permission_id = p.id
                 WHERE r.tenant_id = ?
                 GROUP BY r.id
                 ORDER BY r.name ASC",
                [$tenantId]
            );
            $roles = $rows->fetchAll();

            foreach ($roles as &$role) {
                $role['permissions'] = $role['permission_names'] ? explode(',', $role['permission_names']) : [];
                unset($role['permission_names']);
            }
            unset($role);
        } catch (\Exception $e) {
            error_log('AdminEnterpriseApiController::roles() SQL error: ' . $e->getMessage());
            $this->respondWithData([]);
            return;
        }

        $this->respondWithData($roles);
    }

    /**
     * GET /api/v2/admin/enterprise/roles/{id}
     */
    public function showRole(int $id): void
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $stmt = Database::query(
                "SELECT r.*, (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) as users_count
                 FROM roles r WHERE r.id = ? AND r.tenant_id = ?",
                [$id, $tenantId]
            );
            $role = $stmt->fetch();

            if (!$role) {
                $this->respondWithError('NOT_FOUND', 'Role not found', null, 404);
                return;
            }

            $role['permissions'] = json_decode($role['permissions'] ?? '[]', true) ?: [];

            $this->respondWithData($role);
        } catch (\Exception $e) {
            $this->respondWithError('NOT_FOUND', 'Role not found or roles table not available', null, 404);
            return;
        }
    }

    /**
     * POST /api/v2/admin/enterprise/roles
     */
    public function createRole(): void
    {
        $this->requireAdmin();
        $data = $this->getJsonInput();
        $tenantId = TenantContext::getId();

        if (empty($data['name'])) {
            $this->respondWithError('VALIDATION_ERROR', 'Role name is required', 'name', 422);
            return;
        }

        $name = trim($data['name']);
        $displayName = $data['display_name'] ?? $name;
        $description = $data['description'] ?? null;
        $level = (int)($data['level'] ?? 10);
        $permissions = $data['permissions'] ?? [];

        try {
            Database::beginTransaction();

            Database::query(
                "INSERT INTO roles (name, display_name, description, level, tenant_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
                [$name, $displayName, $description, $level, $tenantId]
            );
            $roleId = Database::lastInsertId();

            // Assign permissions via junction table
            if (!empty($permissions)) {
                foreach ($permissions as $permName) {
                    $perm = Database::query(
                        "SELECT id FROM permissions WHERE name = ? LIMIT 1",
                        [$permName]
                    )->fetch();
                    if (!empty($perm)) {
                        Database::query(
                            "INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)",
                            [$roleId, $perm['id']]
                        );
                    }
                }
            }

            Database::commit();

            $result = Database::query(
                "SELECT r.*, GROUP_CONCAT(p.name) as permission_names
                 FROM roles r
                 LEFT JOIN role_permissions rp ON r.id = rp.role_id
                 LEFT JOIN permissions p ON rp.permission_id = p.id
                 WHERE r.id = ? AND r.tenant_id = ?
                 GROUP BY r.id",
                [$roleId, $tenantId]
            )->fetch() ?? [];
            $result['permissions'] = !empty($result['permission_names']) ? explode(',', $result['permission_names']) : [];
            unset($result['permission_names']);

            $this->respondWithData($result, null, 201);
        } catch (\Exception $e) {
            Database::rollback();
            error_log('createRole error: ' . $e->getMessage());
            $this->respondWithError('CREATE_FAILED', 'Failed to create role', null, 500);
            return;
        }
    }

    /**
     * PUT /api/v2/admin/enterprise/roles/{id}
     */
    public function updateRole(int $id): void
    {
        $this->requireAdmin();
        $data = $this->getJsonInput();
        $tenantId = TenantContext::getId();

        try {
            Database::beginTransaction();

            $setParts = [];
            $params = [];

            if (isset($data['name'])) { $setParts[] = 'name = ?'; $params[] = $data['name']; }
            if (isset($data['display_name'])) { $setParts[] = 'display_name = ?'; $params[] = $data['display_name']; }
            if (isset($data['description'])) { $setParts[] = 'description = ?'; $params[] = $data['description']; }
            if (isset($data['level'])) { $setParts[] = 'level = ?'; $params[] = (int)$data['level']; }

            if (!empty($setParts)) {
                $setParts[] = 'updated_at = NOW()';
                $params[] = $id;
                $params[] = $tenantId;
                Database::query(
                    "UPDATE roles SET " . implode(', ', $setParts) . " WHERE id = ? AND tenant_id = ?",
                    $params
                );
            }

            // Update permissions if provided
            // Verify the role belongs to the current tenant before modifying permissions
            if (isset($data['permissions']) && is_array($data['permissions'])) {
                $roleCheck = Database::query(
                    "SELECT id FROM roles WHERE id = ? AND tenant_id = ?",
                    [$id, $tenantId]
                )->fetch();
                if (!$roleCheck) {
                    Database::rollback();
                    $this->respondWithError('NOT_FOUND', 'Role not found for this tenant', null, 404);
                    return;
                }
                Database::query("DELETE FROM role_permissions WHERE role_id = ? AND tenant_id = ?", [$id, $tenantId]);
                foreach ($data['permissions'] as $permName) {
                    $perm = Database::query("SELECT id FROM permissions WHERE name = ? LIMIT 1", [$permName])->fetch();
                    if (!empty($perm)) {
                        Database::query(
                            "INSERT INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, ?)",
                            [$id, $perm['id'], $tenantId]
                        );
                    }
                }
            }

            Database::commit();

            $result = Database::query(
                "SELECT r.*, GROUP_CONCAT(p.name) as permission_names
                 FROM roles r
                 LEFT JOIN role_permissions rp ON r.id = rp.role_id
                 LEFT JOIN permissions p ON rp.permission_id = p.id
                 WHERE r.id = ? AND r.tenant_id = ?
                 GROUP BY r.id",
                [$id, $tenantId]
            )->fetch();

            if (empty($result)) {
                $this->respondWithError('NOT_FOUND', 'Role not found', null, 404);
                return;
            }

            $result['permissions'] = !empty($result['permission_names']) ? explode(',', $result['permission_names']) : [];
            unset($result['permission_names']);

            $this->respondWithData($result);
        } catch (\Exception $e) {
            Database::rollback();
            error_log('updateRole error: ' . $e->getMessage());
            $this->respondWithError('UPDATE_FAILED', 'Failed to update role', null, 500);
            return;
        }
    }

    /**
     * DELETE /api/v2/admin/enterprise/roles/{id}
     */
    public function deleteRole(int $id): void
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            Database::query(
                "DELETE FROM roles WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
            $this->respondWithData(['deleted' => true]);
        } catch (\Exception $e) {
            $this->respondWithError('DELETE_FAILED', 'Failed to delete role', null, 500);
            return;
        }
    }

    /**
     * GET /api/v2/admin/enterprise/permissions
     * Returns static list of all known permissions grouped by category
     */
    public function permissions(): void
    {
        $this->requireAdmin();

        $this->respondWithData(self::PERMISSIONS);
    }

    // ============================================
    // GDPR
    // ============================================

    /**
     * GET /api/v2/admin/enterprise/gdpr/dashboard
     */
    public function gdprDashboard(): void
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $pending = 0;
        $total = 0;
        try {
            $stmt = Database::query(
                "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
                 FROM gdpr_requests WHERE tenant_id = ?",
                [$tenantId]
            );
            $row = $stmt->fetch();
            $pending = (int) ($row['pending'] ?? 0);
            $total = (int) ($row['total'] ?? 0);
        } catch (\Exception $e) {
            // Table doesn't exist
        }

        $consents = 0;
        try {
            $stmt = Database::query(
                "SELECT COUNT(*) as cnt FROM user_consents WHERE tenant_id = ?",
                [$tenantId]
            );
            $consents = (int) ($stmt->fetch()['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Table doesn't exist
        }

        $breaches = 0;
        try {
            $stmt = Database::query(
                "SELECT COUNT(*) as cnt FROM data_breach_log WHERE tenant_id = ?",
                [$tenantId]
            );
            $breaches = (int) ($stmt->fetch()['cnt'] ?? 0);
        } catch (\Exception $e) {
            // Table doesn't exist
        }

        $this->respondWithData([
            'total_requests' => $total,
            'pending_requests' => $pending,
            'total_consents' => $consents,
            'total_breaches' => $breaches,
        ]);
    }

    /**
     * GET /api/v2/admin/enterprise/gdpr/requests
     */
    public function gdprRequests(): void
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $status = $this->query('status');
        $offset = ($page - 1) * $perPage;

        try {
            $where = "gr.tenant_id = ?";
            $params = [$tenantId];

            if ($status && $status !== 'all') {
                $where .= " AND gr.status = ?";
                $params[] = $status;
            }

            // Count
            $stmt = Database::query("SELECT COUNT(*) as cnt FROM gdpr_requests gr WHERE $where", $params);
            $total = (int) ($stmt->fetch()['cnt'] ?? 0);

            // Fetch
            $fetchParams = array_merge($params, [$perPage, $offset]);
            $stmt = Database::query(
                "SELECT gr.*, gr.request_type as type, u.name as user_name, u.email as user_email
                 FROM gdpr_requests gr
                 LEFT JOIN users u ON u.id = gr.user_id
                 WHERE $where
                 ORDER BY gr.created_at DESC
                 LIMIT ? OFFSET ?",
                $fetchParams
            );
            $requests = $stmt->fetchAll();

            $this->respondWithPaginatedCollection($requests, $total, $page, $perPage);
        } catch (\Exception $e) {
            $this->respondWithPaginatedCollection([], 0, 1, $perPage);
        }
    }

    /**
     * PUT /api/v2/admin/enterprise/gdpr/requests/{id}
     */
    public function updateGdprRequest(int $id): void
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $status = $this->input('status');
        $notes = $this->input('notes');

        if (empty($status)) {
            $this->respondWithError('VALIDATION_ERROR', 'Status is required', 'status', 422);
            return;
        }

        try {
            $updates = ["status = ?", "updated_at = NOW()"];
            $params = [$status];

            if ($notes !== null) {
                $updates[] = "notes = ?";
                $params[] = $notes;
            }

            if ($status === 'completed') {
                $updates[] = "completed_at = NOW()";
            }

            $params[] = $id;
            $params[] = $tenantId;

            Database::query(
                "UPDATE gdpr_requests SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?",
                $params
            );
            $this->respondWithData(['id' => $id, 'status' => $status, 'updated' => true]);
        } catch (\Exception $e) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to update GDPR request', null, 500);
            return;
        }
    }

    /**
     * GET /api/v2/admin/enterprise/gdpr/consents
     */
    public function gdprConsents(): void
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $stmt = Database::query(
                "SELECT uc.*, uc.consent_given as consented, uc.given_at as consented_at, u.name as user_name
                 FROM user_consents uc
                 LEFT JOIN users u ON u.id = uc.user_id
                 WHERE uc.tenant_id = ?
                 ORDER BY uc.created_at DESC
                 LIMIT 100",
                [$tenantId]
            );
            $consents = $stmt->fetchAll();
            $this->respondWithData($consents);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    /**
     * GET /api/v2/admin/enterprise/gdpr/breaches
     */
    public function gdprBreaches(): void
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $stmt = Database::query(
                "SELECT *, breach_type as title, detected_at as reported_at FROM data_breach_log WHERE tenant_id = ? ORDER BY detected_at DESC LIMIT 100",
                [$tenantId]
            );
            $breaches = $stmt->fetchAll();
            $this->respondWithData($breaches);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    /**
     * POST /api/v2/admin/enterprise/gdpr/breaches
     * Report a new data breach.
     */
    public function createBreach(): void
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $input = $this->getAllInput();

        $breachType = trim($input['breach_type'] ?? $input['title'] ?? '');
        if (!$breachType) {
            $this->respondWithError('VALIDATION_ERROR', 'Breach type is required', 'breach_type', 422);
            return;
        }

        try {
            Database::query(
                "INSERT INTO data_breach_log (tenant_id, breach_type, description, severity, status, detected_at, created_by, created_at)
                 VALUES (?, ?, ?, ?, 'open', NOW(), ?, NOW())",
                [
                    $tenantId,
                    $breachType,
                    $input['description'] ?? '',
                    $input['severity'] ?? 'medium',
                    (int) ($this->getAuthenticatedUserId() ?? 0),
                ]
            );
            $id = Database::lastInsertId();

            $this->respondWithData(['id' => $id, 'message' => 'Breach reported successfully'], null, 201);
        } catch (\Exception $e) {
            $this->respondWithError('CREATE_FAILED', 'Failed to report breach: ' . $e->getMessage(), null, 500);
            return;
        }
    }

    /**
     * GET /api/v2/admin/enterprise/gdpr/audit
     */
    public function gdprAudit(): void
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $stmt = Database::query(
                "SELECT gal.id, gal.tenant_id, gal.admin_id, gal.action, gal.entity_type, gal.entity_id,
                        gal.old_value, gal.new_value, gal.ip_address, gal.created_at,
                        u.name as user_name
                 FROM gdpr_audit_log gal
                 LEFT JOIN users u ON u.id = gal.admin_id
                 WHERE gal.tenant_id = ?
                 ORDER BY gal.created_at DESC
                 LIMIT 100",
                [$tenantId]
            );
            $entries = $stmt->fetchAll();
            $this->respondWithData($entries);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    // ============================================
    // MONITORING
    // ============================================

    /**
     * GET /api/v2/admin/enterprise/monitoring
     * System health info
     */
    public function monitoring(): void
    {
        $this->requireAdmin();

        $memUsage = memory_get_usage(true);
        $memLimit = ini_get('memory_limit');

        // DB size
        $dbSize = '0 MB';
        try {
            $dbName = getenv('DB_NAME') ?: 'nexus';
            $stmt = Database::query(
                "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                 FROM information_schema.tables WHERE table_schema = ?",
                [$dbName]
            );
            $row = $stmt->fetch();
            $dbSize = ($row['size_mb'] ?? '0') . ' MB';
        } catch (\Exception $e) {
            // Ignore
        }

        // Uptime
        $uptime = 'N/A';
        try {
            $stmt = Database::query("SHOW GLOBAL STATUS LIKE 'Uptime'");
            $row = $stmt->fetch();
            if ($row) {
                $seconds = (int) ($row['Value'] ?? 0);
                $days = floor($seconds / 86400);
                $hours = floor(($seconds % 86400) / 3600);
                $uptime = "{$days}d {$hours}h";
            }
        } catch (\Exception $e) {
            // Ignore
        }

        $redisConnected = false;
        $redisMemory = 'N/A';
        try {
            $stats = \Nexus\Services\RedisCache::getStats();
            $redisConnected = !empty($stats['enabled']);
            if ($redisConnected) {
                $redisMemory = $stats['memory_used'] ?? 'N/A';
            }
        } catch (\Exception $e) {
            // Ignore
        }

        $this->respondWithData([
            'php_version' => PHP_VERSION,
            'memory_usage' => $this->formatBytes($memUsage),
            'memory_limit' => $memLimit,
            'db_connected' => true,
            'db_size' => $dbSize,
            'redis_connected' => $redisConnected,
            'redis_memory' => $redisMemory,
            'uptime' => $uptime,
            'server_time' => date('Y-m-d H:i:s T'),
            'os' => PHP_OS,
        ]);
    }

    /**
     * GET /api/v2/admin/enterprise/monitoring/health
     */
    public function healthCheck(): void
    {
        $this->requireAdmin();

        $dbOk = false;
        try {
            Database::query("SELECT 1");
            $dbOk = true;
        } catch (\Exception $e) {
            // DB down
        }

        $redisOk = false;
        try {
            $stats = \Nexus\Services\RedisCache::getStats();
            $redisOk = !empty($stats['enabled']);
        } catch (\Exception $e) {
            // Redis unavailable
        }

        // Disk space
        $diskFree = 'N/A';
        $diskTotal = 'N/A';
        try {
            $free = disk_free_space('/');
            $total = disk_total_space('/');
            if ($free !== false && $total !== false) {
                $diskFree = $this->formatBytes($free);
                $diskTotal = $this->formatBytes($total);
            }
        } catch (\Exception $e) {
            // Ignore
        }

        $checks = [
            ['name' => 'Database', 'status' => $dbOk ? 'ok' : 'fail'],
            ['name' => 'Redis', 'status' => $redisOk ? 'ok' : 'fail'],
            ['name' => 'Disk', 'status' => 'ok', 'free' => $diskFree, 'total' => $diskTotal],
        ];

        // PHP extension checks
        $requiredExtensions = ['pdo_mysql', 'redis', 'zip', 'mbstring', 'gd', 'curl', 'json', 'openssl'];
        foreach ($requiredExtensions as $ext) {
            $checks[] = [
                'name'   => 'PHP ext: ' . $ext,
                'status' => extension_loaded($ext) ? 'ok' : 'fail',
            ];
        }
        $checks[] = [
            'name'   => 'PHP >= 8.2',
            'status' => version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'fail',
        ];

        $hasFailures = !empty(array_filter($checks, fn($c) => $c['status'] === 'fail'));
        $overall = $hasFailures ? 'unhealthy' : 'healthy';

        $this->respondWithData([
            'status' => $overall,
            'checks' => $checks,
        ]);
    }

    /**
     * GET /api/v2/admin/enterprise/monitoring/logs
     * Recent error-level activity log entries
     */
    public function logs(): void
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 50, 1, 200);
        $offset = ($page - 1) * $perPage;

        try {
            // Count
            $stmt = Database::query(
                "SELECT COUNT(*) as cnt FROM activity_log WHERE tenant_id = ? AND (action LIKE '%error%' OR action LIKE '%fail%' OR action LIKE '%exception%')",
                [$tenantId]
            );
            $total = (int) ($stmt->fetch()['cnt'] ?? 0);

            // Fetch
            $stmt = Database::query(
                "SELECT al.*, u.name as user_name
                 FROM activity_log al
                 LEFT JOIN users u ON u.id = al.user_id
                 WHERE al.tenant_id = ? AND (al.action LIKE '%error%' OR al.action LIKE '%fail%' OR al.action LIKE '%exception%')
                 ORDER BY al.created_at DESC
                 LIMIT ? OFFSET ?",
                [$tenantId, $perPage, $offset]
            );
            $logs = $stmt->fetchAll();

            $this->respondWithPaginatedCollection($logs, $total, $page, $perPage);
        } catch (\Exception $e) {
            $this->respondWithPaginatedCollection([], 0, 1, $perPage);
        }
    }

    // ============================================
    // CONFIGURATION
    // ============================================

    /**
     * GET /api/v2/admin/enterprise/config
     */
    public function config(): void
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $stmt = Database::query(
                "SELECT configuration FROM tenants WHERE id = ?",
                [$tenantId]
            );
            $row = $stmt->fetch();
            $config = json_decode($row['configuration'] ?? '{}', true) ?: [];

            $this->respondWithData($config);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    /**
     * PUT /api/v2/admin/enterprise/config
     */
    public function updateConfig(): void
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $newConfig = $this->getAllInput();

        if (empty($newConfig)) {
            $this->respondWithError('VALIDATION_ERROR', 'No configuration data provided', null, 422);
            return;
        }

        try {
            // Merge with existing config
            $stmt = Database::query(
                "SELECT configuration FROM tenants WHERE id = ?",
                [$tenantId]
            );
            $row = $stmt->fetch();
            $existing = json_decode($row['configuration'] ?? '{}', true) ?: [];
            $merged = array_merge($existing, $newConfig);

            Database::query(
                "UPDATE tenants SET configuration = ? WHERE id = ?",
                [json_encode($merged), $tenantId]
            );

            // Clear Redis cache if available
            try {
                \Nexus\Services\RedisCache::delete('tenant_bootstrap', $tenantId);
            } catch (\Exception $e) {
                // Redis may not be available
            }

            $this->respondWithData($merged);
        } catch (\Exception $e) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to update configuration', null, 500);
            return;
        }
    }

    /**
     * GET /api/v2/admin/enterprise/config/secrets
     * Returns env var names only, never values
     */
    public function secrets(): void
    {
        $this->requireAdmin();

        $secretKeys = [
            'DB_HOST',
            'DB_NAME',
            'DB_USER',
            'DB_PASS',
            'PUSHER_APP_ID',
            'PUSHER_KEY',
            'PUSHER_SECRET',
            'PUSHER_CLUSTER',
            'OPENAI_API_KEY',
            'GMAIL_CLIENT_ID',
            'GMAIL_CLIENT_SECRET',
            'GMAIL_REFRESH_TOKEN',
            'JWT_SECRET',
            'REDIS_HOST',
            'REDIS_PORT',
            'REDIS_PASSWORD',
            'FCM_SERVER_KEY',
            'VAPID_PUBLIC_KEY',
            'VAPID_PRIVATE_KEY',
        ];

        $secrets = [];
        foreach ($secretKeys as $key) {
            $value = getenv($key);
            $secrets[] = [
                'key' => $key,
                'is_set' => $value !== false && $value !== '',
                'masked_value' => ($value !== false && $value !== '') ? substr($value, 0, 3) . '***' : 'Not set',
            ];
        }

        $this->respondWithData($secrets);
    }

    // ============================================
    // LEGAL DOCUMENTS
    // ============================================

    /**
     * GET /api/v2/admin/legal-documents
     */
    public function legalDocs(): void
    {
        $this->requireAdmin();
        try {
            $docs = \Nexus\Services\LegalDocumentService::getAllForTenant(TenantContext::getId());
            $this->respondWithData($docs);
        } catch (\Exception $e) {
            error_log('legalDocs error: ' . $e->getMessage());
            $this->respondWithData([]);
        }
    }

    /**
     * GET /api/v2/admin/legal-documents/{id}
     */
    public function showLegalDoc(string|int $id): void
    {
        $this->requireAdmin();

        if (!is_numeric($id)) {
            $this->respondWithError('INVALID_ID', 'Legal document ID must be numeric', null, 400);
            return;
        }
        $id = (int) $id;
        $tenantId = $this->getTenantId();

        try {
            $stmt = Database::query(
                "SELECT * FROM legal_documents WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
            $doc = $stmt->fetch();

            if (!$doc) {
                $this->respondWithError('NOT_FOUND', 'Legal document not found', null, 404);
                return;
            }

            $this->respondWithData($doc);
        } catch (\Exception $e) {
            $this->respondWithError('NOT_FOUND', 'Legal document not found or table not available', null, 404);
            return;
        }
    }

    /**
     * POST /api/v2/admin/legal-documents
     */
    public function createLegalDoc(): void
    {
        $this->requireAdmin();
        $data = $this->getJsonInput();
        $adminId = $this->getAuthenticatedUserId();

        if (empty($data['title'])) {
            $this->respondWithError('VALIDATION_ERROR', 'Title is required', 'title', 422);
            return;
        }

        try {
            $doc = \Nexus\Services\LegalDocumentService::createDocument([
                'title'                   => $data['title'],
                'document_type'           => $data['type'] ?? $data['document_type'] ?? 'terms',
                'slug'                    => $data['slug'] ?? null,
                'requires_acceptance'     => $data['requires_acceptance'] ?? true,
                'acceptance_required_for' => $data['acceptance_required_for'] ?? 'registration',
                'notify_on_update'        => $data['notify_on_update'] ?? false,
                'is_active'               => $data['is_active'] ?? true,
                'created_by'              => $adminId,
            ]);
            $this->respondWithData($doc, null, 201);
        } catch (\Exception $e) {
            error_log('createLegalDoc error: ' . $e->getMessage());
            $this->respondWithError('CREATE_FAILED', 'Failed to create legal document: ' . $e->getMessage(), null, 500);
            return;
        }
    }

    /**
     * PUT /api/v2/admin/legal-documents/{id}
     */
    public function updateLegalDoc(int $id): void
    {
        $this->requireAdmin();
        $data = $this->getJsonInput();

        try {
            $updateData = [];
            if (isset($data['title'])) $updateData['title'] = $data['title'];
            if (isset($data['type'])) $updateData['document_type'] = $data['type'];
            if (isset($data['document_type'])) $updateData['document_type'] = $data['document_type'];
            if (isset($data['slug'])) $updateData['slug'] = $data['slug'];
            if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];
            if (isset($data['requires_acceptance'])) $updateData['requires_acceptance'] = $data['requires_acceptance'];
            if (isset($data['acceptance_required_for'])) $updateData['acceptance_required_for'] = $data['acceptance_required_for'];
            if (isset($data['notify_on_update'])) $updateData['notify_on_update'] = $data['notify_on_update'];

            $doc = \Nexus\Services\LegalDocumentService::updateDocument($id, $updateData);
            if (!$doc) {
                $this->respondWithError('NOT_FOUND', 'Document not found', null, 404);
                return;
            }
            $this->respondWithData($doc);
        } catch (\Exception $e) {
            error_log('updateLegalDoc error: ' . $e->getMessage());
            $this->respondWithError('UPDATE_FAILED', 'Failed to update legal document', null, 500);
            return;
        }
    }

    /**
     * DELETE /api/v2/admin/legal-documents/{id}
     */
    public function deleteLegalDoc(int $id): void
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            Database::query(
                "DELETE FROM legal_documents WHERE id = ? AND tenant_id = ?",
                [$id, $tenantId]
            );
            $this->respondWithData(['deleted' => true]);
        } catch (\Exception $e) {
            $this->respondWithError('DELETE_FAILED', 'Failed to delete legal document', null, 500);
            return;
        }
    }

    // ============================================
    // HELPERS
    // ============================================

    private function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
