<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;
use App\Services\LegalDocumentService;

/**
 * AdminEnterpriseController -- Enterprise: roles, GDPR, monitoring, health, logs, secrets, legal docs.
 *
 * Converted from legacy delegation to direct DB/service calls.
 */
class AdminEnterpriseController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly LegalDocumentService $legalDocumentService,
    ) {}

    private const PERMISSIONS = [
        'users' => ['users.view','users.create','users.edit','users.delete','users.suspend','users.ban','users.impersonate'],
        'listings' => ['listings.view','listings.create','listings.edit','listings.delete','listings.approve'],
        'content' => ['content.blog.manage','content.pages.manage','content.categories.manage','content.menus.manage'],
        'wallet' => ['wallet.view','wallet.transfer','wallet.adjust','wallet.org_wallets'],
        'events' => ['events.view','events.create','events.edit','events.delete'],
        'groups' => ['groups.view','groups.create','groups.edit','groups.delete','groups.moderate'],
        'messages' => ['messages.view','messages.moderate'],
        'gamification' => ['gamification.manage','gamification.award_badges','gamification.campaigns'],
        'matching' => ['matching.config','matching.approvals','matching.analytics'],
        'federation' => ['federation.manage','federation.partnerships','federation.api_keys'],
        'gdpr' => ['gdpr.requests','gdpr.consents','gdpr.breaches','gdpr.audit'],
        'system' => ['system.config','system.monitoring','system.logs','system.secrets','system.cache','system.cron'],
        'admin' => ['admin.roles.manage','admin.legal_docs.manage','admin.newsletters.manage','admin.tenant_features'],
    ];

    /** GET /api/v2/admin/enterprise/dashboard */
    public function dashboard(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try { $userCount = (int)(DB::selectOne("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = ?", [$tenantId])->cnt ?? 0); } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminEnterprise] User count query failed: ' . $e->getMessage()); $userCount = 0; }

        $roleCount = 0;
        try { $roleCount = (int)(DB::selectOne("SELECT COUNT(*) as cnt FROM roles WHERE tenant_id = ?", [$tenantId])->cnt ?? 0); } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminEnterprise] Role count query failed: ' . $e->getMessage()); }

        $pendingGdpr = 0;
        try { $pendingGdpr = (int)(DB::selectOne("SELECT COUNT(*) as cnt FROM gdpr_requests WHERE tenant_id = ? AND status = 'pending'", [$tenantId])->cnt ?? 0); } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminEnterprise] GDPR pending count query failed: ' . $e->getMessage()); }

        $dbConnected = true;
        try { DB::select("SELECT 1"); } catch (\Exception $e) { $dbConnected = false; }

        $redisConnected = false;
        try { $stats = app(\App\Services\RedisCache::class)->getStats(); $redisConnected = !empty($stats['enabled']); } catch (\Throwable $e) {}

        $healthStatus = ($dbConnected && $redisConnected) ? 'healthy' : ($dbConnected ? 'degraded' : 'unhealthy');

        return $this->respondWithData([
            'user_count' => $userCount, 'role_count' => $roleCount,
            'pending_gdpr_requests' => $pendingGdpr, 'health_status' => $healthStatus,
            'db_connected' => $dbConnected, 'redis_connected' => $redisConnected,
        ]);
    }

    /** GET /api/v2/admin/enterprise/roles */
    public function roles(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $roles = array_map(fn($r) => (array)$r, DB::select(
                "SELECT r.id, r.name, r.display_name, r.description, r.is_system, r.level, r.created_at, r.updated_at,
                        (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) as users_count,
                        GROUP_CONCAT(p.name) as permission_names
                 FROM roles r LEFT JOIN role_permissions rp ON r.id = rp.role_id LEFT JOIN permissions p ON rp.permission_id = p.id
                 WHERE r.tenant_id = ? GROUP BY r.id ORDER BY r.name ASC",
                [$tenantId]
            ));

            foreach ($roles as &$role) {
                $role['permissions'] = $role['permission_names'] ? explode(',', $role['permission_names']) : [];
                unset($role['permission_names']);
            }
            unset($role);

            return $this->respondWithData($roles);
        } catch (\Exception $e) {
            return $this->respondWithData([]);
        }
    }

    /** GET /api/v2/admin/enterprise/roles/{id} */
    public function showRole(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        try {
            $result = DB::selectOne(
                "SELECT r.*, (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) as users_count FROM roles r WHERE r.id = ? AND r.tenant_id = ?",
                [$id, $tenantId]
            );

            if (!$result) { return $this->respondWithError('NOT_FOUND', 'Role not found', null, 404); }
            $role = (array)$result;
            $role['permissions'] = json_decode($role['permissions'] ?? '[]', true) ?: [];
            return $this->respondWithData($role);
        } catch (\Exception $e) {
            return $this->respondWithError('NOT_FOUND', 'Role not found or roles table not available', null, 404);
        }
    }

    /** POST /api/v2/admin/enterprise/roles */
    public function createRole(): JsonResponse
    {
        $this->requireAdmin();
        $data = $this->getAllInput();
        $tenantId = TenantContext::getId();

        if (empty($data['name'])) { return $this->respondWithError('VALIDATION_ERROR', 'Role name is required', 'name', 422); }

        $name = trim($data['name']);
        $displayName = $data['display_name'] ?? $name;
        $description = $data['description'] ?? null;
        $level = (int)($data['level'] ?? 10);
        $permissions = $data['permissions'] ?? [];

        try {
            DB::beginTransaction();
            DB::insert("INSERT INTO roles (name, display_name, description, level, tenant_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())", [$name, $displayName, $description, $level, $tenantId]);
            $roleId = DB::getPdo()->lastInsertId();

            if (!empty($permissions)) {
                foreach ($permissions as $permName) {
                    $perm = DB::selectOne("SELECT id FROM permissions WHERE name = ? LIMIT 1", [$permName]);
                    if (!empty($perm)) {
                        DB::insert("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [$roleId, $perm->id]);
                    }
                }
            }
            DB::commit();

            $result = DB::selectOne(
                "SELECT r.*, GROUP_CONCAT(p.name) as permission_names FROM roles r LEFT JOIN role_permissions rp ON r.id = rp.role_id LEFT JOIN permissions p ON rp.permission_id = p.id WHERE r.id = ? AND r.tenant_id = ? GROUP BY r.id",
                [$roleId, $tenantId]
            );
            $resultArr = $result ? (array)$result : [];
            $resultArr['permissions'] = !empty($resultArr['permission_names']) ? explode(',', $resultArr['permission_names']) : [];
            unset($resultArr['permission_names']);

            return $this->respondWithData($resultArr, null, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->respondWithError('CREATE_FAILED', 'Failed to create role', null, 500);
        }
    }

    /** PUT /api/v2/admin/enterprise/roles/{id} */
    public function updateRole(int $id): JsonResponse
    {
        $this->requireAdmin();
        $data = $this->getAllInput();
        $tenantId = TenantContext::getId();

        try {
            DB::beginTransaction();
            $setParts = []; $params = [];
            if (isset($data['name'])) { $setParts[] = 'name = ?'; $params[] = $data['name']; }
            if (isset($data['display_name'])) { $setParts[] = 'display_name = ?'; $params[] = $data['display_name']; }
            if (isset($data['description'])) { $setParts[] = 'description = ?'; $params[] = $data['description']; }
            if (isset($data['level'])) { $setParts[] = 'level = ?'; $params[] = (int)$data['level']; }

            if (!empty($setParts)) {
                $setParts[] = 'updated_at = NOW()'; $params[] = $id; $params[] = $tenantId;
                DB::update("UPDATE roles SET " . implode(', ', $setParts) . " WHERE id = ? AND tenant_id = ?", $params);
            }

            if (isset($data['permissions']) && is_array($data['permissions'])) {
                $roleCheck = DB::selectOne("SELECT id FROM roles WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
                if (!$roleCheck) { DB::rollBack(); return $this->respondWithError('NOT_FOUND', 'Role not found for this tenant', null, 404); }
                DB::delete("DELETE FROM role_permissions WHERE role_id = ? AND tenant_id = ?", [$id, $tenantId]);
                foreach ($data['permissions'] as $permName) {
                    $perm = DB::selectOne("SELECT id FROM permissions WHERE name = ? LIMIT 1", [$permName]);
                    if (!empty($perm)) {
                        DB::insert("INSERT INTO role_permissions (role_id, permission_id, tenant_id) VALUES (?, ?, ?)", [$id, $perm->id, $tenantId]);
                    }
                }
            }
            DB::commit();

            $result = DB::selectOne(
                "SELECT r.*, GROUP_CONCAT(p.name) as permission_names FROM roles r LEFT JOIN role_permissions rp ON r.id = rp.role_id LEFT JOIN permissions p ON rp.permission_id = p.id WHERE r.id = ? AND r.tenant_id = ? GROUP BY r.id",
                [$id, $tenantId]
            );

            if (empty($result)) { return $this->respondWithError('NOT_FOUND', 'Role not found', null, 404); }
            $resultArr = (array)$result;
            $resultArr['permissions'] = !empty($resultArr['permission_names']) ? explode(',', $resultArr['permission_names']) : [];
            unset($resultArr['permission_names']);
            return $this->respondWithData($resultArr);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->respondWithError('UPDATE_FAILED', 'Failed to update role', null, 500);
        }
    }

    /** DELETE /api/v2/admin/enterprise/roles/{id} */
    public function deleteRole(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        try {
            DB::delete("DELETE FROM roles WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            return $this->respondWithData(['deleted' => true]);
        } catch (\Exception $e) {
            return $this->respondWithError('DELETE_FAILED', 'Failed to delete role', null, 500);
        }
    }

    /** GET /api/v2/admin/enterprise/permissions */
    public function permissions(): JsonResponse
    {
        $this->requireAdmin();
        return $this->respondWithData(self::PERMISSIONS);
    }

    /** GET /api/v2/admin/enterprise/gdpr */
    public function gdprDashboard(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $pending = 0; $total = 0;
        try { $row = DB::selectOne("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending FROM gdpr_requests WHERE tenant_id = ?", [$tenantId]); $pending = (int)($row->pending ?? 0); $total = (int)($row->total ?? 0); } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminEnterprise] GDPR dashboard query failed: ' . $e->getMessage()); }

        $consents = 0;
        try { $consents = (int)(DB::selectOne("SELECT COUNT(*) as cnt FROM user_consents WHERE tenant_id = ?", [$tenantId])->cnt ?? 0); } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminEnterprise] Consents count query failed: ' . $e->getMessage()); }

        $breaches = 0;
        try { $breaches = (int)(DB::selectOne("SELECT COUNT(*) as cnt FROM data_breach_log WHERE tenant_id = ?", [$tenantId])->cnt ?? 0); } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminEnterprise] Breaches count query failed: ' . $e->getMessage()); }

        return $this->respondWithData(['total_requests' => $total, 'pending_requests' => $pending, 'total_consents' => $consents, 'total_breaches' => $breaches]);
    }

    /** GET /api/v2/admin/enterprise/gdpr/requests */
    public function gdprRequests(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $status = $this->query('status');
        $offset = ($page - 1) * $perPage;

        try {
            $where = "gr.tenant_id = ?"; $params = [$tenantId];
            if ($status && $status !== 'all') { $where .= " AND gr.status = ?"; $params[] = $status; }

            $total = (int)(DB::selectOne("SELECT COUNT(*) as cnt FROM gdpr_requests gr WHERE $where", $params)->cnt ?? 0);
            $fetchParams = array_merge($params, [$perPage, $offset]);
            $requests = array_map(fn($r) => (array)$r, DB::select(
                "SELECT gr.*, gr.request_type as type, u.name as user_name, u.email as user_email FROM gdpr_requests gr LEFT JOIN users u ON u.id = gr.user_id WHERE $where ORDER BY gr.created_at DESC LIMIT ? OFFSET ?",
                $fetchParams
            ));
            return $this->respondWithPaginatedCollection($requests, $total, $page, $perPage);
        } catch (\Exception $e) {
            return $this->respondWithPaginatedCollection([], 0, 1, $perPage);
        }
    }

    /** PUT /api/v2/admin/enterprise/gdpr/requests/{id} */
    public function updateGdprRequest(int $id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $status = $this->input('status'); $notes = $this->input('notes');
        if (empty($status)) { return $this->respondWithError('VALIDATION_ERROR', 'Status is required', 'status', 422); }

        try {
            $updates = ["status = ?", "updated_at = NOW()"]; $params = [$status];
            if ($notes !== null) { $updates[] = "notes = ?"; $params[] = $notes; }
            if ($status === 'completed') { $updates[] = "completed_at = NOW()"; }
            $params[] = $id; $params[] = $tenantId;
            DB::update("UPDATE gdpr_requests SET " . implode(', ', $updates) . " WHERE id = ? AND tenant_id = ?", $params);
            return $this->respondWithData(['id' => $id, 'status' => $status, 'updated' => true]);
        } catch (\Exception $e) {
            return $this->respondWithError('UPDATE_FAILED', 'Failed to update GDPR request', null, 500);
        }
    }

    /** GET /api/v2/admin/enterprise/gdpr/consents */
    public function gdprConsents(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $page = max(1, (int) request()->query('page', 1));
        $perPage = min(100, max(1, (int) request()->query('per_page', 25)));
        $offset = ($page - 1) * $perPage;

        try {
            $total = (int) (DB::selectOne("SELECT COUNT(*) as cnt FROM user_consents WHERE tenant_id = ?", [$tenantId])->cnt ?? 0);
            $consents = array_map(fn($r) => (array)$r, DB::select("SELECT uc.*, uc.consent_given as consented, uc.given_at as consented_at, u.name as user_name FROM user_consents uc LEFT JOIN users u ON u.id = uc.user_id WHERE uc.tenant_id = ? ORDER BY uc.created_at DESC LIMIT ? OFFSET ?", [$tenantId, $perPage, $offset]));
            return $this->respondWithData(['data' => $consents, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
        } catch (\Exception $e) { return $this->respondWithData(['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage]); }
    }

    /** GET /api/v2/admin/enterprise/gdpr/breaches */
    public function gdprBreaches(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $page = max(1, (int) request()->query('page', 1));
        $perPage = min(100, max(1, (int) request()->query('per_page', 25)));
        $offset = ($page - 1) * $perPage;

        try {
            $total = (int) (DB::selectOne("SELECT COUNT(*) as cnt FROM data_breach_log WHERE tenant_id = ?", [$tenantId])->cnt ?? 0);
            $breaches = array_map(fn($r) => (array)$r, DB::select("SELECT *, breach_type as title, detected_at as reported_at FROM data_breach_log WHERE tenant_id = ? ORDER BY detected_at DESC LIMIT ? OFFSET ?", [$tenantId, $perPage, $offset]));
            return $this->respondWithData(['data' => $breaches, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
        } catch (\Exception $e) { return $this->respondWithData(['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage]); }
    }

    /** POST /api/v2/admin/enterprise/gdpr/breaches */
    public function createBreach(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $input = $this->getAllInput();
        $breachType = trim($input['breach_type'] ?? $input['title'] ?? '');
        if (!$breachType) { return $this->respondWithError('VALIDATION_ERROR', 'Breach type is required', 'breach_type', 422); }

        try {
            DB::insert(
                "INSERT INTO data_breach_log (tenant_id, breach_type, description, severity, status, detected_at, created_by, created_at) VALUES (?, ?, ?, ?, 'open', NOW(), ?, NOW())",
                [$tenantId, $breachType, $input['description'] ?? '', $input['severity'] ?? 'medium', $this->getUserId()]
            );
            return $this->respondWithData(['id' => DB::getPdo()->lastInsertId(), 'message' => 'Breach reported successfully'], null, 201);
        } catch (\Exception $e) {
            return $this->respondWithError('CREATE_FAILED', 'Failed to report breach', null, 500);
        }
    }

    /** GET /api/v2/admin/enterprise/gdpr/audit */
    public function gdprAudit(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $page = max(1, (int) request()->query('page', 1));
        $perPage = min(100, max(1, (int) request()->query('per_page', 25)));
        $offset = ($page - 1) * $perPage;

        try {
            $total = (int) (DB::selectOne("SELECT COUNT(*) as cnt FROM gdpr_audit_log WHERE tenant_id = ?", [$tenantId])->cnt ?? 0);
            $entries = array_map(fn($r) => (array)$r, DB::select(
                "SELECT gal.id, gal.tenant_id, gal.admin_id, gal.action, gal.entity_type, gal.entity_id, gal.old_value, gal.new_value, gal.ip_address, gal.created_at, u.name as user_name FROM gdpr_audit_log gal LEFT JOIN users u ON u.id = gal.admin_id WHERE gal.tenant_id = ? ORDER BY gal.created_at DESC LIMIT ? OFFSET ?",
                [$tenantId, $perPage, $offset]
            ));
            return $this->respondWithData(['data' => $entries, 'total' => $total, 'page' => $page, 'per_page' => $perPage]);
        } catch (\Exception $e) { return $this->respondWithData(['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage]); }
    }

    /** GET /api/v2/admin/enterprise/monitoring */
    public function monitoring(): JsonResponse
    {
        $this->requireAdmin();
        $memUsage = memory_get_usage(true);

        $dbSize = '0 MB';
        try { $dbName = getenv('DB_NAME') ?: 'nexus'; $row = DB::selectOne("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = ?", [$dbName]); $dbSize = ($row->size_mb ?? '0') . ' MB'; } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminEnterprise] DB size query failed: ' . $e->getMessage()); }

        $uptime = 'N/A';
        try { $rows = DB::select("SHOW GLOBAL STATUS LIKE 'Uptime'"); $row = $rows[0] ?? null; if ($row) { $s = (int)($row->Value ?? 0); $uptime = floor($s/86400) . 'd ' . floor(($s%86400)/3600) . 'h'; } } catch (\Exception $e) { \Illuminate\Support\Facades\Log::warning('[AdminEnterprise] Uptime query failed: ' . $e->getMessage()); }

        $redisConnected = false; $redisMemory = 'N/A';
        try { $stats = app(\App\Services\RedisCache::class)->getStats(); $redisConnected = !empty($stats['enabled']); if ($redisConnected) { $redisMemory = $stats['memory_used'] ?? 'N/A'; } } catch (\Throwable $e) {}

        return $this->respondWithData([
            'php_version' => PHP_VERSION, 'memory_usage' => $this->formatBytes($memUsage),
            'memory_limit' => ini_get('memory_limit'), 'db_connected' => true, 'db_size' => $dbSize,
            'redis_connected' => $redisConnected, 'redis_memory' => $redisMemory,
            'uptime' => $uptime, 'server_time' => date('Y-m-d H:i:s T'), 'os' => PHP_OS,
        ]);
    }

    /** GET /api/v2/admin/enterprise/health */
    public function healthCheck(): JsonResponse
    {
        $this->requireAdmin();
        $dbOk = false; try { DB::select("SELECT 1"); $dbOk = true; } catch (\Exception $e) {}
        $redisOk = false; try { $stats = app(\App\Services\RedisCache::class)->getStats(); $redisOk = !empty($stats['enabled']); } catch (\Throwable $e) {}

        $diskFree = 'N/A'; $diskTotal = 'N/A';
        try { $f = disk_free_space('/'); $t = disk_total_space('/'); if ($f !== false && $t !== false) { $diskFree = $this->formatBytes($f); $diskTotal = $this->formatBytes($t); } } catch (\Exception $e) {}

        $checks = [
            ['name' => 'Database', 'status' => $dbOk ? 'ok' : 'fail'],
            ['name' => 'Redis', 'status' => $redisOk ? 'ok' : 'fail'],
            ['name' => 'Disk', 'status' => 'ok', 'free' => $diskFree, 'total' => $diskTotal],
        ];

        foreach (['pdo_mysql','redis','zip','mbstring','gd','curl','json','openssl'] as $ext) {
            $checks[] = ['name' => 'PHP ext: ' . $ext, 'status' => extension_loaded($ext) ? 'ok' : 'fail'];
        }
        $checks[] = ['name' => 'PHP >= 8.2', 'status' => version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'fail'];

        $hasFailures = !empty(array_filter($checks, fn($c) => $c['status'] === 'fail'));

        return $this->respondWithData(['status' => $hasFailures ? 'unhealthy' : 'healthy', 'checks' => $checks]);
    }

    /** GET /api/v2/admin/enterprise/logs */
    public function logs(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 50, 1, 200);
        $offset = ($page - 1) * $perPage;

        try {
            $total = (int)(DB::selectOne("SELECT COUNT(*) as cnt FROM activity_log WHERE tenant_id = ? AND (action LIKE '%error%' OR action LIKE '%fail%' OR action LIKE '%exception%')", [$tenantId])->cnt ?? 0);
            $logs = array_map(fn($r) => (array)$r, DB::select("SELECT al.*, u.name as user_name FROM activity_log al LEFT JOIN users u ON u.id = al.user_id WHERE al.tenant_id = ? AND (al.action LIKE '%error%' OR al.action LIKE '%fail%' OR al.action LIKE '%exception%') ORDER BY al.created_at DESC LIMIT ? OFFSET ?", [$tenantId, $perPage, $offset]));
            return $this->respondWithPaginatedCollection($logs, $total, $page, $perPage);
        } catch (\Exception $e) {
            return $this->respondWithPaginatedCollection([], 0, 1, $perPage);
        }
    }

    /** GET /api/v2/admin/enterprise/config */
    public function config(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        try {
            $row = DB::selectOne("SELECT configuration FROM tenants WHERE id = ?", [$tenantId]);
            return $this->respondWithData(json_decode($row->configuration ?? '{}', true) ?: []);
        } catch (\Exception $e) { return $this->respondWithData([]); }
    }

    /** PUT /api/v2/admin/enterprise/config */
    public function updateConfig(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $newConfig = $this->getAllInput();
        if (empty($newConfig)) { return $this->respondWithError('VALIDATION_ERROR', 'No configuration data provided', null, 422); }

        try {
            $row = DB::selectOne("SELECT configuration FROM tenants WHERE id = ?", [$tenantId]);
            $existing = json_decode($row->configuration ?? '{}', true) ?: [];
            $merged = array_merge($existing, $newConfig);
            DB::update("UPDATE tenants SET configuration = ? WHERE id = ?", [json_encode($merged), $tenantId]);
            try { app(\App\Services\RedisCache::class)->delete('tenant_bootstrap', $tenantId); } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('[AdminEnterprise] Cache invalidation failed: ' . $e->getMessage()); }
            return $this->respondWithData($merged);
        } catch (\Exception $e) {
            return $this->respondWithError('UPDATE_FAILED', 'Failed to update configuration', null, 500);
        }
    }

    /** GET /api/v2/admin/enterprise/secrets */
    public function secrets(): JsonResponse
    {
        $this->requireAdmin();
        $secretKeys = ['DB_HOST','DB_NAME','DB_USER','DB_PASS','PUSHER_APP_ID','PUSHER_KEY','PUSHER_SECRET','PUSHER_CLUSTER','OPENAI_API_KEY','GMAIL_CLIENT_ID','GMAIL_CLIENT_SECRET','GMAIL_REFRESH_TOKEN','JWT_SECRET','REDIS_HOST','REDIS_PORT','REDIS_PASSWORD','FCM_SERVER_KEY','VAPID_PUBLIC_KEY','VAPID_PRIVATE_KEY'];
        $secrets = [];
        foreach ($secretKeys as $key) {
            $value = getenv($key);
            $secrets[] = ['key' => $key, 'is_set' => $value !== false && $value !== ''];
        }
        return $this->respondWithData($secrets);
    }

    /** GET /api/v2/admin/legal-documents */
    public function legalDocs(): JsonResponse
    {
        $this->requireAdmin();
        try {
            $docs = $this->legalDocumentService->getAllForTenant(TenantContext::getId());
            return $this->respondWithData($docs);
        } catch (\Exception $e) { return $this->respondWithData([]); }
    }

    /** POST /api/v2/admin/legal-documents */
    public function createLegalDoc(): JsonResponse
    {
        $this->requireAdmin();
        $data = $this->getAllInput();
        if (empty($data['title'])) { return $this->respondWithError('VALIDATION_ERROR', 'Title is required', 'title', 422); }

        try {
            $doc = $this->legalDocumentService->createDocument([
                'title' => $data['title'], 'document_type' => $data['type'] ?? $data['document_type'] ?? 'terms',
                'slug' => $data['slug'] ?? null, 'requires_acceptance' => $data['requires_acceptance'] ?? true,
                'acceptance_required_for' => $data['acceptance_required_for'] ?? 'registration',
                'notify_on_update' => $data['notify_on_update'] ?? false,
                'is_active' => $data['is_active'] ?? true, 'created_by' => $this->getUserId(),
            ]);
            return $this->respondWithData($doc, null, 201);
        } catch (\Exception $e) {
            return $this->respondWithError('CREATE_FAILED', 'Failed to create legal document', null, 500);
        }
    }

    /** GET /api/v2/admin/legal-documents/{id} */
    public function showLegalDoc($id): JsonResponse
    {
        $this->requireAdmin();
        $id = (int) $id;
        $tenantId = $this->getTenantId();
        try {
            $result = DB::selectOne("SELECT *, document_type as type FROM legal_documents WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            if (!$result) { return $this->respondWithError('NOT_FOUND', 'Legal document not found', null, 404); }
            $doc = (array)$result;
            return $this->respondWithData($doc);
        } catch (\Exception $e) {
            return $this->respondWithError('NOT_FOUND', 'Legal document not found', null, 404);
        }
    }

    /** PUT /api/v2/admin/legal-documents/{id} */
    public function updateLegalDoc($id): JsonResponse
    {
        $this->requireAdmin();
        $data = $this->getAllInput();
        $id = (int) $id;

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

            $doc = $this->legalDocumentService->updateDocument($id, $updateData);
            if (!$doc) { return $this->respondWithError('NOT_FOUND', 'Document not found', null, 404); }
            return $this->respondWithData($doc);
        } catch (\Exception $e) {
            return $this->respondWithError('UPDATE_FAILED', 'Failed to update legal document', null, 500);
        }
    }

    /** DELETE /api/v2/admin/legal-documents/{id} */
    public function deleteLegalDoc($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $id = (int) $id;
        try {
            DB::delete("DELETE FROM legal_documents WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
            return $this->respondWithData(['deleted' => true]);
        } catch (\Exception $e) {
            return $this->respondWithError('DELETE_FAILED', 'Failed to delete legal document', null, 500);
        }
    }

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
