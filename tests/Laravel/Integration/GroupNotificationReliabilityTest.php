<?php
// Copyright Â© 2024â€“2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class GroupNotificationReliabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_notify_joined_resolves_recipient_locale_and_creates_tenant_scoped_bell(): void
    {
        TenantContext::setById($this->testTenantId);
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        $member = User::factory()->forTenant($this->testTenantId)->create([
            'preferred_language' => 'en',
        ]);
        $group = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id' => $owner->id,
            'name' => 'Repair Circle',
            'visibility' => 'public',
        ]);

        TenantContext::setById($this->testTenantId);
        (new GroupNotificationService())->notifyJoined($group->id, $member->id);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $member->id,
            'type' => 'group_join',
            'link' => "/groups/{$group->id}",
        ]);
        $this->assertSame(0, DB::table('notifications')
            ->where('user_id', $member->id)
            ->where('tenant_id', '!=', $this->testTenantId)
            ->count());
    }
}
