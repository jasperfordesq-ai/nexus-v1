<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Models\Group;
use App\Models\GroupDiscussion;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;
use Tests\Laravel\Traits\ActsAsMember;

/**
 * Integration test: group creation, membership, discussions, and posts.
 *
 * Covers the full group lifecycle from creation through member
 * management and discussion thread interactions.
 */
class GroupLifecycleTest extends TestCase
{
    use DatabaseTransactions;
    use ActsAsMember;

    private User $owner;
    private User $memberUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->forTenant($this->testTenantId)->create([
            'status'      => 'active',
            'is_approved' => true,
        ]);

        $this->memberUser = User::factory()->forTenant($this->testTenantId)->create([
            'status'      => 'active',
            'is_approved' => true,
        ]);
    }

    // =========================================================================
    // Group Creation
    // =========================================================================

    public function test_create_group_via_api(): void
    {
        Sanctum::actingAs($this->owner, ['*']);

        $response = $this->apiPost('/v2/groups', [
            'name'        => 'Gardening Enthusiasts',
            'description' => 'A group for people who love gardening and growing food.',
            'visibility'  => 'public',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);

        // Verify group exists in the database
        $group = Group::where('tenant_id', $this->testTenantId)
            ->where('owner_id', $this->owner->id)
            ->where('name', 'Gardening Enthusiasts')
            ->first();

        $this->assertNotNull($group, 'Group should be created in the database');
        $this->assertEquals('public', $group->visibility);
    }

    public function test_create_group_requires_name(): void
    {
        Sanctum::actingAs($this->owner, ['*']);

        $response = $this->apiPost('/v2/groups', [
            'description' => 'A group without a name',
            'visibility'  => 'public',
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    // =========================================================================
    // Membership
    // =========================================================================

    public function test_user_can_join_public_group(): void
    {
        $group = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id'   => $this->owner->id,
            'visibility' => 'public',
            'cached_member_count' => 1,
        ]);

        // Owner should be auto-added as a member (simulate)
        GroupMember::create([
            'tenant_id' => $this->testTenantId,
            'group_id'  => $group->id,
            'user_id'   => $this->owner->id,
            'role'      => 'owner',
            'status'    => 'active',
        ]);

        Sanctum::actingAs($this->memberUser, ['*']);

        $response = $this->apiPost("/v2/groups/{$group->id}/join");
        $this->assertContains($response->getStatusCode(), [200, 201]);

        // Verify membership in the database
        $membership = GroupMember::where('group_id', $group->id)
            ->where('user_id', $this->memberUser->id)
            ->first();

        $this->assertNotNull($membership, 'User should be a member of the group');
        $this->assertContains($membership->status, ['active', 'pending']);
    }

    public function test_user_can_leave_group(): void
    {
        $group = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id'   => $this->owner->id,
            'visibility' => 'public',
        ]);

        // Add the member
        GroupMember::create([
            'tenant_id' => $this->testTenantId,
            'group_id'  => $group->id,
            'user_id'   => $this->memberUser->id,
            'role'      => 'member',
            'status'    => 'active',
        ]);

        Sanctum::actingAs($this->memberUser, ['*']);

        $response = $this->apiDelete("/v2/groups/{$group->id}/membership");
        $this->assertEquals(200, $response->getStatusCode());

        // Verify membership is removed or status changed
        $membership = GroupMember::where('group_id', $group->id)
            ->where('user_id', $this->memberUser->id)
            ->where('status', 'active')
            ->first();

        $this->assertNull($membership, 'Active membership should be removed after leaving');
    }

    public function test_group_members_endpoint_returns_members(): void
    {
        $group = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id'   => $this->owner->id,
            'visibility' => 'public',
        ]);

        GroupMember::create([
            'tenant_id' => $this->testTenantId,
            'group_id'  => $group->id,
            'user_id'   => $this->owner->id,
            'role'      => 'owner',
            'status'    => 'active',
        ]);

        GroupMember::create([
            'tenant_id' => $this->testTenantId,
            'group_id'  => $group->id,
            'user_id'   => $this->memberUser->id,
            'role'      => 'member',
            'status'    => 'active',
        ]);

        Sanctum::actingAs($this->owner, ['*']);

        $response = $this->apiGet("/v2/groups/{$group->id}/members");
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->json('data') ?? $response->json();
        $members = $data['data'] ?? $data;

        // Should have at least 2 members (owner + member)
        $this->assertGreaterThanOrEqual(2, count($members));
    }

    // =========================================================================
    // Discussions
    // =========================================================================

    public function test_create_group_discussion(): void
    {
        $group = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id'   => $this->owner->id,
            'visibility' => 'public',
        ]);

        // Owner must be a member to create discussions
        GroupMember::create([
            'tenant_id' => $this->testTenantId,
            'group_id'  => $group->id,
            'user_id'   => $this->owner->id,
            'role'      => 'owner',
            'status'    => 'active',
        ]);

        Sanctum::actingAs($this->owner, ['*']);

        $response = $this->apiPost("/v2/groups/{$group->id}/discussions", [
            'title' => 'Best plants for beginners',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);

        // Verify discussion exists
        $discussion = GroupDiscussion::where('group_id', $group->id)
            ->where('user_id', $this->owner->id)
            ->first();

        $this->assertNotNull($discussion, 'Discussion should be created');
        $this->assertEquals('Best plants for beginners', $discussion->title);
    }

    public function test_post_to_group_discussion(): void
    {
        $group = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id'   => $this->owner->id,
            'visibility' => 'public',
        ]);

        GroupMember::create([
            'tenant_id' => $this->testTenantId,
            'group_id'  => $group->id,
            'user_id'   => $this->owner->id,
            'role'      => 'owner',
            'status'    => 'active',
        ]);

        GroupMember::create([
            'tenant_id' => $this->testTenantId,
            'group_id'  => $group->id,
            'user_id'   => $this->memberUser->id,
            'role'      => 'member',
            'status'    => 'active',
        ]);

        $discussion = GroupDiscussion::create([
            'tenant_id' => $this->testTenantId,
            'group_id'  => $group->id,
            'user_id'   => $this->owner->id,
            'title'     => 'Discussion Thread',
        ]);

        // Member posts to the discussion
        Sanctum::actingAs($this->memberUser, ['*']);

        $response = $this->apiPost(
            "/v2/groups/{$group->id}/discussions/{$discussion->id}/messages",
            [
                'content' => 'I think tomatoes are great for beginners!',
            ]
        );

        $this->assertContains($response->getStatusCode(), [200, 201]);
    }

    public function test_list_group_discussions(): void
    {
        $group = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id'   => $this->owner->id,
            'visibility' => 'public',
        ]);

        GroupMember::create([
            'tenant_id' => $this->testTenantId,
            'group_id'  => $group->id,
            'user_id'   => $this->owner->id,
            'role'      => 'owner',
            'status'    => 'active',
        ]);

        // Create a couple of discussions
        GroupDiscussion::create([
            'tenant_id' => $this->testTenantId,
            'group_id'  => $group->id,
            'user_id'   => $this->owner->id,
            'title'     => 'Discussion One',
        ]);

        GroupDiscussion::create([
            'tenant_id' => $this->testTenantId,
            'group_id'  => $group->id,
            'user_id'   => $this->owner->id,
            'title'     => 'Discussion Two',
        ]);

        Sanctum::actingAs($this->owner, ['*']);

        $response = $this->apiGet("/v2/groups/{$group->id}/discussions");
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->json('data') ?? $response->json();
        $discussions = $data['data'] ?? $data;
        $this->assertGreaterThanOrEqual(2, count($discussions));
    }

    // =========================================================================
    // Full Group Lifecycle
    // =========================================================================

    public function test_full_group_lifecycle(): void
    {
        // Step 1: Owner creates a group
        Sanctum::actingAs($this->owner, ['*']);

        $createResponse = $this->apiPost('/v2/groups', [
            'name'        => 'Lifecycle Test Group',
            'description' => 'Testing the full group lifecycle.',
            'visibility'  => 'public',
        ]);

        $this->assertContains($createResponse->getStatusCode(), [200, 201]);

        $groupData = $createResponse->json('data') ?? $createResponse->json();
        $groupId = $groupData['id'] ?? $groupData['data']['id'] ?? null;

        if (!$groupId) {
            // Fallback: find group by name
            $group = Group::where('tenant_id', $this->testTenantId)
                ->where('name', 'Lifecycle Test Group')
                ->first();
            $this->assertNotNull($group);
            $groupId = $group->id;
        }

        // Step 2: Another user joins
        Sanctum::actingAs($this->memberUser, ['*']);

        $joinResponse = $this->apiPost("/v2/groups/{$groupId}/join");
        $this->assertContains($joinResponse->getStatusCode(), [200, 201]);

        // Step 3: Owner creates a discussion
        Sanctum::actingAs($this->owner, ['*']);

        $discussionResponse = $this->apiPost("/v2/groups/{$groupId}/discussions", [
            'title' => 'Welcome to our group!',
        ]);
        $this->assertContains($discussionResponse->getStatusCode(), [200, 201]);

        $discussionData = $discussionResponse->json('data') ?? $discussionResponse->json();
        $discussionId = $discussionData['id'] ?? $discussionData['data']['id'] ?? null;

        if (!$discussionId) {
            $discussion = GroupDiscussion::where('group_id', $groupId)->first();
            $this->assertNotNull($discussion);
            $discussionId = $discussion->id;
        }

        // Step 4: Member posts in the discussion
        Sanctum::actingAs($this->memberUser, ['*']);

        $postResponse = $this->apiPost(
            "/v2/groups/{$groupId}/discussions/{$discussionId}/messages",
            ['content' => 'Thanks for having me!']
        );
        $this->assertContains($postResponse->getStatusCode(), [200, 201]);

        // Step 5: Verify member count
        $memberCount = GroupMember::where('group_id', $groupId)
            ->where('status', 'active')
            ->count();
        $this->assertGreaterThanOrEqual(2, $memberCount, 'Group should have at least owner + 1 member');
    }

    public function test_non_member_cannot_post_to_discussion(): void
    {
        $group = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id'   => $this->owner->id,
            'visibility' => 'public',
        ]);

        GroupMember::create([
            'tenant_id' => $this->testTenantId,
            'group_id'  => $group->id,
            'user_id'   => $this->owner->id,
            'role'      => 'owner',
            'status'    => 'active',
        ]);

        $discussion = GroupDiscussion::create([
            'tenant_id' => $this->testTenantId,
            'group_id'  => $group->id,
            'user_id'   => $this->owner->id,
            'title'     => 'Private Discussion',
        ]);

        // Non-member tries to post
        $outsider = User::factory()->forTenant($this->testTenantId)->create([
            'status'      => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($outsider, ['*']);

        $response = $this->apiPost(
            "/v2/groups/{$group->id}/discussions/{$discussion->id}/messages",
            ['content' => 'I should not be allowed to post here']
        );

        // Should be forbidden (403) or not found (404)
        $this->assertContains($response->getStatusCode(), [400, 403, 404]);
    }

    public function test_group_not_visible_cross_tenant(): void
    {
        $group = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id'   => $this->owner->id,
            'visibility' => 'public',
        ]);

        // Create a user on a different tenant
        $otherTenantId = 998;
        DB::table('tenants')->insertOrIgnore([
            'id'         => $otherTenantId,
            'name'       => 'Other Community',
            'slug'       => 'other-community',
            'is_active'  => true,
            'depth'      => 0,
            'allows_subtenants' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherUser = User::factory()->forTenant($otherTenantId)->create([
            'status'      => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($otherUser, ['*']);

        // Request with the other tenant's header
        $response = $this->getJson(
            "/api/v2/groups/{$group->id}",
            array_merge($this->withTenantHeader(), ['X-Tenant-ID' => (string) $otherTenantId])
        );

        // Group should not be visible to another tenant
        $this->assertContains($response->getStatusCode(), [403, 404]);
    }
}
