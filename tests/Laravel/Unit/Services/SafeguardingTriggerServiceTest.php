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
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SafeguardingTriggerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @param list<array<string, bool|string>> $triggerSets */
    private function mockActiveOptions(int $userId, array $triggerSets): void
    {
        $optionIds = array_keys($triggerSets);
        $optionIds = array_map(static fn (int $index): int => $index + 1, $optionIds);

        $preferences = Mockery::mock();
        $preferences->shouldReceive('where')->with('tenant_id', $this->testTenantId)->once()->andReturnSelf();
        $preferences->shouldReceive('where')->with('user_id', $userId)->once()->andReturnSelf();
        $preferences->shouldReceive('whereNull')->with('revoked_at')->once()->andReturnSelf();
        $preferences->shouldReceive('orderBy')->with('id')->once()->andReturnSelf();
        $preferences->shouldReceive('pluck')->with('option_id')->once()->andReturn(collect($optionIds));
        DB::shouldReceive('table')
            ->with('user_safeguarding_preferences')
            ->once()
            ->andReturn($preferences);

        if ($triggerSets === []) {
            return;
        }

        $options = Mockery::mock();
        $options->shouldReceive('where')->with('tenant_id', $this->testTenantId)->once()->andReturnSelf();
        $options->shouldReceive('whereIn')->with('id', $optionIds)->once()->andReturnSelf();
        $options->shouldReceive('where')->with('is_active', true)->once()->andReturnSelf();
        $options->shouldReceive('orderBy')->with('id')->once()->andReturnSelf();
        $options->shouldReceive('get')->with(['triggers'])->once()->andReturn(collect(
            array_map(static fn (array $triggers): object => (object) ['triggers' => $triggers], $triggerSets)
        ));
        DB::shouldReceive('table')
            ->with('tenant_safeguarding_options')
            ->once()
            ->andReturn($options);
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

        $this->mockActiveOptions(1, []);

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

        $this->mockActiveOptions(1, [
            ['requires_broker_approval' => true, 'restricts_messaging' => false],
            ['restricts_messaging' => true, 'notify_admin_on_selection' => true],
        ]);

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

        $this->mockActiveOptions(5, [[
            'requires_vetted_interaction' => true,
            'vetting_type_required' => 'garda_vetting',
        ]]);

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

    // =========================================================================
    // getRequiredVettingTypesForUsers (bulk variant)
    // =========================================================================

    public function test_getRequiredVettingTypesForUsers_returnsEmptyArrayForEmptyInput(): void
    {
        // No DB calls expected — early return
        $result = SafeguardingTriggerService::getRequiredVettingTypesForUsers([], $this->testTenantId);

        $this->assertSame([], $result);
    }

    public function test_getRequiredVettingTypesForUsers_returnsEmptyArraysForUsersWithNoPreferences(): void
    {
        DB::shouldReceive('table')->with('user_safeguarding_preferences as p')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = SafeguardingTriggerService::getRequiredVettingTypesForUsers(
            [5, 10, 15],
            $this->testTenantId
        );

        $this->assertSame([5 => [], 10 => [], 15 => []], $result);
    }

    public function test_getRequiredVettingTypesForUsers_ignoresVettingTypesWithoutVettedInteractionTrigger(): void
    {
        $rows = collect([
            (object) [
                'user_id' => 5,
                'triggers' => json_encode([
                    'notify_admin_on_selection' => true,
                    'vetting_type_required' => 'dbs_enhanced',
                ]),
            ],
        ]);

        DB::shouldReceive('table')->with('user_safeguarding_preferences as p')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn($rows);

        $result = SafeguardingTriggerService::getRequiredVettingTypesForUsers(
            [5],
            $this->testTenantId
        );

        $this->assertSame([5 => []], $result);
    }

    public function test_getRequiredVettingTypesForUsers_aggregatesOnlyInteractionBlockingVettingTypesPerUser(): void
    {
        // Simulated rows: user 5 has two options, one with garda_vetting and one with dbs_enhanced.
        // user 10 has one informational provider declaration. user 15 has an option with no vetting_type.
        $rows = collect([
            (object) ['user_id' => 5, 'triggers' => json_encode(['requires_vetted_interaction' => true, 'vetting_type_required' => 'garda_vetting'])],
            (object) ['user_id' => 5, 'triggers' => json_encode(['requires_vetted_interaction' => true, 'vetting_type_required' => 'dbs_enhanced'])],
            (object) ['user_id' => 5, 'triggers' => json_encode(['vetting_type_required' => 'garda_vetting'])], // duplicate — should dedupe
            (object) ['user_id' => 10, 'triggers' => json_encode(['vetting_type_required' => 'garda_vetting'])],
            (object) ['user_id' => 15, 'triggers' => json_encode(['requires_broker_approval' => true])],
        ]);

        DB::shouldReceive('table')->with('user_safeguarding_preferences as p')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn($rows);

        $result = SafeguardingTriggerService::getRequiredVettingTypesForUsers(
            [5, 10, 15],
            $this->testTenantId
        );

        $this->assertArrayHasKey(5, $result);
        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(15, $result);

        // User 5 — two unique vetting types, deduplicated
        $this->assertCount(2, $result[5]);
        $this->assertContains('garda_vetting', $result[5]);
        $this->assertContains('dbs_enhanced', $result[5]);

        // User 10 — single type
        $this->assertSame([], $result[10]);

        // User 15 — no vetting_type_required in triggers
        $this->assertSame([], $result[15]);
    }

    public function test_getRequiredVettingTypesForUsers_dedupesInputIds(): void
    {
        DB::shouldReceive('table')->with('user_safeguarding_preferences as p')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = SafeguardingTriggerService::getRequiredVettingTypesForUsers(
            [5, 5, 5, 10, 10],
            $this->testTenantId
        );

        $this->assertCount(2, $result);
        $this->assertArrayHasKey(5, $result);
        $this->assertArrayHasKey(10, $result);
    }

    public function test_getRequiredVettingTypesForUsers_filtersInvalidIds(): void
    {
        DB::shouldReceive('table')->with('user_safeguarding_preferences as p')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = SafeguardingTriggerService::getRequiredVettingTypesForUsers(
            [5, 0, -1, 10],
            $this->testTenantId
        );

        $this->assertCount(2, $result);
        $this->assertArrayHasKey(5, $result);
        $this->assertArrayHasKey(10, $result);
        $this->assertArrayNotHasKey(0, $result);
        $this->assertArrayNotHasKey(-1, $result);
    }

    public function test_getRequiredVettingTypesForUsers_handlesArrayTriggersColumn(): void
    {
        // Some rows may return triggers as a decoded array rather than a JSON string,
        // depending on the driver / Eloquent casting. Both paths must work.
        $rows = collect([
            (object) ['user_id' => 5, 'triggers' => ['requires_vetted_interaction' => true, 'vetting_type_required' => 'garda_vetting']],
        ]);

        DB::shouldReceive('table')->with('user_safeguarding_preferences as p')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn($rows);

        $result = SafeguardingTriggerService::getRequiredVettingTypesForUsers(
            [5],
            $this->testTenantId
        );

        $this->assertSame(['garda_vetting'], $result[5]);
    }

    public function test_getRequiredVettingTypesForUsers_returnsEmptyMapOnDbError(): void
    {
        DB::shouldReceive('table')->andThrow(new \Exception('DB error'));

        $result = SafeguardingTriggerService::getRequiredVettingTypesForUsers(
            [5, 10],
            $this->testTenantId
        );

        // Keys still present (initialised before try/catch) but values are empty arrays
        $this->assertSame([5 => [], 10 => []], $result);
    }
}
