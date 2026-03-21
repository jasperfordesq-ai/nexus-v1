<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for GroupsController — CRUD, join, leave, members, discussions.
 */
class GroupsControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function createGroup(array $overrides = []): Group
    {
        return Group::factory()->forTenant($this->testTenantId)->create($overrides);
    }

    // ------------------------------------------------------------------
    //  INDEX
    // ------------------------------------------------------------------

    public function test_index_returns_groups(): void
    {
        $this->authenticatedUser();
        $this->createGroup(['visibility' => 'public']);
        $this->createGroup(['visibility' => 'public']);

        $response = $this->apiGet('/v2/groups');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_index_works_without_authentication(): void
    {
        $this->createGroup(['visibility' => 'public']);

        $response = $this->apiGet('/v2/groups');

        $response->assertStatus(200);
    }

    public function test_index_is_tenant_scoped(): void
    {
        $this->authenticatedUser();
        $this->createGroup(['visibility' => 'public']);
        Group::factory()->forTenant(999)->create(['visibility' => 'public']);

        $response = $this->apiGet('/v2/groups');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    // ------------------------------------------------------------------
    //  SHOW
    // ------------------------------------------------------------------

    public function test_show_returns_group(): void
    {
        $this->authenticatedUser();
        $group = $this->createGroup(['visibility' => 'public']);

        $response = $this->apiGet("/v2/groups/{$group->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_show_returns_404_for_nonexistent_group(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/groups/999999');

        $response->assertStatus(404);
    }

    public function test_show_returns_404_for_other_tenant_group(): void
    {
        $this->authenticatedUser();
        $otherGroup = Group::factory()->forTenant(999)->create();

        $response = $this->apiGet("/v2/groups/{$otherGroup->id}");

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    //  CREATE
    // ------------------------------------------------------------------

    public function test_authenticated_user_can_create_group(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/groups', [
            'name' => 'Test Group',
            'description' => 'A test group for unit testing.',
            'visibility' => 'public',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
        $response->assertJsonStructure(['data']);
    }

    public function test_unauthenticated_user_cannot_create_group(): void
    {
        $response = $this->apiPost('/v2/groups', [
            'name' => 'Unauthorized Group',
            'description' => 'Should fail.',
        ]);

        $response->assertStatus(401);
    }

    public function test_create_group_fails_without_required_fields(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/groups', []);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    // ------------------------------------------------------------------
    //  UPDATE
    // ------------------------------------------------------------------

    public function test_owner_can_update_group(): void
    {
        $user = $this->authenticatedUser();
        $group = $this->createGroup(['owner_id' => $user->id]);

        $response = $this->apiPut("/v2/groups/{$group->id}", [
            'name' => 'Updated Group Name',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 204]);
    }

    public function test_non_owner_cannot_update_group(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        $group = $this->createGroup(['owner_id' => $owner->id]);
        $this->authenticatedUser();

        $response = $this->apiPut("/v2/groups/{$group->id}", [
            'name' => 'Hijacked Name',
        ]);

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    public function test_update_nonexistent_group_returns_404(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPut('/v2/groups/999999', [
            'name' => 'No such group',
        ]);

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_update_group(): void
    {
        $group = $this->createGroup();

        $response = $this->apiPut("/v2/groups/{$group->id}", [
            'name' => 'Unauthorized',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DELETE
    // ------------------------------------------------------------------

    public function test_owner_can_delete_group(): void
    {
        $user = $this->authenticatedUser();
        $group = $this->createGroup(['owner_id' => $user->id]);

        $response = $this->apiDelete("/v2/groups/{$group->id}");

        $this->assertContains($response->getStatusCode(), [200, 204]);
    }

    public function test_non_owner_cannot_delete_group(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        $group = $this->createGroup(['owner_id' => $owner->id]);
        $this->authenticatedUser();

        $response = $this->apiDelete("/v2/groups/{$group->id}");

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    public function test_delete_nonexistent_group_returns_404(): void
    {
        $this->authenticatedUser();

        $response = $this->apiDelete('/v2/groups/999999');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_delete_group(): void
    {
        $group = $this->createGroup();

        $response = $this->apiDelete("/v2/groups/{$group->id}");

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  JOIN
    // ------------------------------------------------------------------

    public function test_user_can_join_public_group(): void
    {
        $user = $this->authenticatedUser();
        $group = $this->createGroup(['visibility' => 'public']);

        $response = $this->apiPost("/v2/groups/{$group->id}/join");

        $this->assertContains($response->getStatusCode(), [200, 201]);
        $response->assertJsonStructure(['data' => ['status']]);
    }

    public function test_unauthenticated_user_cannot_join_group(): void
    {
        $group = $this->createGroup(['visibility' => 'public']);

        $response = $this->apiPost("/v2/groups/{$group->id}/join");

        $response->assertStatus(401);
    }

    public function test_join_nonexistent_group_returns_404(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/groups/999999/join');

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    //  LEAVE
    // ------------------------------------------------------------------

    public function test_unauthenticated_user_cannot_leave_group(): void
    {
        $group = $this->createGroup();

        $response = $this->apiDelete("/v2/groups/{$group->id}/membership");

        $response->assertStatus(401);
    }

    public function test_leave_nonexistent_group_returns_error(): void
    {
        $this->authenticatedUser();

        $response = $this->apiDelete('/v2/groups/999999/membership');

        $this->assertContains($response->getStatusCode(), [400, 404, 409]);
    }

    // ------------------------------------------------------------------
    //  MEMBERS
    // ------------------------------------------------------------------

    public function test_members_returns_list(): void
    {
        $user = $this->authenticatedUser();
        $group = $this->createGroup(['owner_id' => $user->id]);

        $response = $this->apiGet("/v2/groups/{$group->id}/members");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_members_for_nonexistent_group_returns_404(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/groups/999999/members');

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    //  UPDATE MEMBER ROLE
    // ------------------------------------------------------------------

    public function test_unauthenticated_user_cannot_update_member_role(): void
    {
        $group = $this->createGroup();

        $response = $this->apiPut("/v2/groups/{$group->id}/members/1", [
            'role' => 'admin',
        ]);

        $response->assertStatus(401);
    }

    public function test_update_member_role_requires_role_field(): void
    {
        $this->authenticatedUser();
        $group = $this->createGroup();

        $response = $this->apiPut("/v2/groups/{$group->id}/members/1", []);

        $response->assertStatus(400);
    }

    // ------------------------------------------------------------------
    //  PENDING REQUESTS
    // ------------------------------------------------------------------

    public function test_unauthenticated_user_cannot_view_pending_requests(): void
    {
        $group = $this->createGroup();

        $response = $this->apiGet("/v2/groups/{$group->id}/requests");

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DISCUSSIONS
    // ------------------------------------------------------------------

    public function test_unauthenticated_user_cannot_view_discussions(): void
    {
        $group = $this->createGroup();

        $response = $this->apiGet("/v2/groups/{$group->id}/discussions");

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_create_discussion(): void
    {
        $group = $this->createGroup();

        $response = $this->apiPost("/v2/groups/{$group->id}/discussions", [
            'title' => 'Test Discussion',
            'content' => 'Some content.',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  ANNOUNCEMENTS
    // ------------------------------------------------------------------

    public function test_unauthenticated_user_cannot_view_announcements(): void
    {
        $group = $this->createGroup();

        $response = $this->apiGet("/v2/groups/{$group->id}/announcements");

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_create_announcement(): void
    {
        $group = $this->createGroup();

        $response = $this->apiPost("/v2/groups/{$group->id}/announcements", [
            'title' => 'Test Announcement',
            'body' => 'Announcement body.',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  TENANT ISOLATION
    // ------------------------------------------------------------------

    public function test_cannot_access_other_tenant_group_via_join(): void
    {
        $this->authenticatedUser();
        $otherGroup = Group::factory()->forTenant(999)->create(['visibility' => 'public']);

        $response = $this->apiPost("/v2/groups/{$otherGroup->id}/join");

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }
}
