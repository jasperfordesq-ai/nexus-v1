<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\TenantFeatureConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AdminSettingsController -- Tenant settings and feature toggles.
 *
 * All methods require admin authentication. Super-admin-only settings
 * (maintenance_mode, registration_mode, etc.) are protected and cannot
 * be changed by regular tenant admins.
 */
class AdminSettingsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct() {}

    /**
     * Setting keys that regular admins are allowed to modify.
     * Keys NOT in this list require super-admin privileges.
     */
    private const ADMIN_ALLOWED_KEYS = [
        'general.timezone',
        'general.welcome_message',
        'general.default_currency',
        'general.date_format',
        'general.time_format',
        'general.items_per_page',
        'general.welcome_credits',
        'general.footer_text',
        // Onboarding keys are managed by AdminOnboardingConfigController with its own whitelist
    ];

    /**
     * Setting keys that ONLY super-admins may modify.
     * Regular admins attempting to set these will receive a 403 error.
     */
    private const SUPER_ADMIN_ONLY_KEYS = [
        'general.maintenance_mode',
        'general.registration_mode',
        'general.email_verification',
        'general.admin_approval',
        'general.max_upload_size_mb',
    ];

    /**
     * All recognized setting key prefixes. Keys outside these namespaces are rejected.
     */
    private const ALLOWED_PREFIXES = [
        'general.',
        'onboarding.',
    ];

    /** GET /api/v2/admin/settings */
    public function index(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $settings = DB::select(
            'SELECT setting_key, setting_value FROM tenant_settings WHERE tenant_id = ?',
            [$tenantId]
        );

        $result = [];
        foreach ($settings as $s) {
            $result[$s->setting_key] = $s->setting_value;
        }

        return $this->respondWithData($result);
    }

    /** PUT /api/v2/admin/settings */
    public function update(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $data = $this->getAllInput();

        if (empty($data)) {
            return $this->respondWithError('VALIDATION_ERROR', 'No settings provided', null, 422);
        }

        $isSuperAdmin = $this->isSuperAdmin();

        $updated = 0;
        $rejected = [];
        foreach ($data as $key => $value) {
            // Validate key format: alphanumeric + underscores/dots only, max 100 chars
            if (!is_string($key) || !preg_match('/^[a-zA-Z][a-zA-Z0-9_.]{0,99}$/', $key)) {
                continue;
            }

            // Reject keys outside recognized prefixes
            $hasValidPrefix = false;
            foreach (self::ALLOWED_PREFIXES as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    $hasValidPrefix = true;
                    break;
                }
            }
            if (!$hasValidPrefix) {
                $rejected[] = $key;
                continue;
            }

            // Super-admin-only keys require elevated privileges
            if (in_array($key, self::SUPER_ADMIN_ONLY_KEYS, true) && !$isSuperAdmin) {
                $rejected[] = $key;
                continue;
            }

            // Validate specific setting values
            $validationError = $this->validateSettingValue($key, $value);
            if ($validationError !== null) {
                return $this->respondWithError('VALIDATION_ERROR', $validationError, $key, 422);
            }

            $stringValue = is_array($value) ? json_encode($value) : (string) $value;
            DB::statement(
                'INSERT INTO tenant_settings (tenant_id, setting_key, setting_value) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                [$tenantId, $key, $stringValue]
            );
            $updated++;
        }

        // Audit log
        if ($updated > 0) {
            Log::info('Admin settings updated', [
                'admin_id' => $adminId,
                'tenant_id' => $tenantId,
                'keys_updated' => array_keys(
                    collect($data)->filter(fn ($v, $k) => !in_array($k, $rejected))->all()
                ),
            ]);
        }

        $response = ['updated' => $updated];
        if (!empty($rejected)) {
            $response['rejected_keys'] = $rejected;
        }

        return $this->respondWithData($response);
    }

    /** GET /api/v2/admin/settings/features */
    public function features(): JsonResponse
    {
        $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $features = DB::select(
            'SELECT feature_key, is_enabled FROM tenant_features WHERE tenant_id = ?',
            [$tenantId]
        );

        $result = [];
        foreach ($features as $f) {
            $result[$f->feature_key] = (bool) $f->is_enabled;
        }

        return $this->respondWithData($result);
    }

    /** POST /api/v2/admin/settings/features/toggle */
    public function toggleFeature(): JsonResponse
    {
        $adminId = $this->requireAdmin();
        $tenantId = $this->getTenantId();

        $feature = $this->requireInput('feature');
        $enabled = $this->inputBool('enabled', true);

        // Validate feature key against known features
        if (!array_key_exists($feature, TenantFeatureConfig::FEATURE_DEFAULTS)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'Unknown feature: ' . $feature . '. Valid features: ' . implode(', ', array_keys(TenantFeatureConfig::FEATURE_DEFAULTS)),
                'feature',
                422
            );
        }

        DB::statement(
            'INSERT INTO tenant_features (tenant_id, feature_key, is_enabled) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)',
            [$tenantId, $feature, $enabled ? 1 : 0]
        );

        Log::info('Feature toggled', [
            'admin_id' => $adminId,
            'tenant_id' => $tenantId,
            'feature' => $feature,
            'enabled' => $enabled,
        ]);

        return $this->respondWithData(['feature' => $feature, 'enabled' => $enabled]);
    }

    /**
     * Validate specific setting values for safety bounds.
     *
     * @return string|null Error message, or null if valid
     */
    private function validateSettingValue(string $key, mixed $value): ?string
    {
        return match ($key) {
            'general.max_upload_size_mb' => (function () use ($value) {
                $mb = (int) $value;
                if ($mb < 1 || $mb > 50) {
                    return 'max_upload_size_mb must be between 1 and 50';
                }
                return null;
            })(),
            'general.items_per_page' => (function () use ($value) {
                $ipp = (int) $value;
                if ($ipp < 5 || $ipp > 100) {
                    return 'items_per_page must be between 5 and 100';
                }
                return null;
            })(),
            'general.welcome_credits' => (function () use ($value) {
                $wc = (int) $value;
                if ($wc < 0 || $wc > 100) {
                    return 'welcome_credits must be between 0 and 100';
                }
                return null;
            })(),
            'general.maintenance_mode' => (function () use ($value) {
                if (!in_array((string) $value, ['true', 'false', '1', '0'], true)) {
                    return 'maintenance_mode must be a boolean value';
                }
                return null;
            })(),
            'general.registration_mode' => (function () use ($value) {
                if (!in_array($value, ['open', 'closed', 'invite_only'], true)) {
                    return 'registration_mode must be one of: open, closed, invite_only';
                }
                return null;
            })(),
            default => null,
        };
    }

    /**
     * Check if current authenticated user is a super admin.
     */
    private function isSuperAdmin(): bool
    {
        try {
            $this->requireSuperAdmin();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
