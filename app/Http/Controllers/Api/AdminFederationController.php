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
use Nexus\Services\FederationDirectoryService;
use Nexus\Services\FederationPartnershipService;

/**
 * AdminFederationController -- Federation management.
 *
 * Converted from legacy delegation to direct DB/service calls.
 * CSV export methods remain as delegation (write to php://output).
 */
class AdminFederationController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    private const ALLOWED_TABLES = [
        'federation_partnerships', 'federation_transactions', 'federation_messages',
        'federation_api_keys', 'federation_user_settings', 'federation_audit_log',
    ];

    private function tableExists(string $table): bool
    {
        if (!in_array($table, self::ALLOWED_TABLES, true)) { return false; }
        try { Database::query("SELECT 1 FROM `{$table}` LIMIT 1"); return true; } catch (\Exception $e) { return false; }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Already converted methods
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /api/v2/admin/federation */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $features = DB::select('SELECT * FROM federation_tenant_features WHERE tenant_id = ?', [$tenantId]);
        $whitelist = DB::select('SELECT * FROM federation_tenant_whitelist WHERE tenant_id = ?', [$tenantId]);
        return $this->respondWithData(['features' => $features, 'whitelist' => $whitelist]);
    }

    /** GET /api/v2/admin/federation/timebanks */
    public function timebanks(): JsonResponse
    {
        $this->requireAdmin();
        $timebanks = DB::select('SELECT id, name, slug, domain FROM tenants WHERE status = ? ORDER BY name', ['active']);
        return $this->respondWithData($timebanks);
    }

    /** GET /api/v2/admin/federation/controls */
    public function controls(): JsonResponse
    {
        $this->requireAdmin();
        $controls = DB::select('SELECT * FROM federation_system_control ORDER BY control_key');
        $result = [];
        foreach ($controls as $c) { $result[$c->control_key] = $c->control_value; }
        return $this->respondWithData($result);
    }

    /** PUT /api/v2/admin/federation/controls */
    public function updateControls(): JsonResponse
    {
        $this->requireSuperAdmin();
        $data = $this->getAllInput();
        $updated = 0;
        foreach ($data as $key => $value) {
            $updated += DB::update('UPDATE federation_system_control SET control_value = ? WHERE control_key = ?', [$value, $key]);
        }
        return $this->respondWithData(['updated' => $updated]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Settings
    // ─────────────────────────────────────────────────────────────────────────

    public function settings(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $data = [
            'federation_enabled' => TenantContext::hasFeature('federation'),
            'tenant_id' => $tenantId,
            'settings' => ['allow_inbound_partnerships' => true, 'auto_approve_partners' => false, 'shared_categories' => [], 'max_partnerships' => 10],
        ];

        try {
            $row = Database::query("SELECT configuration FROM tenants WHERE id = ?", [$tenantId])->fetch();
            if ($row && !empty($row['configuration'])) {
                $config = json_decode($row['configuration'], true);
                if (isset($config['federation']) && is_array($config['federation'])) {
                    $fc = $config['federation'];
                    if (isset($fc['federation_enabled'])) { $data['federation_enabled'] = (bool)$fc['federation_enabled']; }
                    $data['settings'] = array_merge($data['settings'], array_diff_key($fc, ['federation_enabled' => '']));
                }
            }
        } catch (\Exception $e) {}

        return $this->respondWithData($data);
    }

    public function updateSettings(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        try {
            $row = Database::query("SELECT configuration FROM tenants WHERE id = ?", [$tenantId])->fetch();
            $config = json_decode($row['configuration'] ?? '{}', true) ?: [];

            $federationSettings = $config['federation'] ?? [];
            if (isset($input['settings']) && is_array($input['settings'])) {
                $federationSettings = array_merge($federationSettings, $input['settings']);
            }
            if (isset($input['federation_enabled'])) {
                $federationSettings['federation_enabled'] = $input['federation_enabled'];
            }
            $config['federation'] = $federationSettings;

            Database::query("UPDATE tenants SET configuration = ? WHERE id = ?", [json_encode($config), $tenantId]);
            try { \Nexus\Services\RedisCache::delete('tenant_bootstrap', $tenantId); } catch (\Exception $e) {}

            return $this->respondWithData([
                'federation_enabled' => $federationSettings['federation_enabled'] ?? false,
                'tenant_id' => $tenantId,
                'settings' => array_diff_key($federationSettings, ['federation_enabled' => '']),
            ]);
        } catch (\Exception $e) {
            return $this->respondWithError('UPDATE_FAILED', 'Failed to update federation settings', null, 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Partnerships
    // ─────────────────────────────────────────────────────────────────────────

    public function partnerships(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('federation_partnerships')) { return $this->respondWithData([]); }

        try {
            $items = Database::query(
                "SELECT fp.*, t.name as partner_name, t.slug as partner_slug FROM federation_partnerships fp LEFT JOIN tenants t ON (fp.partner_tenant_id = t.id) WHERE fp.tenant_id = ? OR fp.partner_tenant_id = ? ORDER BY fp.created_at DESC",
                [$tenantId, $tenantId]
            )->fetchAll();
            return $this->respondWithData($items ?: []);
        } catch (\Exception $e) { return $this->respondWithData([]); }
    }

    public function approvePartnership($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = (int) $id;

        if (!$id || !$this->tableExists('federation_partnerships')) { return $this->respondWithError('NOT_FOUND', 'Partnership not found', null, 404); }

        try {
            $partner = Database::query("SELECT * FROM federation_partnerships WHERE id = ? AND (tenant_id = ? OR partner_tenant_id = ?)", [$id, $tenantId, $tenantId])->fetch();
            if (!$partner) { return $this->respondWithError('NOT_FOUND', 'Partnership not found', null, 404); }
            Database::query("UPDATE federation_partnerships SET status = 'active', updated_at = NOW() WHERE id = ?", [$id]);
            return $this->respondWithData(['message' => 'Partnership approved']);
        } catch (\Exception $e) { return $this->respondWithError('UPDATE_FAILED', 'Failed to approve partnership'); }
    }

    public function rejectPartnership($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = (int) $id;

        if (!$id || !$this->tableExists('federation_partnerships')) { return $this->respondWithError('NOT_FOUND', 'Partnership not found', null, 404); }

        try {
            $partner = Database::query("SELECT * FROM federation_partnerships WHERE id = ? AND (tenant_id = ? OR partner_tenant_id = ?)", [$id, $tenantId, $tenantId])->fetch();
            if (!$partner) { return $this->respondWithError('NOT_FOUND', 'Partnership not found', null, 404); }
            Database::query("UPDATE federation_partnerships SET status = 'rejected', updated_at = NOW() WHERE id = ?", [$id]);
            return $this->respondWithData(['message' => 'Partnership rejected']);
        } catch (\Exception $e) { return $this->respondWithError('UPDATE_FAILED', 'Failed to reject partnership'); }
    }

    public function terminatePartnership($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = (int) $id;

        if (!$id || !$this->tableExists('federation_partnerships')) { return $this->respondWithError('NOT_FOUND', 'Partnership not found', null, 404); }

        try {
            $partner = Database::query("SELECT * FROM federation_partnerships WHERE id = ? AND (tenant_id = ? OR partner_tenant_id = ?)", [$id, $tenantId, $tenantId])->fetch();
            if (!$partner) { return $this->respondWithError('NOT_FOUND', 'Partnership not found', null, 404); }
            Database::query("UPDATE federation_partnerships SET status = 'terminated', updated_at = NOW() WHERE id = ?", [$id]);
            return $this->respondWithData(['message' => 'Partnership terminated']);
        } catch (\Exception $e) { return $this->respondWithError('UPDATE_FAILED', 'Failed to terminate partnership'); }
    }

    public function requestPartnership(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $userId = $this->getUserId();
        $input = $this->getAllInput();
        $targetTenantId = (int)($input['target_tenant_id'] ?? 0);
        $notes = isset($input['notes']) ? substr(trim($input['notes']), 0, 1000) : null;

        if ($targetTenantId <= 0) { return $this->respondWithError('VALIDATION_ERROR', 'Target community ID is required', 'target_tenant_id'); }
        if ($targetTenantId === $tenantId) { return $this->respondWithError('VALIDATION_ERROR', 'Cannot partner with your own community'); }

        try {
            $result = FederationPartnershipService::requestPartnership($tenantId, $targetTenantId, $userId, FederationPartnershipService::LEVEL_DISCOVERY, $notes);
            if ($result['success']) { return $this->respondWithData($result, null, 201); }
            return $this->respondWithError('REQUEST_FAILED', $result['error'] ?? 'Failed to send partnership request');
        } catch (\Exception $e) { return $this->respondWithError('REQUEST_FAILED', 'Failed to send partnership request'); }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Directory & Profile
    // ─────────────────────────────────────────────────────────────────────────

    public function directory(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $filters = [];
        $search = $this->query('search'); if ($search) { $filters['search'] = substr(trim($search), 0, 200); }
        $region = $this->query('region'); if ($region) { $filters['region'] = substr(trim($region), 0, 100); }
        $category = $this->query('category'); if ($category) { $filters['category'] = substr(trim($category), 0, 100); }
        if ($this->queryBool('exclude_partnered')) { $filters['exclude_partnered'] = true; }

        try {
            $communities = FederationDirectoryService::getDiscoverableTimebanks($tenantId, $filters);
            $regions = FederationDirectoryService::getAvailableRegions();
            $categories = FederationDirectoryService::getAvailableCategories();
            return $this->respondWithData(['communities' => $communities, 'regions' => $regions, 'categories' => $categories]);
        } catch (\Exception $e) {
            try {
                $fallback = Database::query(
                    "SELECT t.id, t.name, t.slug, t.is_active, t.created_at, (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.status = 'active') as member_count FROM tenants t WHERE t.is_active = 1 AND t.id != ? ORDER BY t.name ASC LIMIT 100",
                    [$tenantId]
                )->fetchAll();
                return $this->respondWithData(['communities' => $fallback ?: [], 'regions' => [], 'categories' => []]);
            } catch (\Exception $e2) {
                return $this->respondWithData(['communities' => [], 'regions' => [], 'categories' => []]);
            }
        }
    }

    public function profile(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        try {
            $tenant = Database::query("SELECT t.id, t.name, t.slug, t.is_active, t.configuration, t.created_at FROM tenants t WHERE t.id = ?", [$tenantId])->fetch();
            if ($tenant) {
                $config = json_decode($tenant['configuration'] ?? '{}', true);
                $tenant['federation_profile'] = $config['federation_profile'] ?? ['description' => '', 'contact_email' => '', 'website' => '', 'categories' => []];
                unset($tenant['configuration']);
            }
            return $this->respondWithData($tenant ?: []);
        } catch (\Exception $e) { return $this->respondWithData([]); }
    }

    public function updateProfile(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        try {
            $row = Database::query("SELECT configuration FROM tenants WHERE id = ?", [$tenantId])->fetch();
            $config = json_decode($row['configuration'] ?? '{}', true) ?: [];
            $config['federation_profile'] = array_merge($config['federation_profile'] ?? [], $input);
            Database::query("UPDATE tenants SET configuration = ? WHERE id = ?", [json_encode($config), $tenantId]);
            try { \Nexus\Services\RedisCache::delete('tenant_bootstrap', $tenantId); } catch (\Exception $e) {}
            return $this->respondWithData($config['federation_profile']);
        } catch (\Exception $e) {
            return $this->respondWithError('UPDATE_FAILED', 'Failed to update federation profile', null, 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Analytics, API Keys, Data Management
    // ─────────────────────────────────────────────────────────────────────────

    public function analytics(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $data = ['total_partnerships' => 0, 'active_partnerships' => 0, 'pending_requests' => 0, 'cross_community_transactions' => 0, 'cross_community_messages' => 0];

        if ($this->tableExists('federation_partnerships')) {
            try { $row = Database::query("SELECT COUNT(*) as total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_count, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending FROM federation_partnerships WHERE tenant_id = ? OR partner_tenant_id = ?", [$tenantId, $tenantId])->fetch(); $data['total_partnerships'] = (int)($row['total'] ?? 0); $data['active_partnerships'] = (int)($row['active_count'] ?? 0); $data['pending_requests'] = (int)($row['pending'] ?? 0); } catch (\Exception $e) {}
        }
        if ($this->tableExists('federation_transactions')) {
            try { $row = Database::query("SELECT COUNT(*) as total FROM federation_transactions WHERE sender_tenant_id = ? OR receiver_tenant_id = ?", [$tenantId, $tenantId])->fetch(); $data['cross_community_transactions'] = (int)($row['total'] ?? 0); } catch (\Exception $e) {}
        }
        if ($this->tableExists('federation_messages')) {
            try { $row = Database::query("SELECT COUNT(*) as total FROM federation_messages WHERE sender_tenant_id = ? OR receiver_tenant_id = ?", [$tenantId, $tenantId])->fetch(); $data['cross_community_messages'] = (int)($row['total'] ?? 0); } catch (\Exception $e) {}
        }
        return $this->respondWithData($data);
    }

    public function apiKeys(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        if (!$this->tableExists('federation_api_keys')) { return $this->respondWithData([]); }

        try {
            $keys = Database::query("SELECT id, name, key_prefix, status, permissions, last_used_at, expires_at, created_at FROM federation_api_keys WHERE tenant_id = ? ORDER BY created_at DESC", [$tenantId])->fetchAll() ?: [];
            foreach ($keys as &$key) { $key['scopes'] = !empty($key['permissions']) ? (json_decode($key['permissions'], true) ?: []) : []; unset($key['permissions']); }
            unset($key);
            return $this->respondWithData($keys);
        } catch (\Exception $e) { return $this->respondWithData([]); }
    }

    public function createApiKey(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $name = $this->input('name');
        $scopes = $this->input('scopes', []);
        if (!$name) { return $this->respondWithError('VALIDATION_ERROR', 'Name is required', 'name'); }
        if (!$this->tableExists('federation_api_keys')) { return $this->respondWithError('TABLE_MISSING', 'Federation API keys table not configured', null, 503); }

        try {
            $keyValue = bin2hex(random_bytes(32));
            $prefix = substr($keyValue, 0, 8);
            Database::query("INSERT INTO federation_api_keys (tenant_id, name, key_hash, key_prefix, permissions, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, 'active', ?, NOW())", [$tenantId, $name, hash('sha256', $keyValue), $prefix, json_encode($scopes), $this->getUserId()]);
            return $this->respondWithData(['id' => Database::lastInsertId(), 'name' => $name, 'api_key' => $keyValue, 'key_prefix' => $prefix, 'message' => 'Store this key securely. It will not be shown again.'], null, 201);
        } catch (\Exception $e) { return $this->respondWithError('CREATE_FAILED', 'Failed to create API key'); }
    }

    public function dataManagement(): JsonResponse
    {
        $this->requireAdmin();
        return $this->respondWithData([
            'export_formats' => ['json', 'csv'],
            'available_exports' => ['users' => 'Member directory', 'partnerships' => 'Partnership records', 'transactions' => 'Cross-community transactions', 'audit' => 'Audit log'],
            'import_supported' => true, 'last_export_at' => null, 'last_import_at' => null,
        ]);
    }

    /** GET /api/v2/admin/federation/export/{type} -- delegation (CSV download via php://output + exit) */
    public function exportData($type): JsonResponse
    {
        return $this->delegateLegacy(\Nexus\Controllers\Api\AdminFederationApiController::class, 'exportData', [(string)$type]);
    }

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
