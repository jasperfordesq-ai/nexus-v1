<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Middleware;

use Nexus\Middleware\GroupFeatureMiddleware as LegacyGroupFeatureMiddleware;

/**
 * App-namespace wrapper for Nexus\Middleware\GroupFeatureMiddleware.
 *
 * Delegates to the legacy implementation.
 */
class GroupFeatureMiddleware
{
    /**
     * @return bool|array
     */
    public static function checkGroupsEnabled()
    {
        if (!class_exists(LegacyGroupFeatureMiddleware::class)) { return true; }
        return LegacyGroupFeatureMiddleware::checkGroupsEnabled();
    }

    /**
     * @return bool|array
     */
    public static function checkFeature($feature, $customMessage = null)
    {
        if (!class_exists(LegacyGroupFeatureMiddleware::class)) { return true; }
        return LegacyGroupFeatureMiddleware::checkFeature($feature, $customMessage);
    }

    public static function requireGroups(): void
    {
        if (!class_exists(LegacyGroupFeatureMiddleware::class)) { return; }
        LegacyGroupFeatureMiddleware::requireGroups();
    }

    public static function requireFeature($feature, $customMessage = null): void
    {
        if (!class_exists(LegacyGroupFeatureMiddleware::class)) { return; }
        LegacyGroupFeatureMiddleware::requireFeature($feature, $customMessage);
    }

    /**
     * @return bool|array
     */
    public static function checkFeatures(array $features)
    {
        if (!class_exists(LegacyGroupFeatureMiddleware::class)) { return true; }
        return LegacyGroupFeatureMiddleware::checkFeatures($features);
    }

    /**
     * @return bool
     */
    public static function checkAnyFeature(array $features)
    {
        if (!class_exists(LegacyGroupFeatureMiddleware::class)) { return false; }
        return LegacyGroupFeatureMiddleware::checkAnyFeature($features);
    }

    /**
     * @return bool
     */
    public static function can($feature)
    {
        if (!class_exists(LegacyGroupFeatureMiddleware::class)) { return false; }
        return LegacyGroupFeatureMiddleware::can($feature);
    }

    /**
     * @return array
     */
    public static function gates(array $features)
    {
        if (!class_exists(LegacyGroupFeatureMiddleware::class)) { return []; }
        return LegacyGroupFeatureMiddleware::gates($features);
    }
}
