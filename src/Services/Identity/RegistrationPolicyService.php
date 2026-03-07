<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

use Nexus\Core\Database;

/**
 * RegistrationPolicyService — CRUD for tenant registration policies.
 *
 * Each tenant has at most one active registration policy. If no policy row
 * exists, the system falls back to TenantSettingsService behaviour (backwards
 * compatible with the original open/closed + email_verification + admin_approval model).
 */
class RegistrationPolicyService
{
    /** Valid registration modes */
    public const MODES = [
        'open',
        'open_with_approval',
        'verified_identity',
        'government_id',
        'invite_only',
    ];

    /** Valid verification levels */
    public const VERIFICATION_LEVELS = [
        'none',
        'document_only',
        'document_selfie',
        'reusable_digital_id',
        'manual_review',
    ];

    /** Valid post-verification actions */
    public const POST_VERIFICATION_ACTIONS = [
        'activate',
        'admin_approval',
        'limited_access',
        'reject_on_fail',
    ];

    /** Valid fallback modes */
    public const FALLBACK_MODES = [
        'none',
        'admin_review',
        'native_registration',
    ];

    /**
     * Get the registration policy for a tenant. Returns null if none configured.
     *
     * @param int $tenantId
     * @return array|null Policy row or null
     */
    public static function getPolicy(int $tenantId): ?array
    {
        try {
            $row = Database::query(
                "SELECT * FROM tenant_registration_policies WHERE tenant_id = ? AND is_active = 1 LIMIT 1",
                [$tenantId]
            )->fetch();

            return $row ?: null;
        } catch (\Throwable $e) {
            // Table may not exist yet during migration
            error_log("[RegistrationPolicyService] Failed to read policy for tenant {$tenantId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the effective registration mode for a tenant.
     * Falls back to TenantSettingsService if no policy row exists.
     *
     * @param int $tenantId
     * @return array Normalized policy array with mode, verification_level, etc.
     */
    public static function getEffectivePolicy(int $tenantId): array
    {
        $policy = self::getPolicy($tenantId);

        if ($policy) {
            return [
                'registration_mode' => $policy['registration_mode'],
                'verification_provider' => $policy['verification_provider'],
                'verification_level' => $policy['verification_level'],
                'post_verification' => $policy['post_verification'],
                'fallback_mode' => $policy['fallback_mode'],
                'require_email_verify' => (bool) $policy['require_email_verify'],
                'has_policy' => true,
            ];
        }

        // Backwards compatibility: derive policy from legacy TenantSettingsService
        $adminApproval = \Nexus\Services\TenantSettingsService::requiresAdminApproval($tenantId);
        $registrationMode = \Nexus\Services\TenantSettingsService::get($tenantId, 'registration_mode', 'open');
        $emailVerification = \Nexus\Services\TenantSettingsService::requiresEmailVerification($tenantId);

        $mode = 'open';
        if ($registrationMode === 'closed' || $registrationMode === 'invite') {
            $mode = 'invite_only';
        } elseif ($adminApproval) {
            $mode = 'open_with_approval';
        }

        return [
            'registration_mode' => $mode,
            'verification_provider' => null,
            'verification_level' => 'none',
            'post_verification' => $adminApproval ? 'admin_approval' : 'activate',
            'fallback_mode' => 'none',
            'require_email_verify' => $emailVerification,
            'has_policy' => false,
        ];
    }

    /**
     * Create or update the registration policy for a tenant.
     *
     * @param int   $tenantId
     * @param array $data Policy fields
     * @return array The saved policy row
     * @throws \InvalidArgumentException On invalid input
     */
    public static function upsertPolicy(int $tenantId, array $data): array
    {
        $mode = $data['registration_mode'] ?? 'open_with_approval';
        if (!in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException("Invalid registration_mode: {$mode}");
        }

        $verificationProvider = $data['verification_provider'] ?? null;
        $verificationLevel = $data['verification_level'] ?? 'none';
        $postVerification = $data['post_verification'] ?? 'activate';
        $fallbackMode = $data['fallback_mode'] ?? 'none';
        $requireEmailVerify = isset($data['require_email_verify']) ? (int)(bool) $data['require_email_verify'] : 1;

        if (!in_array($verificationLevel, self::VERIFICATION_LEVELS, true)) {
            throw new \InvalidArgumentException("Invalid verification_level: {$verificationLevel}");
        }
        if (!in_array($postVerification, self::POST_VERIFICATION_ACTIONS, true)) {
            throw new \InvalidArgumentException("Invalid post_verification: {$postVerification}");
        }
        if (!in_array($fallbackMode, self::FALLBACK_MODES, true)) {
            throw new \InvalidArgumentException("Invalid fallback_mode: {$fallbackMode}");
        }

        // Validate provider exists if verification mode requires it
        if (in_array($mode, ['verified_identity', 'government_id'], true) && $verificationProvider) {
            if (!IdentityProviderRegistry::has($verificationProvider)) {
                throw new \InvalidArgumentException("Unknown verification provider: {$verificationProvider}");
            }
        }

        // Encrypt provider config if provided
        $providerConfig = null;
        if (isset($data['provider_config']) && is_array($data['provider_config'])) {
            $providerConfig = self::encryptConfig($data['provider_config']);
        }

        Database::query(
            "INSERT INTO tenant_registration_policies
                (tenant_id, registration_mode, verification_provider, verification_level,
                 post_verification, fallback_mode, require_email_verify, provider_config, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE
                registration_mode = VALUES(registration_mode),
                verification_provider = VALUES(verification_provider),
                verification_level = VALUES(verification_level),
                post_verification = VALUES(post_verification),
                fallback_mode = VALUES(fallback_mode),
                require_email_verify = VALUES(require_email_verify),
                provider_config = COALESCE(VALUES(provider_config), provider_config),
                is_active = 1,
                updated_at = CURRENT_TIMESTAMP",
            [
                $tenantId,
                $mode,
                $verificationProvider,
                $verificationLevel,
                $postVerification,
                $fallbackMode,
                $requireEmailVerify,
                $providerConfig,
            ]
        );

        // Sync back to legacy TenantSettingsService for backwards compatibility
        self::syncToLegacySettings($tenantId, $mode, (bool) $requireEmailVerify);

        return self::getPolicy($tenantId);
    }

    /**
     * Sync registration policy to legacy tenant_settings for backwards compatibility.
     */
    private static function syncToLegacySettings(int $tenantId, string $mode, bool $requireEmailVerify): void
    {
        $tss = \Nexus\Services\TenantSettingsService::class;

        // Map new modes to legacy settings
        $registrationMode = 'open';
        $adminApproval = false;

        switch ($mode) {
            case 'open':
                $registrationMode = 'open';
                $adminApproval = false;
                break;
            case 'open_with_approval':
                $registrationMode = 'open';
                $adminApproval = true;
                break;
            case 'verified_identity':
            case 'government_id':
                $registrationMode = 'open';
                $adminApproval = false; // verification replaces approval
                break;
            case 'invite_only':
                $registrationMode = 'closed';
                $adminApproval = false;
                break;
        }

        $tss::set($tenantId, 'registration_mode', $registrationMode, 'string');
        $tss::set($tenantId, 'admin_approval', $adminApproval ? 'true' : 'false', 'boolean');
        $tss::set($tenantId, 'email_verification', $requireEmailVerify ? 'true' : 'false', 'boolean');
        $tss::clearCache();
    }

    /**
     * Encrypt provider config JSON for storage at rest.
     *
     * @param array $config
     * @return string Encrypted string
     */
    private static function encryptConfig(array $config): string
    {
        $key = \Nexus\Core\Env::get('APP_KEY');
        if (!$key) {
            // Fallback: store as JSON (not ideal but prevents data loss)
            error_log('[RegistrationPolicyService] APP_KEY not set — storing provider_config as plaintext JSON');
            return json_encode($config);
        }

        $plaintext = json_encode($config);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Decrypt provider config from storage.
     *
     * @param string $encrypted
     * @return array Decoded config
     */
    public static function decryptConfig(string $encrypted): array
    {
        $key = \Nexus\Core\Env::get('APP_KEY');
        if (!$key) {
            // Try JSON decode as fallback
            $decoded = json_decode($encrypted, true);
            return is_array($decoded) ? $decoded : [];
        }

        $raw = base64_decode($encrypted);
        if ($raw === false || strlen($raw) < 28) {
            return [];
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            error_log('[RegistrationPolicyService] Failed to decrypt provider_config');
            return [];
        }

        $decoded = json_decode($plaintext, true);
        return is_array($decoded) ? $decoded : [];
    }
}
