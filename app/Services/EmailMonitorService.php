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
        static::recordEmailSendStatic($provider, $success, $tenantId);
    }

    /**
     * Static proxy for recordEmailSend — used by code that cannot inject an instance.
     */
    public static function recordEmailSendStatic(string $provider, bool $success, ?int $tenantId = null): void
    {
        try {
            \Nexus\Services\EmailMonitorService::recordEmailSend($provider, $success, $tenantId);
        } catch (\Throwable $e) {
            // Non-fatal — monitoring should never break sending
        }
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

    /**
     * Get email health summary.
     */
    public function getHealthSummary(?int $tenantId = null): array
    {
        return \Nexus\Services\EmailMonitorService::getHealthSummary($tenantId);
    }
}
