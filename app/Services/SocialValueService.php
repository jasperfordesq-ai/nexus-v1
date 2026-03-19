<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * SocialValueService — Laravel DI wrapper for legacy \Nexus\Services\SocialValueService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class SocialValueService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy SocialValueService::calculateSROI().
     */
    public function calculateSROI(int $tenantId, array $dateRange = []): array
    {
        return \Nexus\Services\SocialValueService::calculateSROI($tenantId, $dateRange);
    }

    /**
     * Delegates to legacy SocialValueService::getConfig().
     */
    public function getConfig(int $tenantId): array
    {
        return \Nexus\Services\SocialValueService::getConfig($tenantId);
    }

    /**
     * Delegates to legacy SocialValueService::saveConfig().
     */
    public function saveConfig(int $tenantId, array $config): bool
    {
        return \Nexus\Services\SocialValueService::saveConfig($tenantId, $config);
    }
}
