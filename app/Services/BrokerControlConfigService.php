<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * BrokerControlConfigService — Laravel DI wrapper for legacy \Nexus\Services\BrokerControlConfigService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class BrokerControlConfigService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy BrokerControlConfigService::getConfig().
     */
    public function getConfig(?string $section = null): array
    {
        return \Nexus\Services\BrokerControlConfigService::getConfig($section);
    }

    /**
     * Delegates to legacy BrokerControlConfigService::updateConfig().
     */
    public function updateConfig(array $data): bool
    {
        return \Nexus\Services\BrokerControlConfigService::updateConfig($data);
    }

    /**
     * Delegates to legacy BrokerControlConfigService::updateSection().
     */
    public function updateSection(string $section, array $data): bool
    {
        return \Nexus\Services\BrokerControlConfigService::updateSection($section, $data);
    }

    /**
     * Delegates to legacy BrokerControlConfigService::isDirectMessagingEnabled().
     */
    public function isDirectMessagingEnabled(): bool
    {
        return \Nexus\Services\BrokerControlConfigService::isDirectMessagingEnabled();
    }

    /**
     * Delegates to legacy BrokerControlConfigService::isFirstContactMonitoringEnabled().
     */
    public function isFirstContactMonitoringEnabled(): bool
    {
        return \Nexus\Services\BrokerControlConfigService::isFirstContactMonitoringEnabled();
    }

    /**
     * Delegates to legacy BrokerControlConfigService::isExchangeWorkflowEnabled().
     */
    public function isExchangeWorkflowEnabled(): bool
    {
        return \Nexus\Services\BrokerControlConfigService::isExchangeWorkflowEnabled();
    }

    /**
     * Delegates to legacy BrokerControlConfigService::isVettingEnabled().
     */
    public function isVettingEnabled(): bool
    {
        return \Nexus\Services\BrokerControlConfigService::isVettingEnabled();
    }

    /**
     * Delegates to legacy BrokerControlConfigService::isInsuranceEnabled().
     */
    public function isInsuranceEnabled(): bool
    {
        return \Nexus\Services\BrokerControlConfigService::isInsuranceEnabled();
    }
}
