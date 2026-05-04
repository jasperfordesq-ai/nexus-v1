<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * TenantFeatureConfig — Self-contained feature/module defaults and merging.
 *
 */
class TenantFeatureConfig
{
    /**
     * All known optional features with their default enabled/disabled state.
     */
    public const FEATURE_DEFAULTS = [
        'events' => true,
        'groups' => true,
        'gamification' => true,
        'goals' => true,
        'blog' => true,
        'resources' => true,
        'caring_community' => false,
        'volunteering' => true,
        'exchange_workflow' => true,
        'organisations' => true,
        'federation' => true,
        'connections' => true,
        'reviews' => true,
        'polls' => true,
        'job_vacancies' => true,
        'ideation_challenges' => true,
        'direct_messaging' => true,
        'group_exchanges' => true,
        'search' => true,
        'ai_chat' => true,
        'marketplace' => false,
        'merchant_coupons' => false,
        'message_translation' => true,
        'member_premium' => false,
        'ai_agents' => false,
        'partner_api' => false,
        'regional_analytics' => false,
        'newsletter' => true,
    ];

    /**
     * All known core modules with their default enabled/disabled state.
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

    public function __construct()
    {
    }

    /**
     * Merge DB feature flags with defaults.
     * DB values override defaults; unknown DB keys are preserved.
     */
    public static function mergeFeatures(?array $dbFeatures): array
    {
        $result = self::FEATURE_DEFAULTS;

        if ($dbFeatures === null) {
            return $result;
        }

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
