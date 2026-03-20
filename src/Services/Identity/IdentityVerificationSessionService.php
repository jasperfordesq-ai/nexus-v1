<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

/**
 * IdentityVerificationSessionService — Thin delegate forwarding to \App\Services\Identity\IdentityVerificationSessionService.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\Identity\IdentityVerificationSessionService
 */
class IdentityVerificationSessionService
{

    public static function create(int $tenantId,
        int $userId,
        string $providerSlug,
        string $verificationLevel,
        array $providerData): int
    {
        return \App\Services\Identity\IdentityVerificationSessionService::create($tenantId, $userId, $providerSlug, $verificationLevel, $providerData);
    }

    public static function getById(int $sessionId): ?array
    {
        return \App\Services\Identity\IdentityVerificationSessionService::getById($sessionId);
    }

    public static function findByProviderSession(string $providerSlug, string $providerSessionId): ?array
    {
        return \App\Services\Identity\IdentityVerificationSessionService::findByProviderSession($providerSlug, $providerSessionId);
    }

    public static function getLatestForUser(int $tenantId, int $userId): ?array
    {
        return \App\Services\Identity\IdentityVerificationSessionService::getLatestForUser($tenantId, $userId);
    }

    public static function getAllForUser(int $tenantId, int $userId): array
    {
        return \App\Services\Identity\IdentityVerificationSessionService::getAllForUser($tenantId, $userId);
    }

    public static function updateStatus(int $sessionId,
        string $status,
        ?string $resultSummary = null,
        ?string $providerReference = null,
        ?string $failureReason = null): void
    {
        \App\Services\Identity\IdentityVerificationSessionService::updateStatus($sessionId, $status, $resultSummary, $providerReference, $failureReason);
    }

    public static function getPendingForTenant(int $tenantId, int $limit = 50, int $offset = 0): array
    {
        return \App\Services\Identity\IdentityVerificationSessionService::getPendingForTenant($tenantId, $limit, $offset);
    }

    public static function getAbandoned(int $hoursOld = 24, int $limit = 100): array
    {
        return \App\Services\Identity\IdentityVerificationSessionService::getAbandoned($hoursOld, $limit);
    }

    public static function markReminderSent(int $sessionId): void
    {
        \App\Services\Identity\IdentityVerificationSessionService::markReminderSent($sessionId);
    }

    public static function expireAbandoned(int $hoursOld = 72): int
    {
        return \App\Services\Identity\IdentityVerificationSessionService::expireAbandoned($hoursOld);
    }

    public static function purgeOldSessions(int $retentionDays = 180): int
    {
        return \App\Services\Identity\IdentityVerificationSessionService::purgeOldSessions($retentionDays);
    }
}
