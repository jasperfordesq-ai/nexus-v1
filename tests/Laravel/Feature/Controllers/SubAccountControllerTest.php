<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Exceptions\SafeguardingPolicyException;
use App\Services\SafeguardingInteractionPolicy;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use App\Models\User;
use Mockery;

/**
 * Feature tests for SubAccountController — parent/child sub-account management.
 */
class SubAccountControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------------------
    //  GET /v2/users/me/sub-accounts
    // ------------------------------------------------------------------

    public function test_get_children_requires_auth(): void
    {
        $response = $this->apiGet('/v2/users/me/sub-accounts');

        $response->assertStatus(401);
    }

    public function test_get_children_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/users/me/sub-accounts');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/users/me/parent-accounts
    // ------------------------------------------------------------------

    public function test_get_parents_requires_auth(): void
    {
        $response = $this->apiGet('/v2/users/me/parent-accounts');

        $response->assertStatus(401);
    }

    public function test_get_parents_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/users/me/parent-accounts');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/users/me/sub-accounts
    // ------------------------------------------------------------------

    public function test_request_relationship_requires_auth(): void
    {
        $response = $this->apiPost('/v2/users/me/sub-accounts', [
            'child_email' => 'child@example.com',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  PUT /v2/users/me/sub-accounts/{id}/approve
    // ------------------------------------------------------------------

    public function test_approve_requires_auth(): void
    {
        $response = $this->apiPut('/v2/users/me/sub-accounts/1/approve');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DELETE /v2/users/me/sub-accounts/{id}
    // ------------------------------------------------------------------

    public function test_revoke_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/users/me/sub-accounts/1');

        $response->assertStatus(401);
    }

    public function test_request_relationship_denial_writes_no_pending_permissions(): void
    {
        $parent = $this->authenticatedUser();
        $child = User::factory()->forTenant($this->testTenantId)->create();

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with($parent->id, $child->id, $this->testTenantId, 'sub_account_request')
            ->andThrow(new SafeguardingPolicyException('VETTING_REQUIRED', 'Vetting required'));
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPost('/v2/users/me/sub-accounts', [
            'child_user_id' => $child->id,
            'relationship_type' => 'guardian',
            'permissions' => [
                'can_view_activity' => true,
                'can_view_messages' => true,
            ],
        ]);

        $response->assertStatus(403)->assertJsonPath('errors.0.code', 'VETTING_REQUIRED');
        $this->assertDatabaseMissing('account_relationships', [
            'tenant_id' => $this->testTenantId,
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
        ]);
    }

    public function test_request_relationship_allowed_checks_both_directions_and_keeps_requested_permissions(): void
    {
        $parent = $this->authenticatedUser();
        $child = User::factory()->forTenant($this->testTenantId)->create();

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with($parent->id, $child->id, $this->testTenantId, 'sub_account_request');
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with($child->id, $parent->id, $this->testTenantId, 'sub_account_request');
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPost('/v2/users/me/sub-accounts', [
            'child_user_id' => $child->id,
            'relationship_type' => 'guardian',
            'permissions' => ['can_view_messages' => true],
        ]);

        $response->assertCreated();
        $relationship = DB::table('account_relationships')
            ->where('tenant_id', $this->testTenantId)
            ->where('parent_user_id', $parent->id)
            ->where('child_user_id', $child->id)
            ->first();
        $this->assertNotNull($relationship);
        $this->assertSame('pending', $relationship->status);
        $permissions = json_decode((string) $relationship->permissions, true);
        $this->assertTrue((bool) ($permissions['can_view_messages'] ?? false));
    }

    public function test_approval_rechecks_stored_requested_permissions_and_denial_leaves_pending(): void
    {
        $child = $this->authenticatedUser();
        $parent = User::factory()->forTenant($this->testTenantId)->create();
        $permissions = [
            'can_view_activity' => true,
            'can_manage_listings' => false,
            'can_transact' => true,
            'can_view_messages' => true,
        ];
        $relationshipId = DB::table('account_relationships')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'relationship_type' => 'carer',
            'permissions' => json_encode($permissions),
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with($parent->id, $child->id, $this->testTenantId, 'sub_account_approval')
            ->andThrow(new SafeguardingPolicyException('SAFEGUARDING_CONTACT_RESTRICTED', 'Contact restricted'));
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPut("/v2/users/me/sub-accounts/{$relationshipId}/approve");

        $response->assertStatus(403)->assertJsonPath('errors.0.code', 'SAFEGUARDING_CONTACT_RESTRICTED');
        $relationship = DB::table('account_relationships')->where('id', $relationshipId)->first();
        $this->assertSame('pending', $relationship->status);
        $this->assertNull($relationship->approved_at);
        $this->assertSame($permissions, json_decode((string) $relationship->permissions, true));
    }

    public function test_permission_expansion_denial_leaves_permissions_unchanged(): void
    {
        $parent = $this->authenticatedUser();
        $child = User::factory()->forTenant($this->testTenantId)->create();
        $permissions = [
            'can_view_activity' => true,
            'can_manage_listings' => false,
            'can_transact' => false,
            'can_view_messages' => false,
        ];
        $relationshipId = $this->createActiveRelationship($parent, $child, $permissions);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldReceive('assertLocalContactAllowed')
            ->once()
            ->with($parent->id, $child->id, $this->testTenantId, 'sub_account_permission_expansion')
            ->andThrow(new SafeguardingPolicyException('SAFEGUARDING_POLICY_UNAVAILABLE', 'Policy unavailable'));
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPut("/v2/users/me/sub-accounts/{$relationshipId}/permissions", [
            'permissions' => ['can_view_messages' => true],
        ]);

        $response->assertStatus(503)->assertJsonPath('errors.0.code', 'SAFEGUARDING_POLICY_UNAVAILABLE');
        $this->assertSame($permissions, $this->relationshipPermissions($relationshipId));
    }

    public function test_permission_removal_remains_available_without_a_contact_gate(): void
    {
        $parent = $this->authenticatedUser();
        $child = User::factory()->forTenant($this->testTenantId)->create();
        $relationshipId = $this->createActiveRelationship($parent, $child, [
            'can_view_activity' => true,
            'can_manage_listings' => true,
            'can_transact' => true,
            'can_view_messages' => true,
        ]);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldNotReceive('assertLocalContactAllowed');
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiPut("/v2/users/me/sub-accounts/{$relationshipId}/permissions", [
            'permissions' => [
                'can_manage_listings' => false,
                'can_transact' => false,
                'can_view_messages' => false,
            ],
        ]);

        $response->assertOk();
        $permissions = $this->relationshipPermissions($relationshipId);
        $this->assertFalse((bool) $permissions['can_manage_listings']);
        $this->assertFalse((bool) $permissions['can_transact']);
        $this->assertFalse((bool) $permissions['can_view_messages']);
    }

    public function test_revoke_remains_available_without_a_contact_gate(): void
    {
        $parent = $this->authenticatedUser();
        $child = User::factory()->forTenant($this->testTenantId)->create();
        $relationshipId = $this->createActiveRelationship($parent, $child, [
            'can_view_activity' => true,
        ]);

        $policy = Mockery::mock(SafeguardingInteractionPolicy::class);
        $policy->shouldNotReceive('assertLocalContactAllowed');
        $this->app->instance(SafeguardingInteractionPolicy::class, $policy);

        $response = $this->apiDelete("/v2/users/me/sub-accounts/{$relationshipId}");

        $response->assertOk();
        $this->assertDatabaseHas('account_relationships', [
            'id' => $relationshipId,
            'status' => 'revoked',
        ]);
    }

    /** @param array<string, bool> $permissions */
    private function createActiveRelationship(User $parent, User $child, array $permissions): int
    {
        return (int) DB::table('account_relationships')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'relationship_type' => 'family',
            'permissions' => json_encode($permissions),
            'status' => 'active',
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, bool> */
    private function relationshipPermissions(int $relationshipId): array
    {
        $permissions = DB::table('account_relationships')
            ->where('id', $relationshipId)
            ->value('permissions');

        return json_decode((string) $permissions, true) ?: [];
    }
}
