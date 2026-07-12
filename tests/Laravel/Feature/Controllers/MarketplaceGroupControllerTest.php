<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Enums\GroupStatus;
use App\Models\Group;
use App\Models\User;
use App\Services\TenantFeatureConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Smoke tests for MarketplaceGroupController.
 */
class MarketplaceGroupControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(array_merge(TenantFeatureConfig::FEATURE_DEFAULTS, [
                'groups' => true,
                'marketplace' => true,
            ]), JSON_THROW_ON_ERROR),
        ]);
        TenantContext::setById($this->testTenantId);
    }

    public function test_listings_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/groups/1/listings');
        $response->assertUnauthorized();
    }

    public function test_stats_requires_auth(): void
    {
        $response = $this->apiGet('/v2/marketplace/groups/1/stats');
        $response->assertUnauthorized();
    }

    public function test_active_member_can_view_group_marketplace_listings_and_stats(): void
    {
        [$group, $member] = $this->groupAndMember();
        Sanctum::actingAs($member, ['*']);

        $this->apiGet('/v2/marketplace/groups/' . $group->id . '/listings')->assertOk();
        $this->apiGet('/v2/marketplace/groups/' . $group->id . '/stats')->assertOk();
    }

    public function test_nonmember_is_forbidden_from_active_group_marketplace(): void
    {
        [$group] = $this->groupAndMember();
        $nonmember = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($nonmember, ['*']);

        $this->apiGet('/v2/marketplace/groups/' . $group->id . '/listings')
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
    }

    public function test_archived_parent_blocks_marketplace_even_for_existing_member(): void
    {
        [$group, $member] = $this->groupAndMember();
        DB::table('groups')->where('id', $group->id)->update([
            'status' => GroupStatus::Archived->value,
            'is_active' => false,
        ]);
        Sanctum::actingAs($member, ['*']);

        $this->apiGet('/v2/marketplace/groups/' . $group->id . '/stats')
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FORBIDDEN');
    }

    public function test_cross_tenant_group_is_concealed(): void
    {
        $foreignOwner = User::factory()->forTenant(999)->create();
        $foreign = Group::factory()->forTenant(999)->create(['owner_id' => $foreignOwner->id]);
        $viewer = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($viewer, ['*']);

        $this->apiGet('/v2/marketplace/groups/' . $foreign->id . '/listings')->assertNotFound();
    }

    /** @return array{Group, User} */
    private function groupAndMember(): array
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create();
        $member = User::factory()->forTenant($this->testTenantId)->create();
        $group = Group::factory()->forTenant($this->testTenantId)->create([
            'owner_id' => $owner->id,
            'status' => GroupStatus::Active->value,
            'is_active' => true,
        ]);
        DB::table('group_members')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $group->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$group, $member];
    }
}
