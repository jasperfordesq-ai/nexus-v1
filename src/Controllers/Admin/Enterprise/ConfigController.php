<?php

declare(strict_types=1);

namespace Nexus\Controllers\Admin\Enterprise;

use Nexus\Core\View;
use Nexus\Core\Database;
use Nexus\Services\Enterprise\ConfigService;

/**
 * Config Controller
 *
 * Handles system configuration and feature flags.
 */
class ConfigController extends BaseEnterpriseController
{
    private ConfigService $configService;

    public function __construct()
    {
        parent::__construct();
        $this->configService = ConfigService::getInstance();
    }

    /**
     * GET /admin/enterprise/config
     * System configuration dashboard
     */
    public function dashboard(): void
    {
        $config = [
            'environment' => getenv('APP_ENV') ?: 'unknown',
            'debug' => $this->configService->isDebug(),
            'vault' => $this->configService->getStatus(),
            'features' => $this->getFeatureFlags(),
        ];

        View::render('admin/enterprise/config/dashboard', [
            'config' => $config,
            'title' => 'System Configuration',
        ]);
    }

    /**
     * POST /admin/enterprise/config/settings/{group}/{key}
     * Update a configuration setting
     */
    public function updateSetting(string $group, string $key): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $value = $data['value'] ?? null;

        $validGroups = ['features', 'security', 'performance', 'notifications'];
        if (!in_array($group, $validGroups)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid configuration group']);
            return;
        }

        try {
            Database::query(
                "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = ?",
                [$this->getTenantId(), "{$group}.{$key}", json_encode($value), json_encode($value)]
            );

            $this->configService->clearCache();
            $this->logger->info("Config updated", ['group' => $group, 'key' => $key]);

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/enterprise/config/export
     * Export current configuration
     */
    public function export(): void
    {
        $config = [
            'exported_at' => date('c'),
            'environment' => getenv('APP_ENV') ?: 'unknown',
            'features' => $this->getFeatureFlags(),
            'status' => $this->configService->getStatus(),
        ];

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="config_export_' . date('Y-m-d') . '.json"');
        echo json_encode($config, JSON_PRETTY_PRINT);
    }

    /**
     * POST /admin/enterprise/config/cache/clear
     * Clear configuration cache
     */
    public function clearCache(): void
    {
        header('Content-Type: application/json');

        try {
            $this->configService->clearCache();
            $this->logger->info("Config cache cleared by admin", ['admin_id' => $this->getCurrentUserId()]);

            echo json_encode(['success' => true, 'message' => 'Configuration cache cleared']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /admin/enterprise/config/validate
     * Validate current configuration
     */
    public function validate(): void
    {
        header('Content-Type: application/json');

        $issues = [];
        $warnings = [];

        // Check required environment variables
        $requiredEnvVars = ['DB_HOST', 'DB_DATABASE', 'APP_KEY'];
        foreach ($requiredEnvVars as $var) {
            if (empty(getenv($var))) {
                $issues[] = "Missing required environment variable: {$var}";
            }
        }

        // Check optional but recommended
        $recommendedEnvVars = ['SMTP_HOST', 'REDIS_HOST'];
        foreach ($recommendedEnvVars as $var) {
            if (empty(getenv($var))) {
                $warnings[] = "Recommended environment variable not set: {$var}";
            }
        }

        // Check database connection
        try {
            Database::query('SELECT 1');
        } catch (\Exception $e) {
            $issues[] = "Database connection failed: " . $e->getMessage();
        }

        // Check Vault status
        if (!$this->configService->isUsingVault()) {
            $warnings[] = "HashiCorp Vault is not configured - using environment variables for secrets";
        }

        echo json_encode([
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
            'timestamp' => date('c'),
        ]);
    }

    /**
     * PATCH /admin/enterprise/config/features/{key}
     * Toggle a feature flag
     */
    public function toggleFeature(string $key): void
    {
        header('Content-Type: application/json');

        $data = $this->getJsonInput();
        $enabled = (bool) ($data['enabled'] ?? false);

        try {
            Database::query(
                "INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, setting_type)
                 VALUES (?, ?, ?, 'boolean')
                 ON DUPLICATE KEY UPDATE setting_value = ?",
                [$this->getTenantId(), "feature.{$key}", $enabled ? '1' : '0', $enabled ? '1' : '0']
            );

            $this->logger->info("Feature flag toggled", ['key' => $key, 'enabled' => $enabled]);

            echo json_encode(['success' => true, 'key' => $key, 'enabled' => $enabled]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /admin/enterprise/config/features/reset
     * Reset all feature flags to defaults
     */
    public function resetFeatures(): void
    {
        header('Content-Type: application/json');

        try {
            Database::query(
                "DELETE FROM tenant_settings WHERE tenant_id = ? AND setting_key LIKE 'feature.%'",
                [$this->getTenantId()]
            );

            $this->logger->info("Feature flags reset to defaults", ['admin_id' => $this->getCurrentUserId()]);

            echo json_encode(['success' => true, 'message' => 'Feature flags reset to defaults']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Get all feature flags with their current values
     */
    private function getFeatureFlags(): array
    {
        $availableFeatures = [
            'timebanking' => true,
            'listings' => true,
            'messaging' => true,
            'connections' => true,
            'profiles' => true,
            'groups' => true,
            'events' => true,
            'volunteering' => true,
            'organizations' => true,
            'gamification' => true,
            'leaderboard' => true,
            'badges' => true,
            'streaks' => true,
            'ai_chat' => true,
            'smart_matching' => true,
            'ai_moderation' => false,
            'push_notifications' => true,
            'email_notifications' => true,
            'gdpr_compliance' => true,
            'analytics' => true,
            'audit_logging' => true,
            'map_view' => true,
            'geolocation' => true,
            'layout_banner' => true,
        ];

        $tenantId = $this->getTenantId();
        try {
            $stored = Database::query(
                "SELECT setting_key, setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key LIKE 'feature.%'",
                [$tenantId]
            )->fetchAll(\PDO::FETCH_KEY_PAIR);
        } catch (\Exception $e) {
            $stored = [];
        }

        $features = [];
        foreach ($availableFeatures as $key => $default) {
            $dbKey = "feature.{$key}";
            if (isset($stored[$dbKey])) {
                $features[$key] = $stored[$dbKey] === '1' || $stored[$dbKey] === 'true';
            } else {
                $envKey = 'FEATURE_' . strtoupper($key);
                $envValue = getenv($envKey);
                $features[$key] = $envValue !== false ? (bool) $envValue : $default;
            }
        }

        return $features;
    }
}
