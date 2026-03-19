<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\FederationDirectoryService;
use App\Services\FederationPartnershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * AdminFederationController -- Federation management.
 *
 * Converted from legacy delegation to direct DB/service calls.
 * CSV export methods remain as delegation (write to php://output).
 */
class AdminFederationController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly FederationDirectoryService $federationDirectoryService,
        private readonly FederationPartnershipService $federationPartnershipService,
    ) {}

    private const ALLOWED_TABLES = [
        'federation_partnerships', 'federation_transactions', 'federation_messages',
        'federation_api_keys', 'federation_user_settings', 'federation_audit_log',
    ];

    private function tableExists(string $table): bool
    {
        if (!in_array($table, self::ALLOWED_TABLES, true)) { return false; }
        try { DB::select("SELECT 1 FROM `{$table}` LIMIT 1"); return true; } catch (\Exception $e) { return false; }
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
            $row = DB::selectOne("SELECT configuration FROM tenants WHERE id = ?", [$tenantId]);
            if ($row && !empty($row->configuration)) {
                $config = json_decode($row->configuration, true);
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
            $row = DB::selectOne("SELECT configuration FROM tenants WHERE id = ?", [$tenantId]);
            $config = json_decode($row->configuration ?? '{}', true) ?: [];

            $federationSettings = $config['federation'] ?? [];
            if (isset($input['settings']) && is_array($input['settings'])) {
                $federationSettings = array_merge($federationSettings, $input['settings']);
            }
            if (isset($input['federation_enabled'])) {
                $federationSettings['federation_enabled'] = $input['federation_enabled'];
            }
            $config['federation'] = $federationSettings;

            DB::update("UPDATE tenants SET configuration = ? WHERE id = ?", [json_encode($config), $tenantId]);
            try { \App\Services\RedisCache::delete('tenant_bootstrap', $tenantId); } catch (\Exception $e) {}

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
            $items = DB::select(
                "SELECT fp.*, t.name as partner_name, t.slug as partner_slug FROM federation_partnerships fp LEFT JOIN tenants t ON (fp.partner_tenant_id = t.id) WHERE fp.tenant_id = ? OR fp.partner_tenant_id = ? ORDER BY fp.created_at DESC",
                [$tenantId, $tenantId]
            );
            return $this->respondWithData(array_map(fn($r) => (array)$r, $items) ?: []);
        } catch (\Exception $e) { return $this->respondWithData([]); }
    }

    public function approvePartnership($id): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = (int) $id;

        if (!$id || !$this->tableExists('federation_partnerships')) { return $this->respondWithError('NOT_FOUND', 'Partnership not found', null, 404); }

        try {
            $partner = DB::selectOne("SELECT * FROM federation_partnerships WHERE id = ? AND (tenant_id = ? OR partner_tenant_id = ?)", [$id, $tenantId, $tenantId]);
            if (!$partner) { return $this->respondWithError('NOT_FOUND', 'Partnership not found', null, 404); }
            DB::update("UPDATE federation_partnerships SET status = 'active', updated_at = NOW() WHERE id = ? AND (tenant_id = ? OR partner_tenant_id = ?)", [$id, $tenantId, $tenantId]);
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
            $partner = DB::selectOne("SELECT * FROM federation_partnerships WHERE id = ? AND (tenant_id = ? OR partner_tenant_id = ?)", [$id, $tenantId, $tenantId]);
            if (!$partner) { return $this->respondWithError('NOT_FOUND', 'Partnership not found', null, 404); }
            DB::update("UPDATE federation_partnerships SET status = 'rejected', updated_at = NOW() WHERE id = ? AND (tenant_id = ? OR partner_tenant_id = ?)", [$id, $tenantId, $tenantId]);
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
            $partner = DB::selectOne("SELECT * FROM federation_partnerships WHERE id = ? AND (tenant_id = ? OR partner_tenant_id = ?)", [$id, $tenantId, $tenantId]);
            if (!$partner) { return $this->respondWithError('NOT_FOUND', 'Partnership not found', null, 404); }
            DB::update("UPDATE federation_partnerships SET status = 'terminated', updated_at = NOW() WHERE id = ? AND (tenant_id = ? OR partner_tenant_id = ?)", [$id, $tenantId, $tenantId]);
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
            $result = $this->federationPartnershipService->requestPartnership($tenantId, $targetTenantId, $userId, FederationPartnershipService::LEVEL_DISCOVERY, $notes);
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
            $communities = $this->federationDirectoryService->getDiscoverableTimebanks($tenantId, $filters);
            $regions = $this->federationDirectoryService->getAvailableRegions();
            $categories = $this->federationDirectoryService->getAvailableCategories();
            return $this->respondWithData(['communities' => $communities, 'regions' => $regions, 'categories' => $categories]);
        } catch (\Exception $e) {
            try {
                $fallback = DB::select(
                    "SELECT t.id, t.name, t.slug, t.is_active, t.created_at, (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.status = 'active') as member_count FROM tenants t WHERE t.is_active = 1 AND t.id != ? ORDER BY t.name ASC LIMIT 100",
                    [$tenantId]
                );
                return $this->respondWithData(['communities' => array_map(fn($r) => (array)$r, $fallback) ?: [], 'regions' => [], 'categories' => []]);
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
            $tenantRow = DB::selectOne("SELECT t.id, t.name, t.slug, t.is_active, t.configuration, t.created_at FROM tenants t WHERE t.id = ?", [$tenantId]);
            if ($tenantRow) {
                $tenant = (array)$tenantRow;
                $config = json_decode($tenant['configuration'] ?? '{}', true);
                $tenant['federation_profile'] = $config['federation_profile'] ?? ['description' => '', 'contact_email' => '', 'website' => '', 'categories' => []];
                unset($tenant['configuration']);
            } else {
                $tenant = [];
            }
            return $this->respondWithData($tenant);
        } catch (\Exception $e) { return $this->respondWithData([]); }
    }

    public function updateProfile(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $input = $this->getAllInput();

        try {
            $row = DB::selectOne("SELECT configuration FROM tenants WHERE id = ?", [$tenantId]);
            $config = json_decode($row->configuration ?? '{}', true) ?: [];
            $config['federation_profile'] = array_merge($config['federation_profile'] ?? [], $input);
            DB::update("UPDATE tenants SET configuration = ? WHERE id = ?", [json_encode($config), $tenantId]);
            try { \App\Services\RedisCache::delete('tenant_bootstrap', $tenantId); } catch (\Exception $e) {}
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
            try { $row = DB::selectOne("SELECT COUNT(*) as total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_count, SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending FROM federation_partnerships WHERE tenant_id = ? OR partner_tenant_id = ?", [$tenantId, $tenantId]); $data['total_partnerships'] = (int)($row->total ?? 0); $data['active_partnerships'] = (int)($row->active_count ?? 0); $data['pending_requests'] = (int)($row->pending ?? 0); } catch (\Exception $e) {}
        }
        if ($this->tableExists('federation_transactions')) {
            try { $row = DB::selectOne("SELECT COUNT(*) as total FROM federation_transactions WHERE sender_tenant_id = ? OR receiver_tenant_id = ?", [$tenantId, $tenantId]); $data['cross_community_transactions'] = (int)($row->total ?? 0); } catch (\Exception $e) {}
        }
        if ($this->tableExists('federation_messages')) {
            try { $row = DB::selectOne("SELECT COUNT(*) as total FROM federation_messages WHERE sender_tenant_id = ? OR receiver_tenant_id = ?", [$tenantId, $tenantId]); $data['cross_community_messages'] = (int)($row->total ?? 0); } catch (\Exception $e) {}
        }
        return $this->respondWithData($data);
    }

    public function apiKeys(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        if (!$this->tableExists('federation_api_keys')) { return $this->respondWithData([]); }

        try {
            $keyRows = DB::select("SELECT id, name, key_prefix, status, permissions, last_used_at, expires_at, created_at FROM federation_api_keys WHERE tenant_id = ? ORDER BY created_at DESC", [$tenantId]);
            $keys = array_map(function($k) { $arr = (array)$k; $arr['scopes'] = !empty($arr['permissions']) ? (json_decode($arr['permissions'], true) ?: []) : []; unset($arr['permissions']); return $arr; }, $keyRows);
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
            DB::insert("INSERT INTO federation_api_keys (tenant_id, name, key_hash, key_prefix, permissions, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, 'active', ?, NOW())", [$tenantId, $name, hash('sha256', $keyValue), $prefix, json_encode($scopes), $this->getUserId()]);
            return $this->respondWithData(['id' => DB::getPdo()->lastInsertId(), 'name' => $name, 'api_key' => $keyValue, 'key_prefix' => $prefix, 'message' => 'Store this key securely. It will not be shown again.'], null, 201);
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

    /** GET /api/v2/admin/federation/export/{type} */
    public function exportData($type)
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $type = (string) $type;

        $allowedTypes = ['users', 'partnerships', 'transactions', 'audit'];
        if (!in_array($type, $allowedTypes, true)) {
            return $this->respondWithError('INVALID_TYPE', 'Invalid export type. Allowed: ' . implode(', ', $allowedTypes), null, 400);
        }

        try {
            $rows = [];
            $headers = [];

            switch ($type) {
                case 'users':
                    if (!$this->tableExists('federation_user_settings')) {
                        return $this->respondWithError('NO_DATA', 'Federation user settings table not found', null, 404);
                    }
                    $rows = array_map(fn($r) => (array)$r, DB::select("
                        SELECT u.id, u.first_name, u.last_name, u.email, u.username,
                               fus.federation_optin, fus.privacy_level, fus.service_reach,
                               fus.created_at, fus.updated_at
                        FROM federation_user_settings fus
                        JOIN users u ON u.id = fus.user_id
                        WHERE u.tenant_id = ? AND fus.federation_optin = 1
                        ORDER BY u.first_name, u.last_name
                    ", [$tenantId]));
                    $headers = ['ID', 'First Name', 'Last Name', 'Email', 'Username', 'Opted In', 'Privacy Level', 'Service Reach', 'Created', 'Updated'];
                    break;

                case 'partnerships':
                    if (!$this->tableExists('federation_partnerships')) {
                        return $this->respondWithError('NO_DATA', 'Federation partnerships table not found', null, 404);
                    }
                    $rows = array_map(fn($r) => (array)$r, DB::select("
                        SELECT fp.id, t1.name AS tenant_name, t2.name AS partner_name,
                               fp.status, fp.level, fp.created_at, fp.updated_at
                        FROM federation_partnerships fp
                        LEFT JOIN tenants t1 ON t1.id = fp.tenant_id
                        LEFT JOIN tenants t2 ON t2.id = fp.partner_tenant_id
                        WHERE fp.tenant_id = ? OR fp.partner_tenant_id = ?
                        ORDER BY fp.created_at DESC
                    ", [$tenantId, $tenantId]));
                    $headers = ['ID', 'Tenant', 'Partner', 'Status', 'Level', 'Created', 'Updated'];
                    break;

                case 'transactions':
                    if (!$this->tableExists('federation_transactions')) {
                        return $this->respondWithError('NO_DATA', 'Federation transactions table not found', null, 404);
                    }
                    $rows = array_map(fn($r) => (array)$r, DB::select("
                        SELECT ft.id, ft.sender_user_id, ft.receiver_user_id,
                               ft.amount, ft.description, ft.status,
                               ft.created_at, ft.completed_at
                        FROM federation_transactions ft
                        WHERE ft.sender_tenant_id = ? OR ft.receiver_tenant_id = ?
                        ORDER BY ft.created_at DESC
                    ", [$tenantId, $tenantId]));
                    $headers = ['ID', 'Sender ID', 'Receiver ID', 'Amount', 'Description', 'Status', 'Created', 'Completed'];
                    break;

                case 'audit':
                    if (!$this->tableExists('federation_audit_log')) {
                        return $this->respondWithError('NO_DATA', 'Federation audit log table not found', null, 404);
                    }
                    $rows = array_map(fn($r) => (array)$r, DB::select("
                        SELECT id, action, category, level, actor_user_id,
                               source_tenant_id, target_tenant_id, details, created_at
                        FROM federation_audit_log
                        WHERE source_tenant_id = ? OR target_tenant_id = ?
                        ORDER BY created_at DESC LIMIT 5000
                    ", [$tenantId, $tenantId]));
                    $headers = ['ID', 'Action', 'Category', 'Level', 'Actor ID', 'Source Tenant', 'Target Tenant', 'Details', 'Created'];
                    break;
            }

            $filename = "federation_{$type}_" . date('Y-m-d_His') . '.csv';

            return new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($headers, $rows) {
                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF"); // BOM for Excel UTF-8 compatibility
                fputcsv($output, $headers);
                foreach ($rows as $row) {
                    fputcsv($output, array_values($row));
                }
                fclose($output);
            }, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ]);

        } catch (\Throwable $e) {
            return $this->respondWithError('EXPORT_FAILED', 'Failed to export data', null, 500);
        }
    }
}
