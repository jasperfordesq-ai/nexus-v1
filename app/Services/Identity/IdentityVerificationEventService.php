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
    /** Event types (inlined — safe when legacy is removed) */
    public const EVENT_REGISTRATION_STARTED = 'registration_started';
    public const EVENT_VERIFICATION_CREATED = 'verification_created';
    public const EVENT_VERIFICATION_STARTED = 'verification_started';
    public const EVENT_VERIFICATION_PROCESSING = 'verification_processing';
    public const EVENT_VERIFICATION_PASSED = 'verification_passed';
    public const EVENT_VERIFICATION_FAILED = 'verification_failed';
    public const EVENT_VERIFICATION_EXPIRED = 'verification_expired';
    public const EVENT_VERIFICATION_CANCELLED = 'verification_cancelled';
    public const EVENT_ADMIN_REVIEW_STARTED = 'admin_review_started';
    public const EVENT_ADMIN_APPROVED = 'admin_approved';
    public const EVENT_ADMIN_REJECTED = 'admin_rejected';
    public const EVENT_ACCOUNT_ACTIVATED = 'account_activated';
    public const EVENT_FALLBACK_TRIGGERED = 'fallback_triggered';

    /** Actor types (inlined — safe when legacy is removed) */
    public const ACTOR_SYSTEM = 'system';
    public const ACTOR_USER = 'user';
    public const ACTOR_ADMIN = 'admin';
    public const ACTOR_WEBHOOK = 'webhook';

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
        if (!class_exists(LegacyService::class)) {
            return;
        }
        LegacyService::log($tenantId, $userId, $eventType, $sessionId, $actorId, $actorType, $details, $ipAddress, $userAgent);
    }

    /**
     * Get verification events for a user (for admin review).
     */
    public function getForUser(int $tenantId, int $userId, int $limit = 50): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::getForUser($tenantId, $userId, $limit);
    }

    /**
     * Get events for a specific verification session.
     */
    public function getForSession(int $sessionId): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::getForSession($sessionId);
    }

    /**
     * Get all verification events for a tenant (admin audit log).
     *
     * @return array{events: array, total: int}
     */
    public function getForTenant(int $tenantId, int $limit = 50, int $offset = 0, ?string $eventType = null): array
    {
        if (!class_exists(LegacyService::class)) {
            return [];
        }
        return LegacyService::getForTenant($tenantId, $limit, $offset, $eventType);
    }
}
