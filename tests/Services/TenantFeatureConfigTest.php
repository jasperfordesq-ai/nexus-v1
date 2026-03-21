<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\TenantFeatureConfig;

class TenantFeatureConfigTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(TenantFeatureConfig::class));
    }

    public function testFeatureDefaultsHas19Features(): void
    {
        $this->assertCount(19, TenantFeatureConfig::FEATURE_DEFAULTS);
    }

    public function testAllFeatureDefaultsAreTrue(): void
    {
        foreach (TenantFeatureConfig::FEATURE_DEFAULTS as $feature => $value) {
            $this->assertTrue($value, "Feature '{$feature}' should default to true");
        }
    }

    public function testFeatureDefaultsContainsExpectedKeys(): void
    {
        $expected = [
            'events', 'groups', 'gamification', 'goals', 'blog', 'resources',
            'volunteering', 'exchange_workflow', 'organisations', 'federation',
            'connections', 'reviews', 'polls', 'job_vacancies',
            'ideation_challenges', 'direct_messaging', 'group_exchanges',
            'search', 'ai_chat',
        ];
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, TenantFeatureConfig::FEATURE_DEFAULTS, "Missing feature: {$key}");
        }
    }

    public function testModuleDefaultsContainsExpectedKeys(): void
    {
        $expected = ['listings', 'wallet', 'messages', 'dashboard', 'feed', 'notifications', 'profile', 'settings'];
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, TenantFeatureConfig::MODULE_DEFAULTS, "Missing module: {$key}");
        }
    }

    public function testAllModuleDefaultsAreTrue(): void
    {
        foreach (TenantFeatureConfig::MODULE_DEFAULTS as $module => $value) {
            $this->assertTrue($value, "Module '{$module}' should default to true");
        }
    }

    public function testMergeFeaturesReturnsDefaultsForNull(): void
    {
        $result = TenantFeatureConfig::mergeFeatures(null);
        $this->assertEquals(TenantFeatureConfig::FEATURE_DEFAULTS, $result);
    }

    public function testMergeFeaturesOverridesDefaults(): void
    {
        $result = TenantFeatureConfig::mergeFeatures(['events' => false, 'blog' => false]);
        $this->assertFalse($result['events']);
        $this->assertFalse($result['blog']);
        // Other features remain true
        $this->assertTrue($result['groups']);
        $this->assertTrue($result['gamification']);
    }

    public function testMergeFeaturesPreservesUnknownKeys(): void
    {
        $result = TenantFeatureConfig::mergeFeatures(['custom_feature' => true]);
        $this->assertArrayHasKey('custom_feature', $result);
        $this->assertTrue($result['custom_feature']);
    }

    public function testMergeFeaturesCastsToBool(): void
    {
        $result = TenantFeatureConfig::mergeFeatures(['events' => 1, 'blog' => 0]);
        $this->assertTrue($result['events']);
        $this->assertFalse($result['blog']);
        $this->assertIsBool($result['events']);
        $this->assertIsBool($result['blog']);
    }

    public function testMergeModulesReturnsDefaultsForNull(): void
    {
        $result = TenantFeatureConfig::mergeModules(null);
        $this->assertEquals(TenantFeatureConfig::MODULE_DEFAULTS, $result);
    }

    public function testMergeModulesOverridesDefaults(): void
    {
        $result = TenantFeatureConfig::mergeModules(['listings' => false]);
        $this->assertFalse($result['listings']);
        $this->assertTrue($result['wallet']);
    }

    public function testMergeModulesPreservesUnknownKeys(): void
    {
        $result = TenantFeatureConfig::mergeModules(['custom_module' => true]);
        $this->assertArrayHasKey('custom_module', $result);
        $this->assertTrue($result['custom_module']);
    }

    public function testMergeModulesCastsToBool(): void
    {
        $result = TenantFeatureConfig::mergeModules(['wallet' => 0, 'feed' => 1]);
        $this->assertFalse($result['wallet']);
        $this->assertTrue($result['feed']);
        $this->assertIsBool($result['wallet']);
        $this->assertIsBool($result['feed']);
    }

    public function testCanBeInstantiated(): void
    {
        $config = new TenantFeatureConfig();
        $this->assertInstanceOf(TenantFeatureConfig::class, $config);
    }
}
