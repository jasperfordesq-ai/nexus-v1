<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services\Identity;

/**
 * IdentityVerificationEventService — Thin delegate forwarding to \App\Services\Identity\IdentityVerificationEventService.
 *
 * The full implementation now lives in the App namespace.
 * This file exists for backwards compatibility only.
 *
 * @see \App\Services\Identity\IdentityVerificationEventService
 */
class IdentityVerificationEventService
{
    public const EVENT_REGISTRATION_STARTED = \App\Services\Identity\IdentityVerificationEventService::EVENT_REGISTRATION_STARTED;
    public const EVENT_VERIFICATION_CREATED = \App\Services\Identity\IdentityVerificationEventService::EVENT_VERIFICATION_CREATED;
    public const EVENT_VERIFICATION_STARTED = \App\Services\Identity\IdentityVerificationEventService::EVENT_VERIFICATION_STARTED;
    public const EVENT_VERIFICATION_PROCESSING = \App\Services\Identity\IdentityVerificationEventService::EVENT_VERIFICATION_PROCESSING;
    public const EVENT_VERIFICATION_PASSED = \App\Services\Identity\IdentityVerificationEventService::EVENT_VERIFICATION_PASSED;
    public const EVENT_VERIFICATION_FAILED = \App\Services\Identity\IdentityVerificationEventService::EVENT_VERIFICATION_FAILED;
    public const EVENT_VERIFICATION_EXPIRED = \App\Services\Identity\IdentityVerificationEventService::EVENT_VERIFICATION_EXPIRED;
    public const EVENT_VERIFICATION_CANCELLED = \App\Services\Identity\IdentityVerificationEventService::EVENT_VERIFICATION_CANCELLED;
    public const EVENT_ADMIN_REVIEW_STARTED = \App\Services\Identity\IdentityVerificationEventService::EVENT_ADMIN_REVIEW_STARTED;
    public const EVENT_ADMIN_APPROVED = \App\Services\Identity\IdentityVerificationEventService::EVENT_ADMIN_APPROVED;
    public const EVENT_ADMIN_REJECTED = \App\Services\Identity\IdentityVerificationEventService::EVENT_ADMIN_REJECTED;
    public const EVENT_ACCOUNT_ACTIVATED = \App\Services\Identity\IdentityVerificationEventService::EVENT_ACCOUNT_ACTIVATED;
    public const EVENT_FALLBACK_TRIGGERED = \App\Services\Identity\IdentityVerificationEventService::EVENT_FALLBACK_TRIGGERED;
    public const ACTOR_SYSTEM = \App\Services\Identity\IdentityVerificationEventService::ACTOR_SYSTEM;
    public const ACTOR_USER = \App\Services\Identity\IdentityVerificationEventService::ACTOR_USER;
    public const ACTOR_ADMIN = \App\Services\Identity\IdentityVerificationEventService::ACTOR_ADMIN;
    public const ACTOR_WEBHOOK = \App\Services\Identity\IdentityVerificationEventService::ACTOR_WEBHOOK;

    public static function log(int $tenantId,
        int $userId,
        string $eventType,
        ?int $sessionId = null,
        ?int $actorId = null,
        string $actorType = self::ACTOR_SYSTEM,
        ?array $details = null,
        ?string $ipAddress = null,
        ?string $userAgent = null): void
    {
        \App\Services\Identity\IdentityVerificationEventService::log($tenantId, $userId, $eventType, $sessionId, $actorId, $actorType, $details, $ipAddress, $userAgent);
    }

    public static function getForUser(int $tenantId, int $userId, int $limit = 50): array
    {
        return \App\Services\Identity\IdentityVerificationEventService::getForUser($tenantId, $userId, $limit);
    }

    public static function getForSession(int $sessionId): array
    {
        return \App\Services\Identity\IdentityVerificationEventService::getForSession($sessionId);
    }

    public static function getForTenant(int $tenantId, int $limit = 50, int $offset = 0, ?string $eventType = null): array
    {
        return \App\Services\Identity\IdentityVerificationEventService::getForTenant($tenantId, $limit, $offset, $eventType);
    }
}
