<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupNotificationPreferenceService;
use App\Services\GroupNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class GroupNotificationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private GroupNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupNotificationService();
    }

    public function test_notify_join_request_notifies_active_admins_but_not_the_requester(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        $requester = User::factory()->forTenant($this->testTenantId)->create();
        $group = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id' => $owner->id,
            'name' => 'Repair Circle',
        ]);

        // Synchronous observer jobs clear tenant state as part of queue-worker
        // hygiene, so re-establish the request tenant after creating fixtures.
        TenantContext::setById($this->testTenantId);
        DB::table('group_members')->insert([
            [
                'tenant_id' => $this->testTenantId,
                'group_id' => $group->id,
                'user_id' => $owner->id,
                'role' => 'owner',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $this->testTenantId,
                'group_id' => $group->id,
                'user_id' => $requester->id,
                'role' => 'admin',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        GroupNotificationPreferenceService::set((int) $owner->id, (int) $group->id, [
            'frequency' => 'instant',
            'email_enabled' => false,
            'push_enabled' => false,
        ]);

        $this->service->notifyJoinRequest((int) $group->id, (int) $requester->id);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $owner->id,
            'type' => 'group_join_request',
            'link' => "/groups/{$group->id}?tab=members",
        ]);
        self::assertSame(0, DB::table('notifications')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $requester->id)
            ->count());
    }
}
