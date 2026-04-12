<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\TenantSafeguardingOption;
use App\Models\UserSafeguardingPreference;
use App\Services\SafeguardingTriggerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Laravel\TestCase;
use Mockery;

/**
 * @covers \App\Services\SafeguardingTriggerService
 */
class SafeguardingTriggerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    // =========================================================================
    // getActiveTriggers
    // =========================================================================

    public function test_getActiveTriggers_returnsDefaultsWhenNoPreferences(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $mockAlias = Mockery::mock('alias:' . UserSafeguardingPreference::class);
        $mockAlias->shouldReceive('where->where->active->with->get')
            ->andReturn(collect([]));

        $triggers = SafeguardingTriggerService::getActiveTriggers(1, $this->testTenantId);

        $this->assertFalse($triggers['requires_vetted_interaction']);
        $this->assertFalse($triggers['requires_broker_approval']);
        $this->assertFalse($triggers['restricts_messaging']);
        $this->assertFalse($triggers['restricts_matching']);
        $this->assertFalse($triggers['notify_admin_on_selection']);
    }

    public function test_getActiveTriggers_mergesTriggersWithOrLogic(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $option1 = Mockery::mock();
        $option1->is_active = true;
        $option1->triggers = ['requires_broker_approval' => true, 'restricts_messaging' => false];

        $option2 = Mockery::mock();
        $option2->is_active = true;
        $option2->triggers = ['restricts_messaging' => true, 'notify_admin_on_selection' => true];

        $pref1 = Mockery::mock();
        $pref1->option = $option1;

        $pref2 = Mockery::mock();
        $pref2->option = $option2;

        $mockAlias = Mockery::mock('alias:' . UserSafeguardingPreference::class);
        $mockAlias->shouldReceive('where->where->active->with->get')
            ->andReturn(collect([$pref1, $pref2]));

        $triggers = SafeguardingTriggerService::getActiveTriggers(1, $this->testTenantId);

        // OR-merged: broker_approval from opt1, messaging+notify from opt2
        $this->assertTrue($triggers['requires_broker_approval']);
        $this->assertTrue($triggers['restricts_messaging']);
        $this->assertTrue($triggers['notify_admin_on_selection']);
        // These remain false since neither option set them
        $this->assertFalse($triggers['requires_vetted_interaction']);
        $this->assertFalse($triggers['restricts_matching']);
    }

    public function test_getActiveTriggers_collectsVettingTypes(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturnUsing(function ($key, $ttl, $callback) {
                return $callback();
            });

        $option = Mockery::mock();
        $option->is_active = true;
        $option->triggers = [
            'requires_vetted_interaction' => true,
            'vetting_type_required' => 'garda_vetting',
        ];

        $pref = Mockery::mock();
        $pref->option = $option;

        $mockAlias = Mockery::mock('alias:' . UserSafeguardingPreference::class);
        $mockAlias->shouldReceive('where->where->active->with->get')
            ->andReturn(collect([$pref]));

        $triggers = SafeguardingTriggerService::getActiveTriggers(5, $this->testTenantId);

        $this->assertTrue($triggers['requires_vetted_interaction']);
        $this->assertContains('garda_vetting', $triggers['vetting_types_required']);
    }

    // =========================================================================
    // invalidateCache
    // =========================================================================

    public function test_invalidateCache_forgetsCacheKey(): void
    {
        $expectedKey = "safeguarding_triggers:{$this->testTenantId}:42";

        Cache::shouldReceive('forget')
            ->once()
            ->with($expectedKey);

        SafeguardingTriggerService::invalidateCache(42, $this->testTenantId);
    }

    // =========================================================================
    // Convenience check methods
    // =========================================================================

    public function test_requiresBrokerApproval_returnsTrueWhenTriggered(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn([
                'requires_vetted_interaction' => false,
                'requires_broker_approval' => true,
                'restricts_messaging' => false,
                'restricts_matching' => false,
                'notify_admin_on_selection' => false,
            ]);

        $result = SafeguardingTriggerService::requiresBrokerApproval(1, $this->testTenantId);

        $this->assertTrue($result);
    }

    public function test_isMessagingRestricted_returnsFalseByDefault(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn([
                'requires_vetted_interaction' => false,
                'requires_broker_approval' => false,
                'restricts_messaging' => false,
                'restricts_matching' => false,
                'notify_admin_on_selection' => false,
            ]);

        $result = SafeguardingTriggerService::isMessagingRestricted(1, $this->testTenantId);

        $this->assertFalse($result);
    }

    public function test_getRequiredVettingTypes_returnsEmptyArrayByDefault(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn([
                'requires_vetted_interaction' => false,
                'requires_broker_approval' => false,
                'restricts_messaging' => false,
                'restricts_matching' => false,
                'notify_admin_on_selection' => false,
            ]);

        $result = SafeguardingTriggerService::getRequiredVettingTypes(1, $this->testTenantId);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_isMatchingRestricted_returnsTrueWhenTriggered(): void
    {
        Cache::shouldReceive('remember')
            ->once()
            ->andReturn([
                'requires_vetted_interaction' => false,
                'requires_broker_approval' => false,
                'restricts_messaging' => false,
                'restricts_matching' => true,
                'notify_admin_on_selection' => false,
            ]);

        $result = SafeguardingTriggerService::isMatchingRestricted(1, $this->testTenantId);

        $this->assertTrue($result);
    }
}
