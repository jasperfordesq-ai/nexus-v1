<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * ExchangeWorkflowService — Laravel DI wrapper for legacy \Nexus\Services\ExchangeWorkflowService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class ExchangeWorkflowService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::initiate().
     */
    public function initiate(int $tenantId, int $listingId, int $requesterId): ?int
    {
        return \Nexus\Services\ExchangeWorkflowService::initiate($tenantId, $listingId, $requesterId);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::accept().
     */
    public function accept(int $tenantId, int $exchangeId, int $userId): bool
    {
        return \Nexus\Services\ExchangeWorkflowService::accept($tenantId, $exchangeId, $userId);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::complete().
     */
    public function complete(int $tenantId, int $exchangeId, int $userId, float $hours): bool
    {
        return \Nexus\Services\ExchangeWorkflowService::complete($tenantId, $exchangeId, $userId, $hours);
    }

    /**
     * Delegates to legacy ExchangeWorkflowService::cancel().
     */
    public function cancel(int $tenantId, int $exchangeId, int $userId, ?string $reason = null): bool
    {
        return \Nexus\Services\ExchangeWorkflowService::cancel($tenantId, $exchangeId, $userId, $reason);
    }
}
