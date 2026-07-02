<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\GamificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Regression tests for the gamification feed-card collapse bug.
 *
 * badge_earned / level_up feed_activity rows were written with a literal
 * source_id = 0. The uq_tenant_source unique key + the recordActivity upsert
 * then collapsed EVERY award in a tenant into one shared row per type: the
 * author was overwritten by each event (rendering nameless/mis-attributed
 * cards) and the feed served the card with id = 0, which the admin delete
 * endpoint could not address — deleting one card wiped the whole tenant's
 * and the row was re-created by the next award.
 *
 * The fix records source_id = user_id: one card per user per type, stable
 * attribution, and a real id the moderation surfaces can target.
 */
class GamificationFeedCardsTest extends TestCase
{
    use DatabaseTransactions;

    private const BADGE_A = ['key' => 'test_badge_a', 'name' => 'Test Badge A', 'icon' => '🏅'];
    private const BADGE_B = ['key' => 'test_badge_b', 'name' => 'Test Badge B', 'icon' => '🎖️'];

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tenant_settings')->insertOrIgnore([
            [
                'tenant_id'     => $this->testTenantId,
                'category'      => 'general',
                'setting_key'   => 'gamification_enabled',
                'setting_value' => '1',
                'setting_type'  => 'boolean',
            ],
        ]);
    }

    /**
     * Factory creates fire model events whose scoped listeners reset
     * TenantContext in CLI (see AwardXpOnVolLogApprovedTest) — re-pin it
     * before every direct GamificationService call so tenant-scoped writes
     * land on the test tenant instead of the fallback tenant.
     */
    private function pinTenant(): void
    {
        TenantContext::setById($this->testTenantId);
    }

    private function badgeCardFor(int $userId): ?object
    {
        return DB::table('feed_activity')
            ->where('tenant_id', $this->testTenantId)
            ->where('source_type', 'badge_earned')
            ->where('source_id', $userId)
            ->first();
    }

    // =========================================================================
    // Write path — per-user cards, no tenant-wide collapse
    // =========================================================================

    public function test_badge_awards_for_two_users_create_two_feed_cards(): void
    {
        $userA = User::factory()->forTenant($this->testTenantId)->create();
        $userB = User::factory()->forTenant($this->testTenantId)->create();

        $this->pinTenant();
        GamificationService::awardBadge($userA->id, self::BADGE_A);
        $this->pinTenant();
        GamificationService::awardBadge($userB->id, self::BADGE_B);

        $cardA = $this->badgeCardFor($userA->id);
        $cardB = $this->badgeCardFor($userB->id);

        $this->assertNotNull($cardA, 'User A should have their own badge feed card (source_id = user id)');
        $this->assertNotNull($cardB, 'User B should have their own badge feed card (source_id = user id)');
        $this->assertSame($userA->id, (int) $cardA->user_id, 'Card A must stay attributed to user A');
        $this->assertSame($userB->id, (int) $cardB->user_id, 'Card B must stay attributed to user B');
        $this->assertStringContainsString(self::BADGE_A['name'], (string) $cardA->content);
        $this->assertStringContainsString(self::BADGE_B['name'], (string) $cardB->content);
    }

    public function test_second_badge_for_same_user_updates_only_their_card(): void
    {
        $userA = User::factory()->forTenant($this->testTenantId)->create();
        $userB = User::factory()->forTenant($this->testTenantId)->create();

        $this->pinTenant();
        GamificationService::awardBadge($userA->id, self::BADGE_A);
        $this->pinTenant();
        GamificationService::awardBadge($userB->id, self::BADGE_A);
        $this->pinTenant();
        GamificationService::awardBadge($userA->id, self::BADGE_B);

        $cardA = $this->badgeCardFor($userA->id);
        $cardB = $this->badgeCardFor($userB->id);

        // User A's card upserted in place to the latest badge…
        $this->assertNotNull($cardA);
        $this->assertStringContainsString(self::BADGE_B['name'], (string) $cardA->content);
        // …without touching user B's card or its attribution.
        $this->assertNotNull($cardB);
        $this->assertSame($userB->id, (int) $cardB->user_id);
        $this->assertStringContainsString(self::BADGE_A['name'], (string) $cardB->content);
    }

    public function test_level_up_creates_per_user_feed_card(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['xp' => 0, 'level' => 1]);

        // 150 XP crosses the level-2 threshold (100) — checkLevelUp records the card.
        $this->pinTenant();
        GamificationService::awardXP($user->id, 150, 'test_action', 'Regression test XP');

        $card = DB::table('feed_activity')
            ->where('tenant_id', $this->testTenantId)
            ->where('source_type', 'level_up')
            ->where('source_id', $user->id)
            ->first();

        $this->assertNotNull($card, 'Level-up feed card should exist with source_id = user id');
        $this->assertSame($user->id, (int) $card->user_id);
        $this->assertNotSame(0, (int) $card->source_id, 'Gamification cards must never be written with source_id 0');
    }

    // =========================================================================
    // Delete path — admin can remove exactly one user's card
    // =========================================================================

    public function test_admin_delete_removes_one_users_card_and_spares_others(): void
    {
        $userA = User::factory()->forTenant($this->testTenantId)->create();
        $userB = User::factory()->forTenant($this->testTenantId)->create();
        $this->pinTenant();
        GamificationService::awardBadge($userA->id, self::BADGE_A);
        $this->pinTenant();
        GamificationService::awardBadge($userB->id, self::BADGE_B);

        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiDelete("/v2/admin/feed/posts/{$userA->id}?type=badge_earned");

        $response->assertStatus(200);
        $this->assertNull($this->badgeCardFor($userA->id), 'Deleted card must be gone');
        $this->assertNotNull($this->badgeCardFor($userB->id), 'Other users\' cards must survive the delete');
    }

    // =========================================================================
    // Read path — empty users.name must not render a nameless card
    // =========================================================================

    public function test_feed_author_name_falls_back_when_name_column_is_empty(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'first_name' => 'Sarah',
            'last_name'  => 'Bird',
        ]);
        // users.name is NOT NULL — an empty string is the state that used to
        // slip through COALESCE and render a nameless post.
        DB::table('users')->where('id', $user->id)->update(['name' => '']);

        $this->pinTenant();
        GamificationService::awardBadge($user->id, self::BADGE_A);

        $viewer = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($viewer);

        $response = $this->apiGet('/v2/feed?type=all&limit=50');
        $response->assertStatus(200);

        $items = $response->json('data.items') ?? $response->json('data') ?? [];
        $card = collect($items)->first(
            fn ($item) => ($item['type'] ?? '') === 'badge_earned' && (int) ($item['id'] ?? 0) === $user->id
        );

        $this->assertNotNull($card, 'Badge card should appear in the feed with id = user id');
        $this->assertSame('Sarah Bird', $card['author']['name'] ?? null, 'Empty users.name must fall back to first + last name');
    }
}
