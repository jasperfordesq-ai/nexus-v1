<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\Identity;

use Nexus\Services\Identity\IdentityVerificationSessionService as LegacyService;

/**
 * IdentityVerificationSessionService — Laravel DI wrapper for legacy service.
 *
 * Delegates to \Nexus\Services\Identity\IdentityVerificationSessionService.
 */
class IdentityVerificationSessionService
{
    public function __construct()
    {
    }

    /**
     * Create a new verification session record.
     */
    public function create(int $tenantId, int $userId, string $providerSlug, string $verificationLevel, array $providerData): int
    {
        return LegacyService::create($tenantId, $userId, $providerSlug, $verificationLevel, $providerData);
    }

    /**
     * Get a session by ID.
     */
    public function getById(int $sessionId): ?array
    {
        return LegacyService::getById($sessionId);
    }

    /**
     * Find a session by provider session ID and provider slug.
     */
    public function findByProviderSession(string $providerSlug, string $providerSessionId): ?array
    {
        return LegacyService::findByProviderSession($providerSlug, $providerSessionId);
    }

    /**
     * Get the latest session for a user.
     */
    public function getLatestForUser(int $tenantId, int $userId): ?array
    {
        return LegacyService::getLatestForUser($tenantId, $userId);
    }

    /**
     * Get all sessions for a user.
     */
    public function getAllForUser(int $tenantId, int $userId): array
    {
        return LegacyService::getAllForUser($tenantId, $userId);
    }

    /**
     * Update session status.
     */
    public function updateStatus(int $sessionId, string $status, ?string $resultSummary = null, ?string $providerReference = null, ?string $failureReason = null): void
    {
        LegacyService::updateStatus($sessionId, $status, $resultSummary, $providerReference, $failureReason);
    }

    /**
     * Get sessions pending for a tenant (admin view).
     */
    public function getPendingForTenant(int $tenantId, int $limit = 50, int $offset = 0): array
    {
        return LegacyService::getPendingForTenant($tenantId, $limit, $offset);
    }

    /**
     * Get abandoned sessions older than $hoursOld hours.
     */
    public function getAbandoned(int $hoursOld = 24, int $limit = 100): array
    {
        return LegacyService::getAbandoned($hoursOld, $limit);
    }

    /**
     * Mark a session as having had a reminder sent.
     */
    public function markReminderSent(int $sessionId): void
    {
        LegacyService::markReminderSent($sessionId);
    }

    /**
     * Expire sessions older than the given hours that are still in created/started status.
     */
    public function expireAbandoned(int $hoursOld = 72): int
    {
        return LegacyService::expireAbandoned($hoursOld);
    }

    /**
     * Delete completed/expired sessions older than the retention period.
     */
    public function purgeOldSessions(int $retentionDays = 180): int
    {
        return LegacyService::purgeOldSessions($retentionDays);
    }
}
