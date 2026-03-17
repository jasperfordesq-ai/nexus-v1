<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\Identity;

use Nexus\Services\Identity\IdentityVerificationEventService as LegacyService;

/**
 * IdentityVerificationEventService — Laravel DI wrapper for legacy service.
 *
 * Delegates to \Nexus\Services\Identity\IdentityVerificationEventService.
 */
class IdentityVerificationEventService
{
    /** Event types — mirrored from legacy */
    public const EVENT_REGISTRATION_STARTED = LegacyService::EVENT_REGISTRATION_STARTED;
    public const EVENT_VERIFICATION_CREATED = LegacyService::EVENT_VERIFICATION_CREATED;
    public const EVENT_VERIFICATION_STARTED = LegacyService::EVENT_VERIFICATION_STARTED;
    public const EVENT_VERIFICATION_PROCESSING = LegacyService::EVENT_VERIFICATION_PROCESSING;
    public const EVENT_VERIFICATION_PASSED = LegacyService::EVENT_VERIFICATION_PASSED;
    public const EVENT_VERIFICATION_FAILED = LegacyService::EVENT_VERIFICATION_FAILED;
    public const EVENT_VERIFICATION_EXPIRED = LegacyService::EVENT_VERIFICATION_EXPIRED;
    public const EVENT_VERIFICATION_CANCELLED = LegacyService::EVENT_VERIFICATION_CANCELLED;
    public const EVENT_ADMIN_REVIEW_STARTED = LegacyService::EVENT_ADMIN_REVIEW_STARTED;
    public const EVENT_ADMIN_APPROVED = LegacyService::EVENT_ADMIN_APPROVED;
    public const EVENT_ADMIN_REJECTED = LegacyService::EVENT_ADMIN_REJECTED;
    public const EVENT_ACCOUNT_ACTIVATED = LegacyService::EVENT_ACCOUNT_ACTIVATED;
    public const EVENT_FALLBACK_TRIGGERED = LegacyService::EVENT_FALLBACK_TRIGGERED;

    /** Actor types — mirrored from legacy */
    public const ACTOR_SYSTEM = LegacyService::ACTOR_SYSTEM;
    public const ACTOR_USER = LegacyService::ACTOR_USER;
    public const ACTOR_ADMIN = LegacyService::ACTOR_ADMIN;
    public const ACTOR_WEBHOOK = LegacyService::ACTOR_WEBHOOK;

    public function __construct()
    {
    }

    /**
     * Log an identity verification event.
     */
    public function log(
        int $tenantId,
        int $userId,
        string $eventType,
        ?int $sessionId = null,
        ?int $actorId = null,
        string $actorType = self::ACTOR_SYSTEM,
        ?array $details = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        LegacyService::log($tenantId, $userId, $eventType, $sessionId, $actorId, $actorType, $details, $ipAddress, $userAgent);
    }

    /**
     * Get verification events for a user (for admin review).
     */
    public function getForUser(int $tenantId, int $userId, int $limit = 50): array
    {
        return LegacyService::getForUser($tenantId, $userId, $limit);
    }

    /**
     * Get events for a specific verification session.
     */
    public function getForSession(int $sessionId): array
    {
        return LegacyService::getForSession($sessionId);
    }

    /**
     * Get all verification events for a tenant (admin audit log).
     *
     * @return array{events: array, total: int}
     */
    public function getForTenant(int $tenantId, int $limit = 50, int $offset = 0, ?string $eventType = null): array
    {
        return LegacyService::getForTenant($tenantId, $limit, $offset, $eventType);
    }
}
