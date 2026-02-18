<?php

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
                if (isset($config['federation'])) {
                    $data['settings'] = array_merge($data['settings'], $config['federation']);
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

            $config['federation'] = array_merge($config['federation'] ?? [], $input);

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
                'federation_enabled' => TenantContext::hasFeature('federation'),
                'tenant_id' => $tenantId,
                'settings' => $config['federation'],
            ]);
        } catch (\Exception $e) {
            $this->respondWithError('UPDATE_FAILED', 'Failed to update federation settings', null, 500);
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
}
