<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\GroupWelcomeService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class GroupWelcomeServiceTest extends TestCase
{
    // ── getConfig ────────────────────────────────────────────────────

    public function test_getConfig_returns_default_disabled_when_no_policies(): void
    {
        DB::shouldReceive('table')->with('group_policies')->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', $this->testTenantId)->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturnSelf();
        DB::shouldReceive('toArray')->andReturn([]);

        $result = GroupWelcomeService::getConfig(1);
        $this->assertFalse($result['enabled']);
        $this->assertSame('', $result['message']);
    }

    public function test_getConfig_returns_enabled_and_message_from_policies(): void
    {
        DB::shouldReceive('table')->with('group_policies')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturnSelf();
        DB::shouldReceive('toArray')->andReturn([
            'welcome_message_enabled_5' => json_encode(true),
            'welcome_message_5' => json_encode('Hello {member_name}!'),
        ]);

        $result = GroupWelcomeService::getConfig(5);
        $this->assertTrue($result['enabled']);
        $this->assertSame('Hello {member_name}!', $result['message']);
    }

    // ── setConfig ────────────────────────────────────────────────────

    public function test_setConfig_upserts_both_enabled_and_message_rows(): void
    {
        DB::shouldReceive('table')->with('group_policies')->twice()->andReturnSelf();
        DB::shouldReceive('updateOrInsert')->twice();

        GroupWelcomeService::setConfig(1, true, 'Welcome!');
        $this->assertTrue(true);
    }

    // ── sendWelcome: disabled / missing config ───────────────────────

    public function test_sendWelcome_returns_false_when_disabled(): void
    {
        // getConfig returns disabled
        DB::shouldReceive('table')->with('group_policies')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturnSelf();
        DB::shouldReceive('toArray')->andReturn([]);

        $this->assertFalse(GroupWelcomeService::sendWelcome(1, 2));
    }

    public function test_sendWelcome_returns_false_when_user_or_group_missing(): void
    {
        // Config enabled with message
        DB::shouldReceive('table')->with('group_policies')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturnSelf();
        DB::shouldReceive('toArray')->andReturn([
            'welcome_message_enabled_1' => json_encode(true),
            'welcome_message_1' => json_encode('Hi'),
        ]);

        // users lookup returns null
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);
        DB::shouldReceive('table')->with('groups')->andReturnSelf();

        $this->assertFalse(GroupWelcomeService::sendWelcome(1, 2));
    }

    public function test_sendWelcome_inserts_notification_and_substitutes_vars(): void
    {
        // Config enabled
        DB::shouldReceive('table')->with('group_policies')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturnSelf();
        DB::shouldReceive('toArray')->andReturn([
            'welcome_message_enabled_3' => json_encode(true),
            'welcome_message_3' => json_encode('Hi {member_name}, welcome to {group_name}'),
        ]);

        // user/group/owner lookups
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('table')->with('groups')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(
            (object) ['name' => 'Alice'],             // user
            (object) ['name' => 'Timebank', 'owner_id' => 99],  // group
            (object) ['name' => 'Admin Bob']          // owner
        );

        // Notification insert
        DB::shouldReceive('table')->with('notifications')->once()->andReturnSelf();
        DB::shouldReceive('insert')->once()
            ->withArgs(function ($data) {
                return str_contains($data['content'], 'Alice')
                    && str_contains($data['content'], 'Timebank');
            })
            ->andReturn(true);

        $this->assertTrue(GroupWelcomeService::sendWelcome(3, 42));
    }
}
