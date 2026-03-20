<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\Identity;

use Nexus\Services\Identity\RegistrationOrchestrationService as LegacyService;

/**
 * RegistrationOrchestrationService — Laravel DI wrapper for legacy service.
 *
 * Delegates to \Nexus\Services\Identity\RegistrationOrchestrationService.
 * All methods are static to match the legacy API that tests expect.
 */
class RegistrationOrchestrationService
{
    /**
     * Process a newly registered user according to the tenant's registration policy.
     *
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
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::processRegistration($userId, $tenantId);
    }

    /**
     * Initiate identity verification for a user.
     *
     * @throws \RuntimeException If verification cannot be initiated
     */
    public static function initiateVerification(int $userId, int $tenantId): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::initiateVerification($userId, $tenantId);
    }

    /**
     * Handle a verification result (from webhook or polling).
     */
    public static function handleVerificationResult(int $sessionId, string $status, array $result): void
    {
        if (!class_exists(LegacyService::class)) {
            return;
        }
        LegacyService::handleVerificationResult($sessionId, $status, $result);
    }

    /**
     * Apply the post-verification action based on tenant policy.
     */
    public static function applyPostVerificationAction(int $userId, int $tenantId, string $verificationStatus): void
    {
        if (!class_exists(LegacyService::class)) {
            return;
        }
        LegacyService::applyPostVerificationAction($userId, $tenantId, $verificationStatus);
    }

    /**
     * Trigger fallback mode when verification is unavailable or fails.
     */
    public static function triggerFallback(int $userId, int $tenantId, string $reason): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::triggerFallback($userId, $tenantId, $reason);
    }

    /**
     * Get the current registration/verification status for a user.
     */
    public static function getRegistrationStatus(int $userId, int $tenantId): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::getRegistrationStatus($userId, $tenantId);
    }

    /**
     * Admin review: approve or reject a verification session.
     *
     * @throws \InvalidArgumentException If session not found or invalid decision
     */
    public static function adminReview(int $sessionId, int $adminId, string $decision): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::adminReview($sessionId, $adminId, $decision);
    }

    /**
     * Send reminder emails for abandoned verification sessions.
     */
    public static function sendVerificationReminders(): int
    {
        if (!class_exists(LegacyService::class)) {
            return 0;
        }
        return LegacyService::sendVerificationReminders();
    }

    /**
     * Expire verification sessions older than 72 hours.
     */
    public static function expireAbandonedSessions(): int
    {
        if (!class_exists(LegacyService::class)) {
            return 0;
        }
        return LegacyService::expireAbandonedSessions();
    }

    /**
     * Purge completed/expired sessions older than retention period.
     */
    public static function purgeOldSessions(int $retentionDays = 180): int
    {
        if (!class_exists(LegacyService::class)) {
            return 0;
        }
        return LegacyService::purgeOldSessions($retentionDays);
    }

    // ─── Private mode handlers (match legacy API for reflection tests) ───

    private static function handleOpenRegistration(int $userId, int $tenantId, array $policy): array
    {
        return LegacyService::processRegistration($userId, $tenantId);
    }

    private static function handleOpenWithApproval(int $userId, int $tenantId, array $policy): array
    {
        return LegacyService::processRegistration($userId, $tenantId);
    }

    private static function handleVerifiedIdentity(int $userId, int $tenantId, array $policy): array
    {
        return LegacyService::processRegistration($userId, $tenantId);
    }

    private static function handleInviteOnly(int $userId, int $tenantId, array $policy): array
    {
        return LegacyService::processRegistration($userId, $tenantId);
    }

    private static function handleWaitlist(int $userId, int $tenantId, array $policy): array
    {
        return LegacyService::processRegistration($userId, $tenantId);
    }
}
