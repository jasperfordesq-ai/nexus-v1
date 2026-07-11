<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Exceptions\SafeguardingPolicyException;
use App\Models\User;
use App\Services\GroupService;
use App\Services\SafeguardingInteractionPolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Laravel\TestCase;

class GroupServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_getAll_is_tenant_scoped_and_respects_viewer_visibility(): void
    {
        Queue::fake();

        $viewer = User::factory()->forTenant($this->testTenantId)->create();
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        $secondActiveMember = User::factory()->forTenant($this->testTenantId)->create();
        $pendingMember = User::factory()->forTenant($this->testTenantId)->create();
        $crossTenantOwner = User::factory()->forTenant(999)->create();
        TenantContext::setById($this->testTenantId);

        $marker = 'group-service-get-all-' . $viewer->id;
        $insertGroup = static function (
            int $tenantId,
            int $ownerId,
            string $name,
            string $visibility,
            ?int $parentId = null,
        ): int {
            return (int) DB::table('groups')->insertGetId([
                'tenant_id' => $tenantId,
                'owner_id' => $ownerId,
                'name' => $name,
                'description' => 'Deterministic GroupService::getAll fixture',
                'visibility' => $visibility,
                'parent_id' => $parentId,
                'is_featured' => 0,
                'cached_member_count' => 99,
                'status' => 'active',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        };

        $publicGroupId = $insertGroup(
            $this->testTenantId,
            (int) $owner->id,
            $marker . '-public',
            'public',
        );
        $ownedPrivateGroupId = $insertGroup(
            $this->testTenantId,
            (int) $viewer->id,
            $marker . '-owned-private',
            'private',
        );
        $memberPrivateGroupId = $insertGroup(
            $this->testTenantId,
            (int) $owner->id,
            $marker . '-member-private',
            'private',
        );
        $hiddenPrivateGroupId = $insertGroup(
            $this->testTenantId,
            (int) $owner->id,
            $marker . '-hidden-private',
            'private',
        );
        $childGroupId = $insertGroup(
            $this->testTenantId,
            (int) $owner->id,
            $marker . '-child',
            'public',
            $publicGroupId,
        );
        $crossTenantGroupId = $insertGroup(
            999,
            (int) $crossTenantOwner->id,
            $marker . '-cross-tenant-canary',
            'public',
        );

        DB::table('group_members')->insert([
            [
                'tenant_id' => $this->testTenantId,
                'group_id' => $memberPrivateGroupId,
                'user_id' => $viewer->id,
                'role' => 'member',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $this->testTenantId,
                'group_id' => $memberPrivateGroupId,
                'user_id' => $secondActiveMember->id,
                'role' => 'member',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $this->testTenantId,
                'group_id' => $memberPrivateGroupId,
                'user_id' => $pendingMember->id,
                'role' => 'member',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $anonymousResult = GroupService::getAll([
            'search' => $marker,
            'limit' => 100,
        ]);
        $anonymousIds = array_column($anonymousResult['items'], 'id');

        $this->assertContains($publicGroupId, $anonymousIds);
        $this->assertNotContains($ownedPrivateGroupId, $anonymousIds);
        $this->assertNotContains($memberPrivateGroupId, $anonymousIds);
        $this->assertNotContains($hiddenPrivateGroupId, $anonymousIds);
        $this->assertNotContains($childGroupId, $anonymousIds);
        $this->assertNotContains($crossTenantGroupId, $anonymousIds);

        $viewerResult = GroupService::getAll([
            'viewer_user_id' => (int) $viewer->id,
            'search' => $marker,
            'limit' => 100,
        ]);
        $viewerItems = collect($viewerResult['items'])->keyBy('id');

        $this->assertTrue($viewerItems->has($publicGroupId));
        $this->assertTrue($viewerItems->has($ownedPrivateGroupId));
        $this->assertTrue($viewerItems->has($memberPrivateGroupId));
        $this->assertFalse($viewerItems->has($hiddenPrivateGroupId));
        $this->assertFalse($viewerItems->has($childGroupId));
        $this->assertFalse($viewerItems->has($crossTenantGroupId));
        $this->assertSame(2, $viewerItems->get($memberPrivateGroupId)['member_count']);
        $this->assertFalse($viewerResult['has_more']);
        $this->assertNull($viewerResult['cursor']);
    }

    public function test_join_safeguarding_denial_writes_no_membership(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        $joining = User::factory()->forTenant($this->testTenantId)->create();
        TenantContext::setById($this->testTenantId);
        $groupId = (int) DB::table('groups')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'owner_id' => $owner->id,
            'name' => 'Safeguarding cohort test',
            'visibility' => 'public',
            'status' => 'active',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('group_members')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $groupId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with((int) $joining->id, (int) $owner->id, $this->testTenantId, 'group_join')
            ->andThrow(new SafeguardingPolicyException('VETTING_REQUIRED', 'Vetting required'));
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        try {
            GroupService::join($groupId, (int) $joining->id);
            $this->fail('Expected safeguarding denial');
        } catch (SafeguardingPolicyException $e) {
            $this->assertSame('VETTING_REQUIRED', $e->reasonCode);
        }

        $this->assertDatabaseMissing('group_members', [
            'group_id' => $groupId,
            'user_id' => $joining->id,
        ]);
    }
}
