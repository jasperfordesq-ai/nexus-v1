<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Services;

/**
 * Single source of truth for tenant feature and module defaults.
 *
 * Both AdminConfigApiController and TenantBootstrapController reference
 * these constants to prevent desynchronisation.
 */
class TenantFeatureConfig
{
    /**
     * All known features with their default enabled/disabled state.
     * Features are optional add-ons toggled via Admin > Tenant Features.
     */
    public const FEATURE_DEFAULTS = [
        'events' => true,
        'groups' => true,
        'gamification' => false,
        'goals' => false,
        'blog' => true,
        'resources' => false,
        'volunteering' => false,
        'exchange_workflow' => false,
        'organisations' => false,
        'federation' => false,
        'connections' => true,
        'reviews' => true,
        'polls' => false,
        'job_vacancies' => false,
        'ideation_challenges' => false,
        'direct_messaging' => true,
        'group_exchanges' => false,
        'search' => true,
        'ai_chat' => false,
    ];

    /**
     * All known core modules with their default enabled/disabled state.
     * Modules are core platform functionality (listings, wallet, messages).
     */
    public const MODULE_DEFAULTS = [
        'listings' => true,
        'wallet' => true,
        'messages' => true,
        'dashboard' => true,
        'feed' => true,
        'notifications' => true,
        'profile' => true,
        'settings' => true,
    ];

    /**
     * Merge DB features with defaults, returning the full feature set.
     * DB values override defaults; unknown DB keys are preserved.
     */
    public static function mergeFeatures(?array $dbFeatures): array
    {
        $result = self::FEATURE_DEFAULTS;

        if ($dbFeatures === null) {
            return $result;
        }

        // DB values override defaults
        foreach ($dbFeatures as $key => $value) {
            $result[$key] = (bool) $value;
        }

        return $result;
    }

    /**
     * Merge DB modules with defaults, returning the full module set.
     */
    public static function mergeModules(?array $dbModules): array
    {
        $result = self::MODULE_DEFAULTS;

        if ($dbModules === null) {
            return $result;
        }

        foreach ($dbModules as $key => $value) {
            $result[$key] = (bool) $value;
        }

        return $result;
    }
}
