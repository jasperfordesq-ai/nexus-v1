<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Groups;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\GroupRecommendationEngine;
use App\Services\GroupRecommendationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

final class GroupRecommendationTenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private GroupRecommendationEngine $engine;
    private GroupRecommendationService $service;
    private User $user;
    private User $currentMember;
    private User $admin;
    private User $foreignUser;
    private int $publicGroupId;
    private int $secondPublicGroupId;
    private int $privateGroupId;
    private int $archivedGroupId;
    private int $foreignGroupId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertTrue(
            Schema::hasTable('group_recommendation_interactions'),
            'Recommendation interaction storage is required for tenant-safe tracking.',
        );

        $this->engine = new GroupRecommendationEngine();
        $this->service = new GroupRecommendationService();
        $this->user = User::factory()->forTenant($this->testTenantId)->create([
            'username' => 'g06_recommend_user',
            'bio' => null,
            'latitude' => null,
            'longitude' => null,
        ]);
        $this->currentMember = User::factory()->forTenant($this->testTenantId)->create([
            'username' => 'g06_recommend_member',
        ]);
        $this->admin = User::factory()->forTenant($this->testTenantId)->admin()->create([
            'username' => 'g06_recommend_admin',
        ]);
        $this->foreignUser = User::factory()->forTenant(999)->create([
            'username' => 'g06_recommend_foreign',
        ]);

        TenantContext::setById($this->testTenantId);
        $this->publicGroupId = $this->insertGroup(
            $this->testTenantId,
            (int) $this->currentMember->id,
            'active',
            'public',
            false,
        );
        $this->secondPublicGroupId = $this->insertGroup(
            $this->testTenantId,
            (int) $this->currentMember->id,
            'active',
            'public',
        );
        $this->privateGroupId = $this->insertGroup(
            $this->testTenantId,
            (int) $this->currentMember->id,
            'active',
            'private',
        );
        $this->archivedGroupId = $this->insertGroup(
            $this->testTenantId,
            (int) $this->currentMember->id,
            'archived',
            'public',
        );
        $this->foreignGroupId = $this->insertGroup(
            999,
            (int) $this->foreignUser->id,
            'active',
            'public',
        );

        $this->insertMembership(
            $this->publicGroupId,
            (int) $this->currentMember->id,
            $this->testTenantId,
        );
        // A malformed cross-tenant membership sharing the current group id
        // must never inflate current-tenant recommendation counts.
        $this->insertMembership(
            $this->publicGroupId,
            (int) $this->foreignUser->id,
            999,
        );
        $this->insertMembership(
            $this->foreignGroupId,
            (int) $this->foreignUser->id,
            999,
        );
    }

    public function test_recommendations_only_return_active_public_current_tenant_groups(): void
    {
        $engineRecommendations = $this->engine->getRecommendations((int) $this->user->id, 500);
        $engineIds = array_map('intval', array_column($engineRecommendations, 'id'));
        $this->assertContains($this->publicGroupId, $engineIds);
        $this->assertContains($this->secondPublicGroupId, $engineIds);
        $this->assertNotContains($this->privateGroupId, $engineIds);
        $this->assertNotContains($this->archivedGroupId, $engineIds);
        $this->assertNotContains($this->foreignGroupId, $engineIds);
        $this->assertRecommendableCurrentTenantIds($engineIds);

        $first = collect($engineRecommendations)->firstWhere('id', $this->publicGroupId);
        $this->assertSame(1, (int) ($first['member_count'] ?? -1));

        $serviceRecommendations = $this->service->getRecommendations((int) $this->user->id, 500);
        $serviceIds = array_map('intval', array_column($serviceRecommendations, 'id'));
        $this->assertContains($this->publicGroupId, $serviceIds);
        $this->assertContains($this->secondPublicGroupId, $serviceIds);
        $this->assertNotContains($this->privateGroupId, $serviceIds);
        $this->assertNotContains($this->archivedGroupId, $serviceIds);
        $this->assertNotContains($this->foreignGroupId, $serviceIds);
        $this->assertRecommendableCurrentTenantIds($serviceIds);
        $serviceFirst = collect($serviceRecommendations)->firstWhere('id', $this->publicGroupId);
        $this->assertSame(1, (int) ($serviceFirst['member_count'] ?? -1));

        $this->assertSame([], $this->engine->getRecommendations((int) $this->foreignUser->id, 20));
        $this->assertSame([], $this->service->getRecommendations((int) $this->foreignUser->id, 20));
    }

    public function test_tracking_validates_both_tenant_sides_and_uses_one_canonical_table(): void
    {
        $this->assertTrue($this->engine->trackInteraction(
            (int) $this->user->id,
            $this->publicGroupId,
            'viewed',
        ));
        $this->assertFalse($this->engine->trackInteraction(
            (int) $this->user->id,
            $this->foreignGroupId,
            'clicked',
        ));
        $this->assertFalse($this->engine->trackInteraction(
            (int) $this->foreignUser->id,
            $this->publicGroupId,
            'clicked',
        ));
        $this->assertFalse($this->engine->trackInteraction(
            (int) $this->user->id,
            $this->privateGroupId,
            'clicked',
        ));
        $this->assertFalse($this->engine->trackInteraction(
            (int) $this->user->id,
            $this->archivedGroupId,
            'clicked',
        ));

        $this->assertTrue($this->service->track(
            (int) $this->user->id,
            $this->secondPublicGroupId,
            'click',
        ));
        $this->assertFalse($this->service->track(
            (int) $this->user->id,
            $this->foreignGroupId,
            'click',
        ));

        $rows = DB::table('group_recommendation_interactions')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $this->user->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $rows);
        $this->assertSame(
            ['viewed', 'clicked'],
            $rows->pluck('action')->all(),
        );
        $this->assertSame(
            [$this->publicGroupId, $this->secondPublicGroupId],
            $rows->pluck('group_id')->map(static fn ($id): int => (int) $id)->all(),
        );
    }

    public function test_metrics_are_scoped_to_current_tenant(): void
    {
        DB::table('group_recommendation_interactions')->insert([
            [
                'tenant_id' => $this->testTenantId,
                'user_id' => $this->user->id,
                'group_id' => $this->publicGroupId,
                'action' => 'clicked',
                'created_at' => now(),
            ],
            [
                'tenant_id' => 999,
                'user_id' => $this->foreignUser->id,
                'group_id' => $this->foreignGroupId,
                'action' => 'clicked',
                'created_at' => now(),
            ],
            [
                'tenant_id' => 999,
                'user_id' => $this->foreignUser->id,
                'group_id' => $this->foreignGroupId,
                'action' => 'viewed',
                'created_at' => now(),
            ],
        ]);

        $metrics = collect($this->engine->getPerformanceMetrics(30))->keyBy('action');
        $this->assertSame(1, (int) ($metrics->get('clicked')['count'] ?? 0));
        $this->assertFalse($metrics->has('viewed'));

        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->apiGet('/v2/groups/recommendations/metrics?days=30')->assertOk();
        $httpMetrics = collect($response->json('data'))->keyBy('action');
        $this->assertSame(1, (int) ($httpMetrics->get('clicked')['count'] ?? 0));
        $this->assertFalse($httpMetrics->has('viewed'));
    }

    public function test_tracking_and_similar_endpoints_conceal_foreign_sources(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->apiPost('/v2/groups/recommendations/track', [
            'group_id' => $this->foreignGroupId,
            'action' => 'clicked',
        ])->assertNotFound();
        $this->apiGet("/recommendations/similar/{$this->foreignGroupId}")
            ->assertNotFound();
        $this->apiGet("/recommendations/similar/{$this->privateGroupId}")
            ->assertNotFound();

        $this->apiPost('/v2/groups/recommendations/track', [
            'group_id' => $this->publicGroupId,
            'action' => 'clicked',
        ])->assertOk();
        $this->apiGet("/recommendations/similar/{$this->publicGroupId}")
            ->assertOk();

        $this->assertSame([], $this->service->similar($this->foreignGroupId, 10));
        $this->assertSame([], $this->service->similar($this->privateGroupId, 10));
    }

    private function insertGroup(
        int $tenantId,
        int $ownerId,
        string $status,
        string $visibility,
        bool $legacyActive = true,
    ): int {
        return (int) DB::table('groups')->insertGetId([
            'tenant_id' => $tenantId,
            'owner_id' => $ownerId,
            'name' => 'G06 recommendation ' . uniqid('', true),
            'slug' => 'g06-recommend-' . $tenantId . '-' . uniqid(),
            'description' => 'Recommendation tenant-isolation fixture.',
            'visibility' => $visibility,
            'status' => $status,
            'is_active' => $legacyActive,
            'is_featured' => false,
            'cached_member_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertMembership(int $groupId, int $userId, int $tenantId): void
    {
        DB::table('group_members')->insert([
            'tenant_id' => $tenantId,
            'group_id' => $groupId,
            'user_id' => $userId,
            'status' => 'active',
            'role' => 'member',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param list<int> $groupIds */
    private function assertRecommendableCurrentTenantIds(array $groupIds): void
    {
        $validCount = DB::table('groups')
            ->whereIn('id', $groupIds)
            ->where('tenant_id', $this->testTenantId)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('visibility')->orWhere('visibility', 'public');
            })
            ->count();

        $this->assertCount($validCount, $groupIds);
    }
}
