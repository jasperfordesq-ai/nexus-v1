<?php

namespace Nexus\Services;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * BrokerControlConfigService
 *
 * Centralized configuration service for all broker control features.
 * Manages tenant-level settings stored in tenants.configuration JSON.
 *
 * Features managed:
 * - Direct messaging toggle
 * - Risk tagging
 * - Exchange workflow
 * - Broker message visibility
 */
class BrokerControlConfigService
{
    /**
     * Default configuration values
     */
    private const DEFAULTS = [
        'messaging' => [
            'direct_messaging_enabled' => true,
            'first_contact_monitoring' => true,
            'new_member_monitoring_days' => 30,
            'require_exchange_for_listings' => false,
        ],
        'risk_tagging' => [
            'enabled' => true,
            'high_risk_requires_approval' => true,
            'notify_on_high_risk_match' => true,
            'default_risk_level' => 'low',
        ],
        'exchange_workflow' => [
            'enabled' => false,
            'require_broker_approval' => false,
            'auto_approve_low_risk' => true,
            'max_hours_without_approval' => 4,
            'confirmation_deadline_hours' => 72,
            'allow_hour_adjustment' => true,
            'max_hour_variance_percent' => 25,
            'expiry_hours' => 168, // 7 days
        ],
        'broker_visibility' => [
            'enabled' => true,
            'copy_first_contact' => true,
            'copy_new_member_messages' => true,
            'copy_high_risk_listing_messages' => true,
            'random_sample_percentage' => 0,
            'retention_days' => 365,
        ],
    ];

    /**
     * Get all broker control configuration
     *
     * @param string|null $section Optional section to retrieve (messaging, risk_tagging, etc.)
     * @return array Configuration array
     */
    public static function getConfig(?string $section = null): array
    {
        $tenantId = TenantContext::getId();

        $stmt = Database::query(
            "SELECT configuration FROM tenants WHERE id = ?",
            [$tenantId]
        );
        $tenant = $stmt->fetch();

        $config = [];
        if ($tenant && !empty($tenant['configuration'])) {
            $fullConfig = json_decode($tenant['configuration'], true) ?? [];
            $config = $fullConfig['broker_controls'] ?? [];
        }

        // Merge with defaults
        $merged = self::mergeWithDefaults($config);

        if ($section !== null) {
            return $merged[$section] ?? self::DEFAULTS[$section] ?? [];
        }

        return $merged;
    }

    /**
     * Update broker control configuration
     *
     * @param array $data Configuration data to update
     * @return bool Success status
     */
    public static function updateConfig(array $data): bool
    {
        $tenantId = TenantContext::getId();

        // Get current full configuration
        $stmt = Database::query(
            "SELECT configuration FROM tenants WHERE id = ?",
            [$tenantId]
        );
        $tenant = $stmt->fetch();

        $fullConfig = [];
        if ($tenant && !empty($tenant['configuration'])) {
            $fullConfig = json_decode($tenant['configuration'], true) ?? [];
        }

        // Merge new broker_controls with existing
        $fullConfig['broker_controls'] = self::sanitizeConfig($data);

        // Save back
        $result = Database::query(
            "UPDATE tenants SET configuration = ? WHERE id = ?",
            [json_encode($fullConfig), $tenantId]
        );

        // Clear any cached configuration
        self::clearCache();

        return $result !== false;
    }

    /**
     * Update a specific section of the configuration
     *
     * @param string $section Section name (messaging, risk_tagging, etc.)
     * @param array $data Section data
     * @return bool Success status
     */
    public static function updateSection(string $section, array $data): bool
    {
        $config = self::getConfig();
        $config[$section] = array_merge($config[$section] ?? [], $data);
        return self::updateConfig($config);
    }

    // =========================================================================
    // MESSAGING FEATURE CHECKS
    // =========================================================================

    /**
     * Check if direct messaging is enabled for the tenant
     *
     * @return bool
     */
    public static function isDirectMessagingEnabled(): bool
    {
        $config = self::getConfig('messaging');
        return (bool) ($config['direct_messaging_enabled'] ?? true);
    }

    /**
     * Check if first contact monitoring is enabled
     *
     * @return bool
     */
    public static function isFirstContactMonitoringEnabled(): bool
    {
        if (!self::isBrokerVisibilityEnabled()) {
            return false;
        }
        $config = self::getConfig('broker_visibility');
        return (bool) ($config['copy_first_contact'] ?? true);
    }

    /**
     * Get new member monitoring period in days
     *
     * @return int Days (0 = disabled)
     */
    public static function getNewMemberMonitoringDays(): int
    {
        $config = self::getConfig('messaging');
        return (int) ($config['new_member_monitoring_days'] ?? 30);
    }

    // =========================================================================
    // RISK TAGGING FEATURE CHECKS
    // =========================================================================

    /**
     * Check if risk tagging is enabled
     *
     * @return bool
     */
    public static function isRiskTaggingEnabled(): bool
    {
        $config = self::getConfig('risk_tagging');
        return (bool) ($config['enabled'] ?? true);
    }

    /**
     * Check if high-risk listings require broker approval
     *
     * @return bool
     */
    public static function doesHighRiskRequireApproval(): bool
    {
        $config = self::getConfig('risk_tagging');
        return (bool) ($config['high_risk_requires_approval'] ?? true);
    }

    // =========================================================================
    // EXCHANGE WORKFLOW FEATURE CHECKS
    // =========================================================================

    /**
     * Check if exchange workflow is enabled
     *
     * @return bool
     */
    public static function isExchangeWorkflowEnabled(): bool
    {
        $config = self::getConfig('exchange_workflow');
        return (bool) ($config['enabled'] ?? false);
    }

    /**
     * Check if broker approval is required for exchanges
     *
     * @return bool
     */
    public static function requiresBrokerApproval(): bool
    {
        $config = self::getConfig('exchange_workflow');
        return (bool) ($config['require_broker_approval'] ?? false);
    }

    /**
     * Check if low-risk exchanges can be auto-approved
     *
     * @return bool
     */
    public static function canAutoApproveLowRisk(): bool
    {
        $config = self::getConfig('exchange_workflow');
        return (bool) ($config['auto_approve_low_risk'] ?? true);
    }

    /**
     * Get max hours that can be exchanged without broker approval
     *
     * @return float
     */
    public static function getMaxHoursWithoutApproval(): float
    {
        $config = self::getConfig('exchange_workflow');
        return (float) ($config['max_hours_without_approval'] ?? 4);
    }

    /**
     * Get confirmation deadline in hours
     *
     * @return int
     */
    public static function getConfirmationDeadlineHours(): int
    {
        $config = self::getConfig('exchange_workflow');
        return (int) ($config['confirmation_deadline_hours'] ?? 72);
    }

    /**
     * Get exchange request expiry hours
     *
     * @return int
     */
    public static function getExpiryHours(): int
    {
        $config = self::getConfig('exchange_workflow');
        return (int) ($config['expiry_hours'] ?? 168);
    }

    // =========================================================================
    // BROKER VISIBILITY FEATURE CHECKS
    // =========================================================================

    /**
     * Check if broker visibility is enabled
     *
     * @return bool
     */
    public static function isBrokerVisibilityEnabled(): bool
    {
        $config = self::getConfig('broker_visibility');
        return (bool) ($config['enabled'] ?? true);
    }

    /**
     * Check if new member messages should be copied
     *
     * @return bool
     */
    public static function shouldCopyNewMemberMessages(): bool
    {
        if (!self::isBrokerVisibilityEnabled()) {
            return false;
        }
        $config = self::getConfig('broker_visibility');
        return (bool) ($config['copy_new_member_messages'] ?? true);
    }

    /**
     * Check if high-risk listing messages should be copied
     *
     * @return bool
     */
    public static function shouldCopyHighRiskMessages(): bool
    {
        if (!self::isBrokerVisibilityEnabled()) {
            return false;
        }
        $config = self::getConfig('broker_visibility');
        return (bool) ($config['copy_high_risk_listing_messages'] ?? true);
    }

    /**
     * Get random sample percentage (0-100)
     *
     * @return int
     */
    public static function getRandomSamplePercentage(): int
    {
        $config = self::getConfig('broker_visibility');
        return min(100, max(0, (int) ($config['random_sample_percentage'] ?? 0)));
    }

    /**
     * Get message copy retention days
     *
     * @return int
     */
    public static function getRetentionDays(): int
    {
        $config = self::getConfig('broker_visibility');
        return (int) ($config['retention_days'] ?? 365);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Merge configuration with defaults
     *
     * @param array $config User configuration
     * @return array Merged configuration
     */
    private static function mergeWithDefaults(array $config): array
    {
        $merged = [];
        foreach (self::DEFAULTS as $section => $defaults) {
            $merged[$section] = array_merge($defaults, $config[$section] ?? []);
        }
        return $merged;
    }

    /**
     * Sanitize and validate configuration data
     *
     * @param array $data Raw configuration data
     * @return array Sanitized configuration
     */
    private static function sanitizeConfig(array $data): array
    {
        $sanitized = [];

        // Messaging section
        if (isset($data['messaging'])) {
            $sanitized['messaging'] = [
                'direct_messaging_enabled' => (bool) ($data['messaging']['direct_messaging_enabled'] ?? true),
                'first_contact_monitoring' => (bool) ($data['messaging']['first_contact_monitoring'] ?? true),
                'new_member_monitoring_days' => max(0, (int) ($data['messaging']['new_member_monitoring_days'] ?? 30)),
                'require_exchange_for_listings' => (bool) ($data['messaging']['require_exchange_for_listings'] ?? false),
            ];
        }

        // Risk tagging section
        if (isset($data['risk_tagging'])) {
            $sanitized['risk_tagging'] = [
                'enabled' => (bool) ($data['risk_tagging']['enabled'] ?? true),
                'high_risk_requires_approval' => (bool) ($data['risk_tagging']['high_risk_requires_approval'] ?? true),
                'notify_on_high_risk_match' => (bool) ($data['risk_tagging']['notify_on_high_risk_match'] ?? true),
                'default_risk_level' => in_array($data['risk_tagging']['default_risk_level'] ?? 'low', ['low', 'medium', 'high', 'critical'])
                    ? $data['risk_tagging']['default_risk_level']
                    : 'low',
            ];
        }

        // Exchange workflow section
        if (isset($data['exchange_workflow'])) {
            $sanitized['exchange_workflow'] = [
                'enabled' => (bool) ($data['exchange_workflow']['enabled'] ?? false),
                'require_broker_approval' => (bool) ($data['exchange_workflow']['require_broker_approval'] ?? false),
                'auto_approve_low_risk' => (bool) ($data['exchange_workflow']['auto_approve_low_risk'] ?? true),
                'max_hours_without_approval' => max(0, min(24, (float) ($data['exchange_workflow']['max_hours_without_approval'] ?? 4))),
                'confirmation_deadline_hours' => max(1, min(720, (int) ($data['exchange_workflow']['confirmation_deadline_hours'] ?? 72))),
                'allow_hour_adjustment' => (bool) ($data['exchange_workflow']['allow_hour_adjustment'] ?? true),
                'max_hour_variance_percent' => max(0, min(100, (int) ($data['exchange_workflow']['max_hour_variance_percent'] ?? 25))),
                'expiry_hours' => max(1, min(720, (int) ($data['exchange_workflow']['expiry_hours'] ?? 168))),
            ];
        }

        // Broker visibility section
        if (isset($data['broker_visibility'])) {
            $sanitized['broker_visibility'] = [
                'enabled' => (bool) ($data['broker_visibility']['enabled'] ?? true),
                'copy_first_contact' => (bool) ($data['broker_visibility']['copy_first_contact'] ?? true),
                'copy_new_member_messages' => (bool) ($data['broker_visibility']['copy_new_member_messages'] ?? true),
                'copy_high_risk_listing_messages' => (bool) ($data['broker_visibility']['copy_high_risk_listing_messages'] ?? true),
                'random_sample_percentage' => max(0, min(100, (int) ($data['broker_visibility']['random_sample_percentage'] ?? 0))),
                'retention_days' => max(1, min(3650, (int) ($data['broker_visibility']['retention_days'] ?? 365))),
            ];
        }

        return $sanitized;
    }

    /**
     * Clear cached configuration
     */
    private static function clearCache(): void
    {
        // If using Redis or other cache, clear here
        // Currently tenant config is not cached separately
    }

    /**
     * Get a summary of enabled features for display
     *
     * @return array Feature summary
     */
    public static function getFeatureSummary(): array
    {
        return [
            'direct_messaging' => self::isDirectMessagingEnabled(),
            'risk_tagging' => self::isRiskTaggingEnabled(),
            'exchange_workflow' => self::isExchangeWorkflowEnabled(),
            'broker_visibility' => self::isBrokerVisibilityEnabled(),
            'broker_approval_required' => self::requiresBrokerApproval(),
            'first_contact_monitoring' => self::isFirstContactMonitoringEnabled(),
        ];
    }
}
