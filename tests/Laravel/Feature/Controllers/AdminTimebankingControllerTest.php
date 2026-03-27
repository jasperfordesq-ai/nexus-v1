<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AdminTimebankingController.
 *
 * Covers stats, alerts, updateAlert, adjustBalance, orgWallets,
 * userReport, userStatement.
 */
class AdminTimebankingControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // STATS — GET /v2/admin/timebanking/stats
    // ================================================================

    public function test_stats_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/timebanking/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total_transactions',
                'total_volume',
                'avg_transaction',
                'active_alerts',
                'top_earners',
                'top_spenders',
            ],
        ]);
    }

    public function test_stats_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/timebanking/stats');

        $response->assertStatus(403);
    }

    public function test_stats_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/timebanking/stats');

        $response->assertStatus(401);
    }

    // ================================================================
    // ALERTS — GET /v2/admin/timebanking/alerts
    // ================================================================

    public function test_alerts_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/timebanking/alerts');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_alerts_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/timebanking/alerts');

        $response->assertStatus(403);
    }

    // ================================================================
    // ADJUST BALANCE — POST /v2/admin/timebanking/adjust-balance
    // ================================================================

    public function test_adjust_balance_requires_user_id(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/timebanking/adjust-balance', [
            'amount' => 5.0,
            'reason' => 'Test adjustment',
        ]);

        $response->assertStatus(400);
    }

    public function test_adjust_balance_requires_nonzero_amount(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/timebanking/adjust-balance', [
            'user_id' => 1,
            'amount' => 0,
            'reason' => 'Test adjustment',
        ]);

        $response->assertStatus(400);
    }

    public function test_adjust_balance_requires_reason(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/timebanking/adjust-balance', [
            'user_id' => 1,
            'amount' => 5.0,
        ]);

        $response->assertStatus(400);
    }

    public function test_adjust_balance_returns_404_for_nonexistent_user(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/timebanking/adjust-balance', [
            'user_id' => 99999,
            'amount' => 5.0,
            'reason' => 'Test adjustment',
        ]);

        $response->assertStatus(404);
    }

    public function test_adjust_balance_returns_200_for_valid_request(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $user = User::factory()->forTenant($this->testTenantId)->create(['balance' => 10.0]);
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/timebanking/adjust-balance', [
            'user_id' => $user->id,
            'amount' => 5.0,
            'reason' => 'Bonus credits',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['user_id', 'previous_balance', 'adjustment', 'new_balance'],
        ]);
    }

    public function test_adjust_balance_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/timebanking/adjust-balance', [
            'user_id' => 1,
            'amount' => 5.0,
            'reason' => 'Test',
        ]);

        $response->assertStatus(403);
    }

    public function test_adjust_balance_creates_transaction_record(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $user = User::factory()->forTenant($this->testTenantId)->create(['balance' => 10.0]);
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/timebanking/adjust-balance', [
            'user_id' => $user->id,
            'amount' => 5.0,
            'reason' => 'Transaction integrity test',
        ]);

        $response->assertStatus(200);

        // Verify balance was updated
        $updatedUser = \Illuminate\Support\Facades\DB::selectOne(
            'SELECT balance FROM users WHERE id = ?',
            [$user->id]
        );
        $this->assertEquals(15.0, (float) $updatedUser->balance);

        // Verify transaction record was created
        $txn = \Illuminate\Support\Facades\DB::selectOne(
            "SELECT * FROM transactions WHERE tenant_id = ? AND receiver_id = ? AND description LIKE '%Transaction integrity test%'",
            [$this->testTenantId, $user->id]
        );
        $this->assertNotNull($txn, 'Transaction record must exist after balance adjustment');
        $this->assertEquals(5.0, (float) $txn->amount);
    }

    public function test_adjust_balance_rejects_negative_result(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $user = User::factory()->forTenant($this->testTenantId)->create(['balance' => 3.0]);
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/timebanking/adjust-balance', [
            'user_id' => $user->id,
            'amount' => -10.0,
            'reason' => 'Overdraft test',
        ]);

        $response->assertStatus(400);

        // Verify balance was NOT changed (transaction rolled back)
        $updatedUser = \Illuminate\Support\Facades\DB::selectOne(
            'SELECT balance FROM users WHERE id = ?',
            [$user->id]
        );
        $this->assertEquals(3.0, (float) $updatedUser->balance);
    }

    public function test_adjust_balance_negative_amount_creates_correct_transaction(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $user = User::factory()->forTenant($this->testTenantId)->create(['balance' => 20.0]);
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/timebanking/adjust-balance', [
            'user_id' => $user->id,
            'amount' => -5.0,
            'reason' => 'Deduction test',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(20.0, $data['previous_balance']);
        $this->assertEquals(15.0, $data['new_balance']);
        $this->assertEquals(-5.0, $data['adjustment']);

        // Verify transaction: user is sender (deduction), admin is receiver
        $txn = \Illuminate\Support\Facades\DB::selectOne(
            "SELECT * FROM transactions WHERE tenant_id = ? AND sender_id = ? AND description LIKE '%Deduction test%'",
            [$this->testTenantId, $user->id]
        );
        $this->assertNotNull($txn);
        $this->assertEquals($admin->id, (int) $txn->receiver_id);
        $this->assertEquals(5.0, (float) $txn->amount);
    }

    // ================================================================
    // ORG WALLETS — GET /v2/admin/timebanking/org-wallets
    // ================================================================

    public function test_org_wallets_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/timebanking/org-wallets');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ================================================================
    // USER REPORT — GET /v2/admin/timebanking/user-report
    // ================================================================

    public function test_user_report_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/timebanking/user-report');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_user_report_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/timebanking/user-report');

        $response->assertStatus(403);
    }

    // ================================================================
    // USER STATEMENT — GET /v2/admin/timebanking/user-statement
    // ================================================================

    public function test_user_statement_requires_user_id(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/timebanking/user-statement');

        $response->assertStatus(400);
    }

    public function test_user_statement_returns_404_for_nonexistent_user(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/timebanking/user-statement?user_id=99999');

        $response->assertStatus(404);
    }

    public function test_user_statement_returns_200_for_valid_user(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/timebanking/user-statement?user_id=' . $user->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => ['user', 'period', 'summary', 'transactions'],
        ]);
    }
}
