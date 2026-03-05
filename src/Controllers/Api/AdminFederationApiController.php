<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Admin Federation API Controller
 * Provides settings, partnerships, directory, profile, analytics, API keys, and data management.
 * Gracefully returns empty data if tables don't exist.
 */
class AdminFederationApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    private function tableExists(string $table): bool
    {
        try {
            Database::query("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function settings(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $data = [
            'federation_enabled' => TenantContext::hasFeature('federation'),
            'tenant_id' => $tenantId,
            'settings' => [
                'allow_inbound_partnerships' => true,
                'auto_approve_partners' => false,
                'shared_categories' => [],
                'max_partnerships' => 10,
            ],
        ];

        try {
            $stmt = Database::query(
                "SELECT configuration FROM tenants WHERE id = ?",
                [$tenantId]
            );
            $row = $stmt->fetch();
            if ($row && !empty($row['configuration'])) {
                $config = json_decode($row['configuration'], true);
                if (isset($config['federation']) && is_array($config['federation'])) {
                    // Separate federation_enabled from other settings
                    $federationConfig = $config['federation'];
                    if (isset($federationConfig['federation_enabled'])) {
                        $data['federation_enabled'] = (bool) $federationConfig['federation_enabled'];
                    }
                    // Merge all other settings (excluding federation_enabled)
                    $otherSettings = array_diff_key($federationConfig, ['federation_enabled' => '']);
                    $data['settings'] = array_merge($data['settings'], $otherSettings);
                }
            }
        } catch (\Exception $e) {}

        $this->respondWithData($data);
    }

    /**
     * PUT /api/v2/admin/federation/settings
     * Update federation settings for the current tenant.
     */
    public function updateSettings(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $input = $this->getAllInput();

        try {
            $stmt = Database::query(
                "SELECT configuration FROM tenants WHERE id = ?",
                [$tenantId]
            );
            $row = $stmt->fetch();
            $config = json_decode($row['configuration'] ?? '{}', true) ?: [];

            // Flatten the input structure - merge settings array into federation config
            $federationSettings = $config['federation'] ?? [];
            if (isset($input['settings']) && is_array($input['settings'])) {
                // Merge the nested settings into the flat federation config
                $federationSettings = array_merge($federationSettings, $input['settings']);
            }
            if (isset($input['federation_enabled'])) {
                $federationSettings['federation_enabled'] = $input['federation_enabled'];
            }

            $config['federation'] = $federationSettings;

            Database::query(
                "UPDATE tenants SET configuration = ? WHERE id = ?",
                [json_encode($config), $tenantId]
            );

            // Clear Redis cache if available
            try {
                \Nexus\Services\RedisCache::delete('tenant_bootstrap', $tenantId);
            } catch (\Exception $e) {
                // Redis may not be available
            }

            $this->respondWithData([
                'federation_enabled' => $federationSettings['federation_enabled'] ?? false,
                'tenant_id' => $tenantId,
                'settings' => array_diff_key($federationSettings, ['federation_enabled' => '']),
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to update federation settings: ' . $e->getMessage(), null, 500);
        }
    }

    public function partnerships(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('federation_partnerships')) {
            $this->respondWithData([]);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT fp.*, t.name as partner_name, t.slug as partner_slug
                 FROM federation_partnerships fp
                 LEFT JOIN tenants t ON (fp.partner_tenant_id = t.id)
                 WHERE fp.tenant_id = ? OR fp.partner_tenant_id = ?
                 ORDER BY fp.created_at DESC",
                [$tenantId, $tenantId]
            );
            $this->respondWithData($stmt->fetchAll() ?: []);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    public function approvePartnership(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = $this->getRouteParam('id');

        if (!$id || !$this->tableExists('federation_partnerships')) {
            $this->respondWithError('NOT_FOUND', 'Partnership not found', null, 404);
            return;
        }

        try {
            $partner = Database::query(
                "SELECT * FROM federation_partnerships WHERE id = ? AND (tenant_id = ? OR partner_tenant_id = ?)",
                [$id, $tenantId, $tenantId]
            )->fetch();

            if (!$partner) {
                $this->respondWithError('NOT_FOUND', 'Partnership not found', null, 404);
                return;
            }

            Database::query(
                "UPDATE federation_partnerships SET status = 'active', updated_at = NOW() WHERE id = ?",
                [$id]
            );

            $this->respondWithData(['message' => 'Partnership approved']);
        } catch (\Exception $e) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to approve partnership');
        }
    }

    public function rejectPartnership(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = $this->getRouteParam('id');

        if (!$id || !$this->tableExists('federation_partnerships')) {
            $this->respondWithError('NOT_FOUND', 'Partnership not found', null, 404);
            return;
        }

        try {
            $partner = Database::query(
                "SELECT * FROM federation_partnerships WHERE id = ? AND (tenant_id = ? OR partner_tenant_id = ?)",
                [$id, $tenantId, $tenantId]
            )->fetch();

            if (!$partner) {
                $this->respondWithError('NOT_FOUND', 'Partnership not found', null, 404);
                return;
            }

            Database::query(
                "UPDATE federation_partnerships SET status = 'rejected', updated_at = NOW() WHERE id = ?",
                [$id]
            );

            $this->respondWithData(['message' => 'Partnership rejected']);
        } catch (\Exception $e) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to reject partnership');
        }
    }

    public function terminatePartnership(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $id = $this->getRouteParam('id');

        if (!$id || !$this->tableExists('federation_partnerships')) {
            $this->respondWithError('NOT_FOUND', 'Partnership not found', null, 404);
            return;
        }

        try {
            $partner = Database::query(
                "SELECT * FROM federation_partnerships WHERE id = ? AND (tenant_id = ? OR partner_tenant_id = ?)",
                [$id, $tenantId, $tenantId]
            )->fetch();

            if (!$partner) {
                $this->respondWithError('NOT_FOUND', 'Partnership not found', null, 404);
                return;
            }

            Database::query(
                "UPDATE federation_partnerships SET status = 'terminated', updated_at = NOW() WHERE id = ?",
                [$id]
            );

            $this->respondWithData(['message' => 'Partnership terminated']);
        } catch (\Exception $e) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to terminate partnership');
        }
    }

    public function directory(): void
    {
        $this->requireAdmin();

        try {
            $stmt = Database::query(
                "SELECT t.id, t.name, t.slug, t.is_active, t.created_at,
                        (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.status = 'active') as member_count
                 FROM tenants t
                 WHERE t.is_active = 1
                 ORDER BY t.name ASC LIMIT 100"
            );
            $this->respondWithData($stmt->fetchAll() ?: []);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    public function profile(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        try {
            $stmt = Database::query(
                "SELECT t.id, t.name, t.slug, t.is_active, t.configuration, t.created_at
                 FROM tenants t WHERE t.id = ?",
                [$tenantId]
            );
            $tenant = $stmt->fetch();
            if ($tenant) {
                $config = json_decode($tenant['configuration'] ?? '{}', true);
                $tenant['federation_profile'] = $config['federation_profile'] ?? [
                    'description' => '',
                    'contact_email' => '',
                    'website' => '',
                    'categories' => [],
                ];
                unset($tenant['configuration']);
            }
            $this->respondWithData($tenant ?: []);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    /**
     * PUT /api/v2/admin/federation/directory/profile
     * Update the federation directory profile for the current tenant.
     */
    public function updateProfile(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $input = $this->getAllInput();

        try {
            $stmt = Database::query(
                "SELECT configuration FROM tenants WHERE id = ?",
                [$tenantId]
            );
            $row = $stmt->fetch();
            $config = json_decode($row['configuration'] ?? '{}', true) ?: [];

            $config['federation_profile'] = array_merge($config['federation_profile'] ?? [], $input);

            Database::query(
                "UPDATE tenants SET configuration = ? WHERE id = ?",
                [json_encode($config), $tenantId]
            );

            // Clear Redis cache if available
            try {
                \Nexus\Services\RedisCache::delete('tenant_bootstrap', $tenantId);
            } catch (\Exception $e) {
                // Redis may not be available
            }

            $this->respondWithData($config['federation_profile']);
        } catch (\Exception $e) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to update federation profile', null, 500);
        }
    }

    public function analytics(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $data = [
            'total_partnerships' => 0,
            'active_partnerships' => 0,
            'pending_requests' => 0,
            'cross_community_transactions' => 0,
            'cross_community_messages' => 0,
        ];

        if ($this->tableExists('federation_partnerships')) {
            try {
                $stmt = Database::query(
                    "SELECT COUNT(*) as total,
                            SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_count,
                            SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending
                     FROM federation_partnerships
                     WHERE tenant_id = ? OR partner_tenant_id = ?",
                    [$tenantId, $tenantId]
                );
                $row = $stmt->fetch();
                $data['total_partnerships'] = (int) ($row['total'] ?? 0);
                $data['active_partnerships'] = (int) ($row['active_count'] ?? 0);
                $data['pending_requests'] = (int) ($row['pending'] ?? 0);
            } catch (\Exception $e) {}
        }

        if ($this->tableExists('federation_transactions')) {
            try {
                $stmt = Database::query(
                    "SELECT COUNT(*) as total
                     FROM federation_transactions
                     WHERE sender_tenant_id = ? OR receiver_tenant_id = ?",
                    [$tenantId, $tenantId]
                );
                $row = $stmt->fetch();
                $data['cross_community_transactions'] = (int) ($row['total'] ?? 0);
            } catch (\Exception $e) {}
        }

        if ($this->tableExists('federation_messages')) {
            try {
                $stmt = Database::query(
                    "SELECT COUNT(*) as total
                     FROM federation_messages
                     WHERE sender_tenant_id = ? OR receiver_tenant_id = ?",
                    [$tenantId, $tenantId]
                );
                $row = $stmt->fetch();
                $data['cross_community_messages'] = (int) ($row['total'] ?? 0);
            } catch (\Exception $e) {}
        }

        $this->respondWithData($data);
    }

    public function apiKeys(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        if (!$this->tableExists('federation_api_keys')) {
            $this->respondWithData([]);
            return;
        }

        try {
            $stmt = Database::query(
                "SELECT id, name, key_prefix, status, permissions, last_used_at, expires_at, created_at
                 FROM federation_api_keys
                 WHERE tenant_id = ?
                 ORDER BY created_at DESC",
                [$tenantId]
            );
            $keys = $stmt->fetchAll() ?: [];
            foreach ($keys as &$key) {
                if (!empty($key['permissions'])) {
                    $key['scopes'] = json_decode($key['permissions'], true) ?: [];
                } else {
                    $key['scopes'] = [];
                }
                unset($key['permissions']);
            }
            $this->respondWithData($keys);
        } catch (\Exception $e) {
            $this->respondWithData([]);
        }
    }

    public function createApiKey(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();
        $name = $this->input('name');
        $scopes = $this->input('scopes', []);

        if (!$name) {
            $this->respondWithError('VALIDATION_ERROR', 'Name is required', 'name');
        }

        if (!$this->tableExists('federation_api_keys')) {
            $this->respondWithError('TABLE_MISSING', 'Federation API keys table not configured', null, 503);
        }

        try {
            $keyValue = bin2hex(random_bytes(32));
            $prefix = substr($keyValue, 0, 8);

            Database::query(
                "INSERT INTO federation_api_keys (tenant_id, name, key_hash, key_prefix, permissions, status, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, 'active', ?, NOW())",
                [$tenantId, $name, hash('sha256', $keyValue), $prefix, json_encode($scopes), $this->getAuthUserId()]
            );
            $id = Database::lastInsertId();

            $this->respondWithData([
                'id' => $id,
                'name' => $name,
                'api_key' => $keyValue,
                'key_prefix' => $prefix,
                'message' => 'Store this key securely. It will not be shown again.',
            ], null, 201);
        } catch (\Exception $e) {
            $this->respondWithError('CREATE_FAILED', 'Failed to create API key');
        }
    }

    public function dataManagement(): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $data = [
            'export_formats' => ['json', 'csv'],
            'available_exports' => [
                'users' => 'Member directory',
                'partnerships' => 'Partnership records',
                'transactions' => 'Cross-community transactions',
                'audit' => 'Audit log',
            ],
            'import_supported' => true,
            'last_export_at' => null,
            'last_import_at' => null,
        ];

        $this->respondWithData($data);
    }

    /**
     * Export federation data as CSV
     * GET /api/v2/admin/federation/export/{type}
     */
    public function exportData(string $type): void
    {
        $this->requireAdmin();
        $tenantId = TenantContext::getId();

        $allowedTypes = ['users', 'partnerships', 'transactions', 'audit'];
        if (!in_array($type, $allowedTypes, true)) {
            $this->respondWithError('INVALID_TYPE', 'Invalid export type. Allowed: ' . implode(', ', $allowedTypes), 400);
            return;
        }

        try {
            $db = Database::getInstance();
            $rows = [];
            $headers = [];

            switch ($type) {
                case 'users':
                    if (!$this->tableExists('federation_user_settings')) {
                        $this->respondWithError('NO_DATA', 'Federation user settings table not found', 404);
                        return;
                    }
                    $stmt = $db->prepare("
                        SELECT u.id, u.first_name, u.last_name, u.email, u.username,
                               fus.federation_optin, fus.privacy_level, fus.service_reach,
                               fus.created_at, fus.updated_at
                        FROM federation_user_settings fus
                        JOIN users u ON u.id = fus.user_id
                        WHERE u.tenant_id = ? AND fus.federation_optin = 1
                        ORDER BY u.first_name, u.last_name
                    ");
                    $stmt->execute([$tenantId]);
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $headers = ['ID', 'First Name', 'Last Name', 'Email', 'Username', 'Opted In', 'Privacy Level', 'Service Reach', 'Created', 'Updated'];
                    break;

                case 'partnerships':
                    if (!$this->tableExists('federation_partnerships')) {
                        $this->respondWithError('NO_DATA', 'Federation partnerships table not found', 404);
                        return;
                    }
                    $stmt = $db->prepare("
                        SELECT fp.id, t1.name AS tenant_name, t2.name AS partner_name,
                               fp.status, fp.level, fp.created_at, fp.updated_at
                        FROM federation_partnerships fp
                        LEFT JOIN tenants t1 ON t1.id = fp.tenant_id
                        LEFT JOIN tenants t2 ON t2.id = fp.partner_tenant_id
                        WHERE fp.tenant_id = ? OR fp.partner_tenant_id = ?
                        ORDER BY fp.created_at DESC
                    ");
                    $stmt->execute([$tenantId, $tenantId]);
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $headers = ['ID', 'Tenant', 'Partner', 'Status', 'Level', 'Created', 'Updated'];
                    break;

                case 'transactions':
                    if (!$this->tableExists('federation_transactions')) {
                        $this->respondWithError('NO_DATA', 'Federation transactions table not found', 404);
                        return;
                    }
                    $stmt = $db->prepare("
                        SELECT ft.id, ft.sender_user_id, ft.receiver_user_id,
                               ft.amount, ft.description, ft.status,
                               ft.created_at, ft.completed_at
                        FROM federation_transactions ft
                        WHERE ft.sender_tenant_id = ? OR ft.receiver_tenant_id = ?
                        ORDER BY ft.created_at DESC
                    ");
                    $stmt->execute([$tenantId, $tenantId]);
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $headers = ['ID', 'Sender ID', 'Receiver ID', 'Amount', 'Description', 'Status', 'Created', 'Completed'];
                    break;

                case 'audit':
                    if (!$this->tableExists('federation_audit_log')) {
                        $this->respondWithError('NO_DATA', 'Federation audit log table not found', 404);
                        return;
                    }
                    $stmt = $db->prepare("
                        SELECT id, action, category, level, actor_user_id,
                               source_tenant_id, target_tenant_id, details, created_at
                        FROM federation_audit_log
                        WHERE source_tenant_id = ? OR target_tenant_id = ?
                        ORDER BY created_at DESC
                        LIMIT 5000
                    ");
                    $stmt->execute([$tenantId, $tenantId]);
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $headers = ['ID', 'Action', 'Category', 'Level', 'Actor ID', 'Source Tenant', 'Target Tenant', 'Details', 'Created'];
                    break;
            }

            // Send CSV response
            $filename = "federation_{$type}_" . date('Y-m-d_His') . '.csv';
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, no-store, must-revalidate');

            $output = fopen('php://output', 'w');
            // BOM for Excel UTF-8 compatibility
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);

            foreach ($rows as $row) {
                fputcsv($output, array_values($row));
            }

            fclose($output);
            if (!defined('TESTING')) { if (!defined('TESTING')) { exit; } }

        } catch (\Exception $e) {
            error_log("Federation export error ({$type}): " . $e->getMessage());
            $this->respondWithError('EXPORT_FAILED', 'Failed to export data', 500);
        }
    }
}
