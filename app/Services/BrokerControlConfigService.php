<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
    /** Default configuration values */
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
            'expiry_hours' => 168,
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
     * Get all broker control configuration.
     *
     * @param string|null $section Optional section to retrieve
     * @return array Configuration array
     */
    public function getConfig(?string $section = null): array
    {
        $tenantId = TenantContext::getId();

        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        $config = [];
        if ($tenant && !empty($tenant->configuration)) {
            $fullConfig = json_decode($tenant->configuration, true) ?? [];
            $config = $fullConfig['broker_controls'] ?? [];
        }

        // Also check tenant_settings.broker_config for controller-saved values
        try {
            $settingsRow = DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('setting_key', 'broker_config')
                ->first();

            if ($settingsRow && !empty($settingsRow->setting_value)) {
                $controllerConfig = json_decode($settingsRow->setting_value, true) ?? [];
                foreach (['require_broker_approval', 'auto_approve_low_risk', 'max_hours_without_approval', 'exchange_workflow_enabled'] as $key) {
                    if (array_key_exists($key, $controllerConfig)) {
                        $config[$key] = $controllerConfig[$key];
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore — use defaults
        }

        $merged = self::mergeWithDefaults($config);

        if ($section !== null) {
            return $merged[$section] ?? self::DEFAULTS[$section] ?? [];
        }

        return $merged;
    }

    /**
     * Update broker control configuration.
     *
     * @param array $data Configuration data to update
     * @return bool
     */
    public function updateConfig(array $data): bool
    {
        $tenantId = TenantContext::getId();

        $tenant = DB::table('tenants')->where('id', $tenantId)->first();

        $fullConfig = [];
        if ($tenant && !empty($tenant->configuration)) {
            $fullConfig = json_decode($tenant->configuration, true) ?? [];
        }

        $fullConfig['broker_controls'] = self::sanitizeConfig($data);

        $result = DB::table('tenants')
            ->where('id', $tenantId)
            ->update(['configuration' => json_encode($fullConfig)]);

        self::clearCache();

        return $result !== false;
    }

    /**
     * Update a specific section of the configuration.
     *
     * @param string $section Section name
     * @param array $data Section data
     * @return bool
     */
    public function updateSection(string $section, array $data): bool
    {
        $config = $this->getConfig();
        $config[$section] = array_merge($config[$section] ?? [], $data);
        return $this->updateConfig($config);
    }

    // =========================================================================
    // MESSAGING FEATURE CHECKS
    // =========================================================================

    public function isDirectMessagingEnabled(): bool
    {
        $config = $this->getConfig('messaging');
        return (bool) ($config['direct_messaging_enabled'] ?? true);
    }

    public function isFirstContactMonitoringEnabled(): bool
    {
        if (!$this->isBrokerVisibilityEnabled()) {
            return false;
        }
        $config = $this->getConfig('broker_visibility');
        return (bool) ($config['copy_first_contact'] ?? true);
    }

    // =========================================================================
    // EXCHANGE WORKFLOW FEATURE CHECKS
    // =========================================================================

    public function isExchangeWorkflowEnabled(): bool
    {
        $config = $this->getConfig('exchange_workflow');
        return (bool) ($config['enabled'] ?? false);
    }

    // =========================================================================
    // BROKER VISIBILITY FEATURE CHECKS
    // =========================================================================

    public function isBrokerVisibilityEnabled(): bool
    {
        $config = $this->getConfig('broker_visibility');
        return (bool) ($config['enabled'] ?? true);
    }

    // =========================================================================
    // COMPLIANCE FEATURE TOGGLES
    // =========================================================================

    private function getComplianceSetting(string $key, mixed $default = false): mixed
    {
        $tenantId = TenantContext::getId();
        try {
            $row = DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('setting_key', 'broker_config')
                ->first();

            if ($row && !empty($row->setting_value)) {
                $config = json_decode($row->setting_value, true) ?? [];
                return $config[$key] ?? $default;
            }
        } catch (\Throwable $e) {
            // fail gracefully
        }
        return $default;
    }

    public function isVettingEnabled(): bool
    {
        return (bool) $this->getComplianceSetting('vetting_enabled', false);
    }

    public function isInsuranceEnabled(): bool
    {
        return (bool) $this->getComplianceSetting('insurance_enabled', false);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private static function mergeWithDefaults(array $config): array
    {
        $merged = [];
        foreach (self::DEFAULTS as $section => $defaults) {
            $merged[$section] = array_merge($defaults, $config[$section] ?? []);
        }
        return $merged;
    }

    private static function sanitizeConfig(array $data): array
    {
        $sanitized = [];

        if (isset($data['messaging'])) {
            $sanitized['messaging'] = [
                'direct_messaging_enabled' => (bool) ($data['messaging']['direct_messaging_enabled'] ?? true),
                'first_contact_monitoring' => (bool) ($data['messaging']['first_contact_monitoring'] ?? true),
                'new_member_monitoring_days' => max(0, (int) ($data['messaging']['new_member_monitoring_days'] ?? 30)),
                'require_exchange_for_listings' => (bool) ($data['messaging']['require_exchange_for_listings'] ?? false),
            ];
        }

        if (isset($data['risk_tagging'])) {
            $sanitized['risk_tagging'] = [
                'enabled' => (bool) ($data['risk_tagging']['enabled'] ?? true),
                'high_risk_requires_approval' => (bool) ($data['risk_tagging']['high_risk_requires_approval'] ?? true),
                'notify_on_high_risk_match' => (bool) ($data['risk_tagging']['notify_on_high_risk_match'] ?? true),
                'default_risk_level' => in_array($data['risk_tagging']['default_risk_level'] ?? 'low', ['low', 'medium', 'high', 'critical'])
                    ? ($data['risk_tagging']['default_risk_level'] ?? 'low')
                    : 'low',
            ];
        }

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

    private static function clearCache(): void
    {
        $tenantId = TenantContext::getId();
        try {
            Cache::forget("broker_config_{$tenantId}");
            Cache::forget("tenant_bootstrap_{$tenantId}");
        } catch (\Throwable $e) {
            // Cache unavailable
        }
    }
}
