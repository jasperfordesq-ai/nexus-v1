<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * SentryService — Laravel DI wrapper for legacy \Nexus\Services\SentryService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class SentryService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy SentryService::init().
     */
    public function init(): void
    {
        \Nexus\Services\SentryService::init();
    }

    /**
     * Delegates to legacy SentryService::isEnabled().
     */
    public function isEnabled(): bool
    {
        return \Nexus\Services\SentryService::isEnabled();
    }

    /**
     * Delegates to legacy SentryService::setUser().
     */
    public function setUser(int $userId, ?string $email = null, ?string $username = null): void
    {
        \Nexus\Services\SentryService::setUser($userId, $email, $username);
    }

    /**
     * Delegates to legacy SentryService::setTenant().
     */
    public function setTenant(int $tenantId, ?string $tenantName = null): void
    {
        \Nexus\Services\SentryService::setTenant($tenantId, $tenantName);
    }

    /**
     * Delegates to legacy SentryService::setRequestContext().
     */
    public function setRequestContext(array $requestData): void
    {
        \Nexus\Services\SentryService::setRequestContext($requestData);
    }
}
