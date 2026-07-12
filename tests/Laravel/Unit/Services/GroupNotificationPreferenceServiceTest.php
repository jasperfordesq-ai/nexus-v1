<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupNotificationPreferenceService;
use Illuminate\Support\Facades\DB;

class GroupNotificationPreferenceServiceTest extends TestCase
{
    public function test_get_returns_defaults_when_no_preference_exists(): void
    {
        DB::shouldReceive('table->where->where->where->first')
            ->once()
            ->andReturn(null);

        $result = GroupNotificationPreferenceService::get(1, 10);

        $this->assertIsArray($result);
        $this->assertEquals('instant', $result['frequency']);
        $this->assertTrue($result['email_enabled']);
        $this->assertTrue($result['push_enabled']);
    }

    public function test_get_returns_stored_preference(): void
    {
        $stored = (object) [
            'user_id' => 1,
            'group_id' => 10,
            'frequency' => 'digest',
            'email_enabled' => false,
            'push_enabled' => true,
            'updated_at' => '2026-07-11 16:20:30',
        ];

        DB::shouldReceive('table->where->where->where->first')
            ->once()
            ->andReturn($stored);

        $result = GroupNotificationPreferenceService::get(1, 10);

        $this->assertEquals('digest', $result['frequency']);
        $this->assertFalse($result['email_enabled']);
        $this->assertTrue($result['push_enabled']);
        $this->assertSame('2026-07-11T16:20:30.000000Z', $result['updated_at']);
    }

    public function test_set_calls_updateOrInsert(): void
    {
        DB::shouldReceive('table->updateOrInsert')
            ->once()
            ->withArgs(function ($match, $values) {
                return $match['user_id'] === 1
                    && $match['group_id'] === 10
                    && $match['tenant_id'] === $this->testTenantId
                    && $values['frequency'] === 'digest'
                    && $values['email_enabled'] === false
                    && $values['push_enabled'] === true;
            });
        DB::shouldReceive('table->where->where->where->first')
            ->once()
            ->andReturn((object) [
                'frequency' => 'digest',
                'email_enabled' => false,
                'push_enabled' => true,
                'updated_at' => '2026-07-11 16:20:30',
            ]);

        $saved = GroupNotificationPreferenceService::set(1, 10, [
            'frequency' => 'digest',
            'email_enabled' => false,
            'push_enabled' => true,
        ]);

        $this->assertSame('digest', $saved['frequency']);
        $this->assertSame('2026-07-11T16:20:30.000000Z', $saved['updated_at']);
    }

    public function test_shouldNotify_returns_false_when_muted(): void
    {
        DB::shouldReceive('table->where->where->where->first')
            ->once()
            ->andReturn((object) [
                'frequency' => 'muted',
                'email_enabled' => true,
                'push_enabled' => true,
            ]);

        $result = GroupNotificationPreferenceService::shouldNotify(1, 10, 'in_app');
        $this->assertFalse($result);
    }

    public function test_shouldNotify_returns_false_when_email_disabled_for_email_channel(): void
    {
        DB::shouldReceive('table->where->where->where->first')
            ->once()
            ->andReturn((object) [
                'frequency' => 'instant',
                'email_enabled' => false,
                'push_enabled' => true,
            ]);

        $result = GroupNotificationPreferenceService::shouldNotify(1, 10, 'email');
        $this->assertFalse($result);
    }

    public function test_shouldNotify_returns_false_when_push_disabled_for_push_channel(): void
    {
        DB::shouldReceive('table->where->where->where->first')
            ->once()
            ->andReturn((object) [
                'frequency' => 'instant',
                'email_enabled' => true,
                'push_enabled' => false,
            ]);

        $result = GroupNotificationPreferenceService::shouldNotify(1, 10, 'push');
        $this->assertFalse($result);
    }

    public function test_shouldNotify_returns_true_for_in_app_with_defaults(): void
    {
        // No stored preference — defaults apply (instant, all enabled)
        DB::shouldReceive('table->where->where->where->first')
            ->once()
            ->andReturn(null);

        $result = GroupNotificationPreferenceService::shouldNotify(1, 10, 'in_app');
        $this->assertTrue($result);
    }

    public function test_getAllForUser_returns_array(): void
    {
        DB::shouldReceive('table->join->where->where->select->get')
            ->once()
            ->andReturn(collect([
                (object) ['group_id' => 1, 'group_name' => 'Group A', 'frequency' => 'instant'],
                (object) ['group_id' => 2, 'group_name' => 'Group B', 'frequency' => 'muted'],
            ]));

        $result = GroupNotificationPreferenceService::getAllForUser(1);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Group A', $result[0]['group_name']);
        $this->assertEquals('muted', $result[1]['frequency']);
    }
}
