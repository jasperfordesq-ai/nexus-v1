<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Middleware;

use App\Middleware\GroupFeatureMiddleware as AppGroupFeatureMiddleware;

/**
 * Legacy delegate — real implementation is now in App\Middleware\GroupFeatureMiddleware.
 *
 * @deprecated Use App\Middleware\GroupFeatureMiddleware directly.
 */
class GroupFeatureMiddleware
{
    public static function checkGroupsEnabled()
    {
        return AppGroupFeatureMiddleware::checkGroupsEnabled();
    }

    public static function checkFeature($feature, $customMessage = null)
    {
        return AppGroupFeatureMiddleware::checkFeature($feature, $customMessage);
    }

    public static function requireGroups()
    {
        AppGroupFeatureMiddleware::requireGroups();
    }

    public static function requireFeature($feature, $customMessage = null)
    {
        AppGroupFeatureMiddleware::requireFeature($feature, $customMessage);
    }

    public static function checkFeatures(array $features)
    {
        return AppGroupFeatureMiddleware::checkFeatures($features);
    }

    public static function checkAnyFeature(array $features)
    {
        return AppGroupFeatureMiddleware::checkAnyFeature($features);
    }

    public static function can($feature)
    {
        return AppGroupFeatureMiddleware::can($feature);
    }

    public static function gates(array $features)
    {
        return AppGroupFeatureMiddleware::gates($features);
    }
}
