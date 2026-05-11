<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services\AI;

use App\Core\TenantContext;
use App\Services\TenantFeatureConfig;

/**
 * Returns 5 starter prompts for the chat empty state, tailored to the
 * tenant's enabled modules/features. No model call — pure static map.
 */
class AiSuggestedPromptsService
{
    private const POOL = [
        'listings' => [
            'Find me someone who can help with gardening',
            'What offers are available this week?',
            'I need help with translation — who can help?',
        ],
        'wallet' => [
            'How many hours do I have?',
            'How do time credits work?',
        ],
        'events' => [
            'What events are coming up this weekend?',
            'Are there any workshops on this month?',
        ],
        'jobs' => [
            'Are there any remote jobs going?',
            'What part-time work is available?',
        ],
        'marketplace' => [
            'Is anyone selling a bike?',
            'What free items are available?',
        ],
        'volunteering' => [
            'What volunteering shifts are available?',
        ],
        'groups' => [
            'What groups can I join?',
        ],
        'general' => [
            'How do I create a listing?',
            'How do I message another member?',
            'How do I update my skills?',
        ],
    ];

    /** @return string[] */
    public function pick(int $count = 5): array
    {
        $tenant = TenantContext::get() ?: [];
        $features = $tenant['features'] ?? null;
        if (is_string($features)) {
            $features = json_decode($features, true) ?: [];
        }
        $config = $tenant['configuration'] ?? null;
        if (is_string($config)) {
            $config = json_decode($config, true) ?: [];
        }
        $modules = is_array($config['modules'] ?? null) ? $config['modules'] : [];

        $mergedFeatures = TenantFeatureConfig::mergeFeatures(is_array($features) ? $features : []);
        $mergedModules = TenantFeatureConfig::mergeModules($modules);

        $candidates = [];
        if (!empty($mergedModules['listings'])) {
            $candidates = array_merge($candidates, self::POOL['listings']);
        }
        if (!empty($mergedModules['wallet'])) {
            $candidates = array_merge($candidates, self::POOL['wallet']);
        }
        if (!empty($mergedFeatures['events'])) {
            $candidates = array_merge($candidates, self::POOL['events']);
        }
        if (!empty($mergedFeatures['job_vacancies'])) {
            $candidates = array_merge($candidates, self::POOL['jobs']);
        }
        if (!empty($mergedFeatures['marketplace'])) {
            $candidates = array_merge($candidates, self::POOL['marketplace']);
        }
        if (!empty($mergedFeatures['volunteering'])) {
            $candidates = array_merge($candidates, self::POOL['volunteering']);
        }
        if (!empty($mergedFeatures['groups'])) {
            $candidates = array_merge($candidates, self::POOL['groups']);
        }
        $candidates = array_merge($candidates, self::POOL['general']);

        // Deterministic per-tenant ordering: shuffle with a seed so a refresh
        // doesn't churn the order but different tenants see different mixes.
        $seed = (int) (TenantContext::getId() ?? 0);
        mt_srand($seed);
        shuffle($candidates);
        mt_srand();

        return array_slice($candidates, 0, max(1, $count));
    }
}
