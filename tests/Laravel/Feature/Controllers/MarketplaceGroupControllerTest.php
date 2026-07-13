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

    public function test_group_price_cursor_preserves_ties_without_duplicates(): void
    {
        [$group, $member] = $this->groupAndMember();
        Sanctum::actingAs($member, ['*']);
        foreach ([10.0, 20.0, 20.0, 30.0] as $index => $price) {
            $this->createGroupMarketplaceListing($member, $price, "Cursor {$index}");
        }

        $first = $this->apiGet("/v2/marketplace/groups/{$group->id}/listings?sort=price_asc&limit=2")
            ->assertOk()
            ->assertJsonPath('meta.has_more', true);
        $cursor = (string) $first->json('meta.cursor');
        $second = $this->apiGet(
            "/v2/marketplace/groups/{$group->id}/listings?sort=price_asc&limit=2&cursor=" . urlencode($cursor)
        )->assertOk();

        $this->assertEquals([10.0, 20.0], array_column($first->json('data'), 'price'));
        $this->assertEquals([20.0, 30.0], array_column($second->json('data'), 'price'));
        $this->assertCount(4, array_unique(array_merge(
            array_column($first->json('data'), 'id'),
            array_column($second->json('data'), 'id'),
        )));
    }

    public function test_group_marketplace_hides_expired_listings_before_the_scheduler_runs(): void
    {
        [$group, $member] = $this->groupAndMember();
        Sanctum::actingAs($member, ['*']);
        $visibleId = $this->createGroupMarketplaceListing($member, 10, 'Visible group listing');
        $expiredId = $this->createGroupMarketplaceListing(
            $member,
            20,
            'Expired group listing',
            now()->subMinute(),
        );

        $response = $this->apiGet("/v2/marketplace/groups/{$group->id}/listings")
            ->assertOk();
        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($visibleId, $ids);
        $this->assertNotContains($expiredId, $ids);
        $this->apiGet("/v2/marketplace/groups/{$group->id}/stats")
            ->assertOk()
            ->assertJsonPath('data.active_listings', 1);
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

    private function createGroupMarketplaceListing(
        User $seller,
        float $price,
        string $title,
        mixed $expiresAt = null,
    ): int {
        return (int) DB::table('marketplace_listings')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => $title,
            'description' => 'Group marketplace pagination fixture.',
            'price' => $price,
            'price_currency' => 'EUR',
            'price_type' => 'fixed',
            'quantity' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'expires_at' => $expiresAt ?? now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
