<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Middleware;

use Nexus\Services\GroupFeatureToggleService;
use Nexus\Core\TenantContext;

/**
 * GroupFeatureMiddleware
 *
 * Middleware to check if group features are enabled before allowing access.
 * Use in routes to gate group-related functionality.
 */
class GroupFeatureMiddleware
{
    /**
     * Check if groups module is enabled
     *
     * @return bool|array Returns true if enabled, or error response array
     */
    public static function checkGroupsEnabled()
    {
        if (!GroupFeatureToggleService::isEnabled(GroupFeatureToggleService::FEATURE_GROUPS_MODULE)) {
            http_response_code(403);
            return [
                'error' => true,
                'message' => 'Groups feature is not enabled for this community',
                'redirect' => TenantContext::getBasePath() . '/'
            ];
        }

        return true;
    }

    /**
     * Check if a specific feature is enabled
     *
     * @param string $feature Feature constant
     * @param string $customMessage Optional custom error message
     * @return bool|array Returns true if enabled, or error response array
     */
    public static function checkFeature($feature, $customMessage = null)
    {
        if (!GroupFeatureToggleService::isEnabled($feature)) {
            $definition = GroupFeatureToggleService::getFeatureDefinition($feature);
            $label = $definition['label'] ?? $feature;

            http_response_code(403);
            return [
                'error' => true,
                'message' => $customMessage ?? "The {$label} feature is not available",
                'redirect' => TenantContext::getBasePath() . '/groups'
            ];
        }

        return true;
    }

    /**
     * Require groups module (die if not enabled)
     */
    public static function requireGroups()
    {
        $check = self::checkGroupsEnabled();
        if (is_array($check)) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                // AJAX request
                header('Content-Type: application/json');
                echo json_encode($check);
            } else {
                // Regular request
                header('Location: ' . $check['redirect']);
            }
            exit;
        }
    }

    /**
     * Require a specific feature (die if not enabled)
     *
     * @param string $feature Feature constant
     * @param string $customMessage Optional custom error message
     */
    public static function requireFeature($feature, $customMessage = null)
    {
        // First check if groups module is enabled
        self::requireGroups();

        // Then check specific feature
        $check = self::checkFeature($feature, $customMessage);
        if (is_array($check)) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                // AJAX request
                header('Content-Type: application/json');
                echo json_encode($check);
            } else {
                // Regular request
                header('Location: ' . $check['redirect']);
            }
            exit;
        }
    }

    /**
     * Check multiple features (all must be enabled)
     *
     * @param array $features Array of feature constants
     * @return bool|array Returns true if all enabled, or error response array
     */
    public static function checkFeatures(array $features)
    {
        foreach ($features as $feature) {
            $check = self::checkFeature($feature);
            if (is_array($check)) {
                return $check;
            }
        }

        return true;
    }

    /**
     * Check if any of the features is enabled
     *
     * @param array $features Array of feature constants
     * @return bool At least one feature is enabled
     */
    public static function checkAnyFeature(array $features)
    {
        foreach ($features as $feature) {
            if (GroupFeatureToggleService::isEnabled($feature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get feature gate for view rendering
     *
     * @param string $feature Feature constant
     * @return bool
     */
    public static function can($feature)
    {
        return GroupFeatureToggleService::isEnabled($feature);
    }

    /**
     * Get multiple feature gates for view rendering
     *
     * @param array $features Array of feature constants
     * @return array Associative array of feature => enabled
     */
    public static function gates(array $features)
    {
        $gates = [];
        foreach ($features as $feature) {
            $gates[$feature] = GroupFeatureToggleService::isEnabled($feature);
        }
        return $gates;
    }
}
