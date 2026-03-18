<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * AdminBadgeCountService — Laravel DI wrapper for legacy \Nexus\Services\AdminBadgeCountService.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class AdminBadgeCountService
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy AdminBadgeCountService::getCounts().
     */
    public function getCounts(): array
    {
        if (!class_exists('\Nexus\Services\AdminBadgeCountService')) { return []; }
        return \Nexus\Services\AdminBadgeCountService::getCounts();
    }

    /**
     * Delegates to legacy AdminBadgeCountService::getCount().
     */
    public function getCount(string $key): int
    {
        if (!class_exists('\Nexus\Services\AdminBadgeCountService')) { return 0; }
        return \Nexus\Services\AdminBadgeCountService::getCount($key);
    }

    /**
     * Delegates to legacy AdminBadgeCountService::clearCache().
     */
    public function clearCache(): void
    {
        if (!class_exists('\Nexus\Services\AdminBadgeCountService')) { return; }
        \Nexus\Services\AdminBadgeCountService::clearCache();
    }
}
