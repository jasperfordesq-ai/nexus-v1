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
 * All methods are static to match the legacy API that tests expect.
 */
class IdentityVerificationSessionService
{
    /**
     * Create a new verification session record.
     */
    public static function create(int $tenantId, int $userId, string $providerSlug, string $verificationLevel, array $providerData): int
    {
        if (!class_exists(LegacyService::class)) {
            return 0;
        }
        return LegacyService::create($tenantId, $userId, $providerSlug, $verificationLevel, $providerData);
    }

    /**
     * Get a session by ID.
     */
    public static function getById(int $sessionId): ?array
    {
        if (!class_exists(LegacyService::class)) {
            return null;
        }
        return LegacyService::getById($sessionId);
    }

    /**
     * Find a session by provider session ID and provider slug.
     */
    public static function findByProviderSession(string $providerSlug, string $providerSessionId): ?array
    {
        if (!class_exists(LegacyService::class)) {
            return null;
        }
        return LegacyService::findByProviderSession($providerSlug, $providerSessionId);
    }

    /**
     * Get the latest session for a user.
     */
    public static function getLatestForUser(int $tenantId, int $userId): ?array
    {
        if (!class_exists(LegacyService::class)) {
            return null;
        }
        return LegacyService::getLatestForUser($tenantId, $userId);
    }

    /**
     * Get all sessions for a user.
     */
    public static function getAllForUser(int $tenantId, int $userId): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::getAllForUser($tenantId, $userId);
    }

    /**
     * Update session status.
     */
    public static function updateStatus(int $sessionId, string $status, ?string $resultSummary = null, ?string $providerReference = null, ?string $failureReason = null): void
    {
        if (!class_exists(LegacyService::class)) {
            return;
        }
        LegacyService::updateStatus($sessionId, $status, $resultSummary, $providerReference, $failureReason);
    }

    /**
     * Get sessions pending for a tenant (admin view).
     */
    public static function getPendingForTenant(int $tenantId, int $limit = 50, int $offset = 0): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::getPendingForTenant($tenantId, $limit, $offset);
    }

    /**
     * Get abandoned sessions older than $hoursOld hours.
     */
    public static function getAbandoned(int $hoursOld = 24, int $limit = 100): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::getAbandoned($hoursOld, $limit);
    }

    /**
     * Mark a session as having had a reminder sent.
     */
    public static function markReminderSent(int $sessionId): void
    {
        if (!class_exists(LegacyService::class)) {
            return;
        }
        LegacyService::markReminderSent($sessionId);
    }

    /**
     * Expire sessions older than the given hours that are still in created/started status.
     */
    public static function expireAbandoned(int $hoursOld = 72): int
    {
        if (!class_exists(LegacyService::class)) {
            return 0;
        }
        return LegacyService::expireAbandoned($hoursOld);
    }

    /**
     * Delete completed/expired sessions older than the retention period.
     */
    public static function purgeOldSessions(int $retentionDays = 180): int
    {
        if (!class_exists(LegacyService::class)) {
            return 0;
        }
        return LegacyService::purgeOldSessions($retentionDays);
    }
}
