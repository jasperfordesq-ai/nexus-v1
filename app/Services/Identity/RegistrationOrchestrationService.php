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
 */
class RegistrationOrchestrationService
{
    public function __construct()
    {
    }

    /**
     * Process a newly registered user according to the tenant's registration policy.
     */
    public function processRegistration(int $userId, int $tenantId): array
    {
        return LegacyService::processRegistration($userId, $tenantId);
    }

    /**
     * Initiate identity verification for a user.
     *
     * @throws \RuntimeException If verification cannot be initiated
     */
    public function initiateVerification(int $userId, int $tenantId): array
    {
        return LegacyService::initiateVerification($userId, $tenantId);
    }

    /**
     * Handle a verification result (from webhook or polling).
     */
    public function handleVerificationResult(int $sessionId, string $status, array $result): void
    {
        LegacyService::handleVerificationResult($sessionId, $status, $result);
    }

    /**
     * Apply the post-verification action based on tenant policy.
     */
    public function applyPostVerificationAction(int $userId, int $tenantId, string $verificationStatus): void
    {
        LegacyService::applyPostVerificationAction($userId, $tenantId, $verificationStatus);
    }

    /**
     * Trigger fallback mode when verification is unavailable or fails.
     */
    public function triggerFallback(int $userId, int $tenantId, string $reason): array
    {
        return LegacyService::triggerFallback($userId, $tenantId, $reason);
    }

    /**
     * Get the current registration/verification status for a user.
     */
    public function getRegistrationStatus(int $userId, int $tenantId): array
    {
        return LegacyService::getRegistrationStatus($userId, $tenantId);
    }

    /**
     * Admin review: approve or reject a verification session.
     *
     * @throws \InvalidArgumentException If session not found or invalid decision
     */
    public function adminReview(int $sessionId, int $adminId, string $decision): array
    {
        return LegacyService::adminReview($sessionId, $adminId, $decision);
    }

    /**
     * Send reminder emails for abandoned verification sessions.
     */
    public function sendVerificationReminders(): int
    {
        return LegacyService::sendVerificationReminders();
    }

    /**
     * Expire verification sessions older than 72 hours.
     */
    public function expireAbandonedSessions(): int
    {
        return LegacyService::expireAbandonedSessions();
    }

    /**
     * Purge completed/expired sessions older than retention period.
     */
    public function purgeOldSessions(int $retentionDays = 180): int
    {
        return LegacyService::purgeOldSessions($retentionDays);
    }
}
