<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * SmartMatchingEngine — Laravel DI wrapper for legacy \Nexus\Services\SmartMatchingEngine.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class SmartMatchingEngine
{
    public function __construct()
    {
    }

    /**
     * Delegates to legacy SmartMatchingEngine::getConfig().
     */
    public function getConfig(): array
    {
        if (!class_exists('\Nexus\Services\SmartMatchingEngine')) { return []; }
        return \Nexus\Services\SmartMatchingEngine::getConfig();
    }

    /**
     * Delegates to legacy SmartMatchingEngine::clearCache().
     */
    public function clearCache(): void
    {
        if (!class_exists('\Nexus\Services\SmartMatchingEngine')) { return; }
        \Nexus\Services\SmartMatchingEngine::clearCache();
    }

    /**
     * Delegates to legacy SmartMatchingEngine::findMatchesForUser().
     */
    public function findMatchesForUser(int $userId, array $options = []): array
    {
        if (!class_exists('\Nexus\Services\SmartMatchingEngine')) { return []; }
        return \Nexus\Services\SmartMatchingEngine::findMatchesForUser($userId, $options);
    }

    /**
     * Delegates to legacy SmartMatchingEngine::getHotMatches().
     */
    public function getHotMatches(int $userId, int $limit = 5): array
    {
        if (!class_exists('\Nexus\Services\SmartMatchingEngine')) { return []; }
        return \Nexus\Services\SmartMatchingEngine::getHotMatches($userId, $limit);
    }

    /**
     * Delegates to legacy SmartMatchingEngine::getMutualMatches().
     */
    public function getMutualMatches(int $userId, int $limit = 10): array
    {
        if (!class_exists('\Nexus\Services\SmartMatchingEngine')) { return []; }
        return \Nexus\Services\SmartMatchingEngine::getMutualMatches($userId, $limit);
    }
}
