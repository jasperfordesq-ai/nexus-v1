<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * RegistrationOrchestrationService — Central coordinator for the registration flow.
 *
 * After a user is created by RegistrationApiController, this service determines
 * the next steps based on the tenant's registration policy: direct activation,
 * admin approval, identity verification, or invite-only gate.
 *
 * This is the single entry point for post-registration logic that depends
 * on the Registration Policy Engine.
 */
class RegistrationOrchestrationService
{
    /**
     * Process a newly registered user according to the tenant's registration policy.
     *
     * Called from RegistrationApiController after user creation. Returns
     * instructions for the controller to render the correct response.
     *
     * @param int $userId
     * @param int $tenantId
     * @return array{
     *   action: string,
     *   requires_verification: bool,
     *   requires_approval: bool,
     *   verification_session: ?array,
     *   next_steps: string[],
     *   message: string
     * }
     */
    public static function processRegistration(int $userId, int $tenantId): array
    {
        $policy = RegistrationPolicyService::getEffectivePolicy($tenantId);

        // Log registration start
        IdentityVerificationEventService::log(
            $tenantId,
            $userId,
            IdentityVerificationEventService::EVENT_REGISTRATION_STARTED,
            null,
            null,
            IdentityVerificationEventService::ACTOR_SYSTEM,
            ['registration_mode' => $policy['registration_mode'], 'has_policy' => $policy['has_policy']],
            \Nexus\Core\ClientIp::get(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        switch ($policy['registration_mode']) {
            case 'open':
                return self::handleOpenRegistration($userId, $tenantId, $policy);

            case 'open_with_approval':
                return self::handleOpenWithApproval($userId, $tenantId, $policy);

            case 'verified_identity':
            case 'government_id':
                return self::handleVerifiedIdentity($userId, $tenantId, $policy);

            case 'invite_only':
                return self::handleInviteOnly($userId, $tenantId, $policy);

            default:
                // Unknown mode — safe fallback to approval
                return self::handleOpenWithApproval($userId, $tenantId, $policy);
        }
    }

    /**
     * Initiate identity verification for a user.
     *
     * Called when the user is ready to start verification (may be immediately
     * after registration or when they click "Start Verification").
     *
     * @param int $userId
     * @param int $tenantId
     * @return array Session data with redirect_url or client_token
     * @throws \RuntimeException If verification cannot be initiated
     */
    public static function initiateVerification(int $userId, int $tenantId): array
    {
        $policy = RegistrationPolicyService::getEffectivePolicy($tenantId);

        if (!$policy['verification_provider']) {
            throw new \RuntimeException('No verification provider configured for this tenant.');
        }

        $provider = IdentityProviderRegistry::get($policy['verification_provider']);

        if (!$provider->isAvailable($tenantId)) {
            // Trigger fallback if configured
            if ($policy['fallback_mode'] !== 'none') {
                return self::triggerFallback($userId, $tenantId, 'provider_unavailable');
            }
            throw new \RuntimeException('Identity verification provider is currently unavailable.');
        }

        // Load provider config for tenant
        $policyRow = RegistrationPolicyService::getPolicy($tenantId);
        $providerConfig = [];
        if ($policyRow && $policyRow['provider_config']) {
            $providerConfig = RegistrationPolicyService::decryptConfig($policyRow['provider_config']);
        }

        // Create session with provider
        $providerData = $provider->createSession(
            $userId,
            $tenantId,
            $policy['verification_level'],
            $providerConfig
        );

        // Persist session
        $sessionId = IdentityVerificationSessionService::create(
            $tenantId,
            $userId,
            $policy['verification_provider'],
            $policy['verification_level'],
            $providerData
        );

        // Update user verification status
        Database::query(
            "UPDATE users SET verification_status = 'pending', verification_provider = ? WHERE id = ? AND tenant_id = ?",
            [$policy['verification_provider'], $userId, $tenantId]
        );

        // Log
        IdentityVerificationEventService::log(
            $tenantId,
            $userId,
            IdentityVerificationEventService::EVENT_VERIFICATION_CREATED,
            $sessionId,
            null,
            IdentityVerificationEventService::ACTOR_SYSTEM,
            ['provider' => $policy['verification_provider'], 'level' => $policy['verification_level']]
        );

        $session = IdentityVerificationSessionService::getById($sessionId);

        return [
            'session_id' => $sessionId,
            'redirect_url' => $providerData['redirect_url'] ?? null,
            'client_token' => $providerData['client_token'] ?? null,
            'provider' => $policy['verification_provider'],
            'expires_at' => $providerData['expires_at'] ?? null,
            'status' => 'created',
        ];
    }

    /**
     * Handle a verification result (from webhook or polling).
     *
     * @param int    $sessionId     identity_verification_sessions.id
     * @param string $status        'passed', 'failed', 'processing', 'expired'
     * @param array  $result        Normalized result from provider
     */
    public static function handleVerificationResult(int $sessionId, string $status, array $result): void
    {
        $session = IdentityVerificationSessionService::getById($sessionId);
        if (!$session) {
            error_log("[RegistrationOrchestrationService] Session {$sessionId} not found");
            return;
        }

        $tenantId = (int) $session['tenant_id'];
        $userId = (int) $session['user_id'];

        // Update session
        IdentityVerificationSessionService::updateStatus(
            $sessionId,
            $status,
            isset($result['decision']) ? json_encode($result) : null,
            $result['provider_reference'] ?? null,
            $result['failure_reason'] ?? null
        );

        // Map status to event type
        $eventMap = [
            'passed' => IdentityVerificationEventService::EVENT_VERIFICATION_PASSED,
            'failed' => IdentityVerificationEventService::EVENT_VERIFICATION_FAILED,
            'processing' => IdentityVerificationEventService::EVENT_VERIFICATION_PROCESSING,
            'expired' => IdentityVerificationEventService::EVENT_VERIFICATION_EXPIRED,
        ];
        $eventType = $eventMap[$status] ?? IdentityVerificationEventService::EVENT_VERIFICATION_PROCESSING;

        IdentityVerificationEventService::log(
            $tenantId,
            $userId,
            $eventType,
            $sessionId,
            null,
            IdentityVerificationEventService::ACTOR_WEBHOOK,
            $result
        );

        // Apply post-verification action
        if (in_array($status, ['passed', 'failed'], true)) {
            self::applyPostVerificationAction($userId, $tenantId, $status);
        }
    }

    /**
     * Apply the post-verification action based on tenant policy.
     */
    public static function applyPostVerificationAction(int $userId, int $tenantId, string $verificationStatus): void
    {
        $policy = RegistrationPolicyService::getEffectivePolicy($tenantId);

        // Update user verification status
        $dbStatus = $verificationStatus === 'passed' ? 'passed' : 'failed';
        $completedAt = date('Y-m-d H:i:s');
        Database::query(
            "UPDATE users SET verification_status = ?, verification_completed_at = ? WHERE id = ? AND tenant_id = ?",
            [$dbStatus, $completedAt, $userId, $tenantId]
        );

        if ($verificationStatus === 'passed') {
            // Send verification passed email
            \Nexus\Services\NotificationDispatcher::dispatchVerificationPassed($userId);

            switch ($policy['post_verification']) {
                case 'activate':
                    // Auto-activate the user
                    Database::query(
                        "UPDATE users SET is_approved = 1 WHERE id = ? AND tenant_id = ?",
                        [$userId, $tenantId]
                    );
                    IdentityVerificationEventService::log(
                        $tenantId,
                        $userId,
                        IdentityVerificationEventService::EVENT_ACCOUNT_ACTIVATED,
                        null, null,
                        IdentityVerificationEventService::ACTOR_SYSTEM,
                        ['reason' => 'verification_passed', 'post_action' => 'activate']
                    );
                    break;

                case 'admin_approval':
                    // Keep is_approved=0, admin must still approve
                    IdentityVerificationEventService::log(
                        $tenantId,
                        $userId,
                        IdentityVerificationEventService::EVENT_ADMIN_REVIEW_STARTED,
                        null, null,
                        IdentityVerificationEventService::ACTOR_SYSTEM,
                        ['reason' => 'verification_passed_pending_admin']
                    );
                    break;

                case 'limited_access':
                    // Grant limited access (is_approved stays 0 but a special status allows some access)
                    Database::query(
                        "UPDATE users SET status = 'active' WHERE id = ? AND tenant_id = ?",
                        [$userId, $tenantId]
                    );
                    break;
            }
        } else {
            // Verification failed — send email notification
            $failureReason = '';
            $session = IdentityVerificationSessionService::getLatestForUser($tenantId, $userId);
            if ($session) {
                $failureReason = $session['failure_reason'] ?? '';
            }
            \Nexus\Services\NotificationDispatcher::dispatchVerificationFailed($userId, $failureReason);

            if ($policy['post_verification'] === 'reject_on_fail') {
                Database::query(
                    "UPDATE users SET status = 'inactive' WHERE id = ? AND tenant_id = ?",
                    [$userId, $tenantId]
                );
            } elseif ($policy['fallback_mode'] !== 'none') {
                self::triggerFallback($userId, $tenantId, 'verification_failed');
            }
        }
    }

    /**
     * Trigger fallback mode when verification is unavailable or fails.
     */
    public static function triggerFallback(int $userId, int $tenantId, string $reason): array
    {
        $policy = RegistrationPolicyService::getEffectivePolicy($tenantId);

        IdentityVerificationEventService::log(
            $tenantId,
            $userId,
            IdentityVerificationEventService::EVENT_FALLBACK_TRIGGERED,
            null, null,
            IdentityVerificationEventService::ACTOR_SYSTEM,
            ['reason' => $reason, 'fallback_mode' => $policy['fallback_mode']]
        );

        switch ($policy['fallback_mode']) {
            case 'admin_review':
                return [
                    'action' => 'pending_approval',
                    'requires_verification' => false,
                    'requires_approval' => true,
                    'verification_session' => null,
                    'next_steps' => ['Your account will be manually reviewed by an administrator.'],
                    'message' => 'Your account has been queued for manual review.',
                ];

            case 'native_registration':
                // Auto-activate (standard registration without verification)
                Database::query(
                    "UPDATE users SET is_approved = 1, verification_status = 'none' WHERE id = ? AND tenant_id = ?",
                    [$userId, $tenantId]
                );
                return [
                    'action' => 'activated',
                    'requires_verification' => false,
                    'requires_approval' => false,
                    'verification_session' => null,
                    'next_steps' => [],
                    'message' => 'Registration complete!',
                ];

            default:
                return [
                    'action' => 'error',
                    'requires_verification' => false,
                    'requires_approval' => false,
                    'verification_session' => null,
                    'next_steps' => ['Please contact support.'],
                    'message' => 'Identity verification is currently unavailable.',
                ];
        }
    }

    /**
     * Get the current registration/verification status for a user.
     */
    public static function getRegistrationStatus(int $userId, int $tenantId): array
    {
        $user = Database::query(
            "SELECT id, status, is_approved, email_verified_at, verification_status, verification_provider, verification_completed_at
             FROM users WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        )->fetch();

        if (!$user) {
            return ['status' => 'not_found'];
        }

        $policy = RegistrationPolicyService::getEffectivePolicy($tenantId);
        $latestSession = IdentityVerificationSessionService::getLatestForUser($tenantId, $userId);

        return [
            'status' => self::deriveUserState($user, $policy),
            'email_verified' => !empty($user['email_verified_at']),
            'is_approved' => (bool) $user['is_approved'],
            'verification_status' => $user['verification_status'] ?? 'none',
            'verification_provider' => $user['verification_provider'],
            'registration_mode' => $policy['registration_mode'],
            'latest_session' => $latestSession ? [
                'id' => $latestSession['id'],
                'status' => $latestSession['status'],
                'provider' => $latestSession['provider_slug'],
                'created_at' => $latestSession['created_at'],
                'completed_at' => $latestSession['completed_at'],
                'failure_reason' => $latestSession['failure_reason'],
            ] : null,
        ];
    }

    /**
     * Derive the conceptual user state from DB columns.
     */
    private static function deriveUserState(array $user, array $policy): string
    {
        if ($user['status'] === 'banned') return 'banned';
        if ($user['status'] === 'suspended') return 'suspended';

        $verificationStatus = $user['verification_status'] ?? 'none';

        if ($verificationStatus === 'pending') return 'pending_verification';
        if ($verificationStatus === 'failed') return 'verification_failed';

        if (empty($user['is_approved'])) return 'pending_admin_review';

        return 'active';
    }

    // ─── Private helpers ─────────────────────────────────────────────────

    private static function handleOpenRegistration(int $userId, int $tenantId, array $policy): array
    {
        // Open registration: no gates, activate immediately
        Database::query(
            "UPDATE users SET is_approved = 1 WHERE id = ? AND tenant_id = ?",
            [$userId, $tenantId]
        );

        IdentityVerificationEventService::log(
            $tenantId,
            $userId,
            IdentityVerificationEventService::EVENT_ACCOUNT_ACTIVATED,
            null, null,
            IdentityVerificationEventService::ACTOR_SYSTEM,
            ['reason' => 'open_registration']
        );

        return [
            'action' => 'activated',
            'requires_verification' => false,
            'requires_approval' => false,
            'verification_session' => null,
            'next_steps' => [],
            'message' => 'Registration complete! Welcome aboard.',
        ];
    }

    private static function handleOpenWithApproval(int $userId, int $tenantId, array $policy): array
    {
        // User stays is_approved=0, admin must approve
        return [
            'action' => 'pending_approval',
            'requires_verification' => false,
            'requires_approval' => true,
            'verification_session' => null,
            'next_steps' => ['Your account will be reviewed by a community administrator.'],
            'message' => 'Registration successful! Your account is pending admin approval.',
        ];
    }

    private static function handleVerifiedIdentity(int $userId, int $tenantId, array $policy): array
    {
        if (!$policy['verification_provider']) {
            // No provider configured, fall back
            if ($policy['fallback_mode'] !== 'none') {
                return self::triggerFallback($userId, $tenantId, 'no_provider_configured');
            }
            // Default to admin approval if no fallback
            return self::handleOpenWithApproval($userId, $tenantId, $policy);
        }

        // Mark user as pending verification
        Database::query(
            "UPDATE users SET verification_status = 'pending', verification_provider = ? WHERE id = ? AND tenant_id = ?",
            [$policy['verification_provider'], $userId, $tenantId]
        );

        return [
            'action' => 'pending_verification',
            'requires_verification' => true,
            'requires_approval' => $policy['post_verification'] === 'admin_approval',
            'verification_session' => null, // Session created when user starts verification
            'next_steps' => ['Please complete identity verification to activate your account.'],
            'message' => 'Registration successful! Please verify your identity to continue.',
        ];
    }

    private static function handleInviteOnly(int $userId, int $tenantId, array $policy): array
    {
        // Invite-only: user should not have gotten here without an invite code,
        // but if they did, keep them pending
        return [
            'action' => 'pending_approval',
            'requires_verification' => false,
            'requires_approval' => true,
            'verification_session' => null,
            'next_steps' => ['Your account is pending review.'],
            'message' => 'Registration received. An administrator will review your request.',
        ];
    }
}
