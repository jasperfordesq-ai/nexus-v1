<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

/**
 * TenantFeatureConfig — Laravel DI wrapper for legacy \Nexus\Services\TenantFeatureConfig.
 *
 * Provides dependency-injectable access to the legacy static service methods.
 */
class TenantFeatureConfig
{
    /**
     * All known optional features with their default enabled/disabled state.
     * Mirrors \Nexus\Services\TenantFeatureConfig::FEATURE_DEFAULTS.
     */
    public const FEATURE_DEFAULTS = [
        'events' => true,
        'groups' => true,
        'gamification' => true,
        'goals' => true,
        'blog' => true,
        'resources' => true,
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
    ];

    /**
     * All known core modules with their default enabled/disabled state.
     * Mirrors \Nexus\Services\TenantFeatureConfig::MODULE_DEFAULTS.
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
     * Delegates to legacy TenantFeatureConfig::mergeFeatures().
     */
    public function mergeFeatures(?array $dbFeatures): array
    {
        return \Nexus\Services\TenantFeatureConfig::mergeFeatures($dbFeatures);
    }

    /**
     * Delegates to legacy TenantFeatureConfig::mergeModules().
     */
    public function mergeModules(?array $dbModules): array
    {
        return \Nexus\Services\TenantFeatureConfig::mergeModules($dbModules);
    }
}
