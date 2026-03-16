<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * EmailMonitorService — Laravel DI wrapper for legacy \Nexus\Services\EmailMonitorService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class EmailMonitorService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy EmailMonitorService::recordEmailSend().
     */
    public function recordEmailSend(string $provider, bool $success, ?int $tenantId = null): void
    {
        \Nexus\Services\EmailMonitorService::recordEmailSend($provider, $success, $tenantId);
    }

    /**
     * Delegates to legacy EmailMonitorService::recordTokenRefresh().
     */
    public function recordTokenRefresh(bool $success, ?int $tenantId = null): void
    {
        \Nexus\Services\EmailMonitorService::recordTokenRefresh($success, $tenantId);
    }

    /**
     * Delegates to legacy EmailMonitorService::recordFallbackToSmtp().
     */
    public function recordFallbackToSmtp(string $reason, ?int $tenantId = null): void
    {
        \Nexus\Services\EmailMonitorService::recordFallbackToSmtp($reason, $tenantId);
    }

    /**
     * Delegates to legacy EmailMonitorService::recordCircuitBreakerOpen().
     */
    public function recordCircuitBreakerOpen(?int $tenantId = null): void
    {
        \Nexus\Services\EmailMonitorService::recordCircuitBreakerOpen($tenantId);
    }

    /**
     * Delegates to legacy EmailMonitorService::recordRateLimitHit().
     */
    public function recordRateLimitHit(?int $tenantId = null): void
    {
        \Nexus\Services\EmailMonitorService::recordRateLimitHit($tenantId);
    }
}
