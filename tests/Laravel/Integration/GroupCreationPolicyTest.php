<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Enums\GroupStatus;
use App\Events\GroupCreated;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupConfigurationService;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

final class GroupCreationPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        Cache::forget('group_config:' . $this->testTenantId);
        Queue::fake();
        $this->member = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);
    }

    protected function tearDown(): void
    {
        Cache::forget('group_config:' . $this->testTenantId);
        parent::tearDown();
    }

    public function test_default_creation_writes_active_pair_and_owner_membership(): void
    {
        Event::fake([GroupCreated::class]);

        $group = GroupService::create($this->member->id, $this->validPayload());

        self::assertInstanceOf(Group::class, $group);
        self::assertSame(GroupStatus::Active, $group->status);
        self::assertTrue($group->is_active);
        $this->assertDatabaseHas('group_members', [
            'tenant_id' => $this->testTenantId,
            'group_id' => $group->id,
            'user_id' => $this->member->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        Event::assertDispatched(GroupCreated::class);
    }

    public function test_required_approval_creates_group_and_request_atomically_without_active_event(): void
    {
        GroupConfigurationService::set(GroupConfigurationService::CONFIG_REQUIRE_GROUP_APPROVAL, true);
        Event::fake([GroupCreated::class]);

        $group = GroupService::create($this->member->id, $this->validPayload());

        self::assertInstanceOf(Group::class, $group);
        self::assertSame(GroupStatus::PendingReview, $group->status);
        self::assertFalse($group->is_active);
        $this->assertDatabaseHas('group_approval_requests', [
            'tenant_id' => $this->testTenantId,
            'group_id' => $group->id,
            'submitted_by' => $this->member->id,
            'status' => 'pending',
        ]);
        Event::assertNotDispatched(GroupCreated::class);
    }

    public function test_creation_disabled_blocks_member_but_allows_same_tenant_admin(): void
    {
        GroupConfigurationService::set(GroupConfigurationService::CONFIG_ALLOW_USER_GROUP_CREATION, false);

        self::assertNull(GroupService::create($this->member->id, $this->validPayload()));
        self::assertSame('FORBIDDEN', GroupService::getErrors()[0]['code']);

        $admin = User::factory()->forTenant($this->testTenantId)->create(['role' => 'admin']);
        TenantContext::setById($this->testTenantId);
        self::assertInstanceOf(Group::class, GroupService::create($admin->id, $this->validPayload([
            'name' => 'Admin policy override group',
        ])));
    }

    public function test_maximum_owned_groups_and_private_visibility_are_enforced_server_side(): void
    {
        GroupConfigurationService::set(GroupConfigurationService::CONFIG_MAX_GROUPS_PER_USER, 1);
        Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id' => $this->member->id,
            'status' => GroupStatus::Active->value,
            'is_active' => true,
        ]);
        TenantContext::setById($this->testTenantId);

        self::assertNull(GroupService::create($this->member->id, $this->validPayload()));
        self::assertSame('VALIDATION_ERROR', GroupService::getErrors()[0]['code']);

        GroupConfigurationService::set(GroupConfigurationService::CONFIG_MAX_GROUPS_PER_USER, 10);
        GroupConfigurationService::set(GroupConfigurationService::CONFIG_ALLOW_PRIVATE_GROUPS, false);
        self::assertNull(GroupService::create($this->member->id, $this->validPayload([
            'visibility' => 'private',
        ])));
        self::assertSame('visibility', collect(GroupService::getErrors())->firstWhere('field', 'visibility')['field']);
    }

    public function test_description_bounds_are_enforced_from_tenant_configuration(): void
    {
        GroupConfigurationService::set(GroupConfigurationService::CONFIG_MIN_DESCRIPTION_LENGTH, 20);
        GroupConfigurationService::set(GroupConfigurationService::CONFIG_MAX_DESCRIPTION_LENGTH, 30);

        self::assertNull(GroupService::create($this->member->id, $this->validPayload([
            'description' => 'Too short',
        ])));
        self::assertSame('description', GroupService::getErrors()[0]['field']);

        self::assertNull(GroupService::create($this->member->id, $this->validPayload([
            'description' => str_repeat('x', 31),
        ])));
        self::assertSame('description', GroupService::getErrors()[0]['field']);
    }

    public function test_member_capacity_and_non_active_lifecycle_block_direct_join(): void
    {
        GroupConfigurationService::set(GroupConfigurationService::CONFIG_MAX_MEMBERS_PER_GROUP, 1);
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        $group = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id' => $owner->id,
            'cached_member_count' => 1,
            'status' => GroupStatus::Active->value,
            'is_active' => true,
            'visibility' => 'public',
        ]);
        DB::table('group_members')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $group->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        TenantContext::setById($this->testTenantId);

        $capacityResult = GroupService::join($group->id, $this->member->id);
        self::assertFalse($capacityResult['success']);
        self::assertSame('CAPACITY_FULL', $capacityResult['code']);
        $this->assertDatabaseMissing('group_members', [
            'group_id' => $group->id,
            'user_id' => $this->member->id,
        ]);

        DB::table('groups')->where('id', $group->id)->update([
            'status' => GroupStatus::Archived->value,
            'is_active' => false,
            'cached_member_count' => 0,
        ]);
        $archivedResult = GroupService::join($group->id, $this->member->id);
        self::assertFalse($archivedResult['success']);
        self::assertSame('GROUP_UNAVAILABLE', $archivedResult['code']);
    }

    /** @return array{name: string, description: string, visibility: string} */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Policy verified group ' . uniqid('', true),
            'description' => 'A sufficiently detailed description for the group policy test.',
            'visibility' => 'public',
        ], $overrides);
    }
}
