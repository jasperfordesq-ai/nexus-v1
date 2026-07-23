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
use Tests\Laravel\TestCase;

/**
 * Regression tests: signup welcome credits must never award exchange badges.
 *
 * New members were getting "First Exchange" / "First Earn" / "First Spend"
 * the moment badge checks ran, because their signup credits were counted as
 * transactions:
 *  - StartingBalanceService writes a 'starting_balance' transaction (sender 0);
 *  - AdminUsersController::grantWelcomeCredits historically wrote a SELF
 *    'transfer' ([Welcome Bonus]…, sender = receiver = user), which counted
 *    as earned AND spent AND a completed exchange.
 *
 * GamificationService::realExchangesOnly() now excludes system grant types,
 * system-sent rows, self-transactions and legacy '[Welcome Bonus]' rows from
 * every transaction-based badge count, and the
 * gamification:revoke-grant-badges command cleans up historic awards.
 */
class GamificationGrantBadgesTest extends TestCase
{
    use DatabaseTransactions;

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

    private function pinTenant(): void
    {
        TenantContext::setById($this->testTenantId);
    }

    private function insertTransaction(array $overrides): void
    {
        DB::table('transactions')->insert(array_merge([
            'tenant_id'        => $this->testTenantId,
            'sender_id'        => 0,
            'receiver_id'      => null,
            'amount'           => 5,
            'description'      => 'Starting balance credit',
            'status'           => 'completed',
            'transaction_type' => 'starting_balance',
            'created_at'       => now(),
        ], $overrides));
    }

    private function badgeKeys(int $userId): array
    {
        return DB::table('user_badges')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $userId)
            ->pluck('badge_key')
            ->all();
    }

    public function test_starting_balance_grant_awards_no_exchange_badges(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['balance' => 5]);
        $this->insertTransaction(['receiver_id' => $user->id]);

        $this->pinTenant();
        GamificationService::runAllBadgeChecks($user->id);

        $keys = $this->badgeKeys($user->id);
        foreach (['earn_1', 'spend_1', 'transaction_1', 'diversity_3'] as $forbidden) {
            $this->assertNotContains($forbidden, $keys, "Signup credit must not award {$forbidden}");
        }
    }

    public function test_legacy_welcome_bonus_self_transfer_awards_no_exchange_badges(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['balance' => 5]);
        // Historic shape written by grantWelcomeCredits before the fix:
        // self-'transfer' with the [Welcome Bonus] description.
        $this->insertTransaction([
            'sender_id'        => $user->id,
            'receiver_id'      => $user->id,
            'description'      => '[Welcome Bonus] New member welcome credits (approved by admin #1)',
            'transaction_type' => 'transfer',
        ]);

        $this->pinTenant();
        GamificationService::runAllBadgeChecks($user->id);

        $keys = $this->badgeKeys($user->id);
        foreach (['earn_1', 'spend_1', 'transaction_1'] as $forbidden) {
            $this->assertNotContains($forbidden, $keys, "Legacy welcome bonus must not award {$forbidden}");
        }
    }

    public function test_admin_grant_awards_no_exchange_badges(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['balance' => 10]);
        $this->insertTransaction([
            'receiver_id'      => $user->id,
            'amount'           => 10,
            'description'      => 'Admin grant',
            'transaction_type' => 'admin_grant',
        ]);

        $this->pinTenant();
        GamificationService::runAllBadgeChecks($user->id);

        $this->assertNotContains('earn_1', $this->badgeKeys($user->id));
        $this->assertNotContains('transaction_1', $this->badgeKeys($user->id));
    }

    public function test_real_member_exchange_still_awards_badges(): void
    {
        $receiver = User::factory()->forTenant($this->testTenantId)->create();
        $sender = User::factory()->forTenant($this->testTenantId)->create();
        $this->insertTransaction([
            'sender_id'        => $sender->id,
            'receiver_id'      => $receiver->id,
            'amount'           => 2,
            'description'      => 'Helped with gardening',
            'transaction_type' => 'transfer',
        ]);

        $this->pinTenant();
        GamificationService::runAllBadgeChecks($receiver->id);

        $keys = $this->badgeKeys($receiver->id);
        $this->assertContains('earn_1', $keys, 'A real exchange must still award First Earn');
        $this->assertContains('transaction_1', $keys, 'A real exchange must still award First Exchange');
    }

    public function test_real_exchange_counts_ignore_grants(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        $partner = User::factory()->forTenant($this->testTenantId)->create();

        // One grant + one legacy self welcome bonus + one real exchange.
        $this->insertTransaction(['receiver_id' => $user->id]);
        $this->insertTransaction([
            'sender_id'        => $user->id,
            'receiver_id'      => $user->id,
            'description'      => '[Welcome Bonus] New member welcome credits (approved by admin #1)',
            'transaction_type' => 'transfer',
        ]);
        $this->insertTransaction([
            'sender_id'        => $partner->id,
            'receiver_id'      => $user->id,
            'amount'           => 3,
            'description'      => 'Real exchange',
            'transaction_type' => 'transfer',
        ]);

        $this->pinTenant();
        $counts = GamificationService::getRealExchangeCounts($user->id);

        $this->assertSame(3, $counts['earn'], 'Only the real exchange counts as earned');
        $this->assertSame(0, $counts['spend']);
        $this->assertSame(1, $counts['transaction'], 'Grants must not count as transactions');
        $this->assertSame(0, $counts['diversity']);
    }

    public function test_revoke_command_removes_unearned_badges_xp_and_feed_card(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(['xp' => 25, 'level' => 1]);
        $this->insertTransaction(['receiver_id' => $user->id]);

        // Historic wrong award: badge + its XP + its feed card.
        DB::table('user_badges')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id'   => $user->id,
            'badge_key' => 'transaction_1',
            'name'      => 'First Exchange',
            'icon'      => '🔄',
        ]);
        DB::table('user_xp_log')->insert([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $user->id,
            'xp_amount'   => 25,
            'action'      => 'earn_badge',
            'description' => 'Badge: First Exchange',
        ]);
        DB::table('feed_activity')->insert([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $user->id,
            'source_type' => 'badge_earned',
            'source_id'   => $user->id,
            'title'       => 'First Exchange',
            'content'     => 'Earned the "First Exchange" badge!',
            'metadata'    => json_encode(['badge_key' => 'transaction_1']),
            'is_visible'  => 1,
            'created_at'  => now(),
        ]);

        // Dry run changes nothing.
        $this->artisan('gamification:revoke-grant-badges', ['--tenant' => $this->testTenantId])
            ->assertExitCode(0);
        $this->assertContains('transaction_1', $this->badgeKeys($user->id));

        // Apply revokes badge, XP and feed card.
        $this->artisan('gamification:revoke-grant-badges', ['--tenant' => $this->testTenantId, '--apply' => true])
            ->assertExitCode(0);

        $this->assertNotContains('transaction_1', $this->badgeKeys($user->id));
        $this->assertSame(0, (int) DB::table('users')->where('id', $user->id)->value('xp'));
        $this->assertSame(0, DB::table('user_xp_log')
            ->where('tenant_id', $this->testTenantId)
            ->where('user_id', $user->id)
            ->where('action', 'earn_badge')
            ->count());
        $this->assertNull(DB::table('feed_activity')
            ->where('tenant_id', $this->testTenantId)
            ->where('source_type', 'badge_earned')
            ->where('source_id', $user->id)
            ->first());
    }

    public function test_revoke_command_keeps_legitimately_earned_badges(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        $partner = User::factory()->forTenant($this->testTenantId)->create();
        $this->insertTransaction(['receiver_id' => $user->id]); // grant
        $this->insertTransaction([
            'sender_id'        => $partner->id,
            'receiver_id'      => $user->id,
            'amount'           => 2,
            'description'      => 'Real exchange',
            'transaction_type' => 'transfer',
        ]);
        DB::table('user_badges')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id'   => $user->id,
            'badge_key' => 'transaction_1',
            'name'      => 'First Exchange',
            'icon'      => '🔄',
        ]);

        $this->artisan('gamification:revoke-grant-badges', ['--tenant' => $this->testTenantId, '--apply' => true])
            ->assertExitCode(0);

        $this->assertContains('transaction_1', $this->badgeKeys($user->id), 'Badge backed by a real exchange must survive cleanup');
    }
}
