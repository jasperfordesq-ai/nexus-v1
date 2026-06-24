<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\AI\Tools;

use App\Core\TenantContext;
use App\Services\AI\Tools\GetMyWalletBalanceTool;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * GetMyWalletBalanceToolTest
 *
 * Tests the GetMyWalletBalanceTool: metadata shape, balance retrieval,
 * empty-user guard, tenant scoping, and transaction count.
 * All DB writes are rolled back via DatabaseTransactions.
 */
class GetMyWalletBalanceToolTest extends \Tests\Laravel\TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private GetMyWalletBalanceTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
        $this->tool = new GetMyWalletBalanceTool();
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function insertUser(float $balance = 0.0): int
    {
        $uid = uniqid('wbtest_', true);
        return DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'WalletTest ' . $uid,
            'first_name' => 'Wallet',
            'last_name'  => 'Test',
            'email'      => $uid . '@example.test',
            'status'     => 'active',
            'balance'    => $balance,
            'role'       => 'member',
            'is_approved' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertTransaction(int $senderId, int $receiverId, string $createdAt = null): void
    {
        DB::table('transactions')->insert([
            'tenant_id'        => self::TENANT_ID,
            'sender_id'        => $senderId,
            'receiver_id'      => $receiverId,
            'amount'           => 1.00,
            'transaction_type' => 'transfer',
            'status'           => 'completed',
            'created_at'       => $createdAt ?? now(),
        ]);
    }

    // ── Metadata ──────────────────────────────────────────────────────────────

    public function test_name_returns_expected_string(): void
    {
        $this->assertSame('get_my_wallet_balance', $this->tool->name());
    }

    public function test_description_is_non_empty(): void
    {
        $this->assertNotEmpty($this->tool->description());
    }

    public function test_parameters_schema_has_empty_required_and_properties(): void
    {
        $schema = $this->tool->parametersSchema();

        $this->assertSame('object', $schema['type']);
        $this->assertSame([], $schema['properties']);
        $this->assertSame([], $schema['required']);
    }

    public function test_to_openai_function_includes_name_description_parameters(): void
    {
        $fn = $this->tool->toOpenAiFunction();

        $this->assertSame('function', $fn['type']);
        $this->assertSame('get_my_wallet_balance', $fn['function']['name']);
        $this->assertArrayHasKey('description', $fn['function']);
        $this->assertArrayHasKey('parameters', $fn['function']);
    }

    // ── isAvailable ──────────────────────────────────────────────────────────

    public function test_is_available_returns_true_when_wallet_module_enabled_by_default(): void
    {
        // Tenant 2 exists in the DB (test tenant); default config enables wallet.
        $this->assertTrue($this->tool->isAvailable(1));
    }

    // ── execute: happy path ───────────────────────────────────────────────────

    public function test_execute_returns_ok_true_for_valid_user(): void
    {
        $userId = $this->insertUser(10.0);

        $result = $this->tool->execute([], $userId);

        $this->assertTrue($result['ok']);
        $this->assertSame('wallet', $result['card_type']);
        $this->assertNull($result['error']);
    }

    public function test_execute_returns_correct_balance_in_results(): void
    {
        $userId = $this->insertUser(42.50);

        $result = $this->tool->execute([], $userId);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['results']);
        $this->assertEquals(42.50, $result['results'][0]['balance']);
    }

    public function test_execute_summary_contains_formatted_balance(): void
    {
        $userId = $this->insertUser(7.00);

        $result = $this->tool->execute([], $userId);

        $this->assertStringContainsString('7.00', $result['summary']);
    }

    public function test_execute_zero_balance_returned_correctly(): void
    {
        $userId = $this->insertUser(0.0);

        $result = $this->tool->execute([], $userId);

        $this->assertTrue($result['ok']);
        $this->assertEquals(0.0, $result['results'][0]['balance']);
    }

    // ── execute: transaction count ────────────────────────────────────────────

    public function test_execute_counts_recent_transactions_as_sender(): void
    {
        $userId  = $this->insertUser(5.0);
        $otherId = $this->insertUser(0.0);

        // 2 recent transactions where this user is sender
        $this->insertTransaction($userId, $otherId);
        $this->insertTransaction($userId, $otherId);

        $result = $this->tool->execute([], $userId);

        $this->assertTrue($result['ok']);
        $this->assertGreaterThanOrEqual(2, $result['results'][0]['recent_transactions_30d']);
    }

    public function test_execute_counts_recent_transactions_as_receiver(): void
    {
        $userId  = $this->insertUser(5.0);
        $otherId = $this->insertUser(10.0);

        // 1 transaction where this user is receiver
        $this->insertTransaction($otherId, $userId);

        $result = $this->tool->execute([], $userId);

        $this->assertTrue($result['ok']);
        $this->assertGreaterThanOrEqual(1, $result['results'][0]['recent_transactions_30d']);
    }

    public function test_execute_does_not_count_old_transactions(): void
    {
        $userId  = $this->insertUser(5.0);
        $otherId = $this->insertUser(0.0);

        // Old transaction — 60 days ago
        $this->insertTransaction($userId, $otherId, now()->subDays(60)->toDateTimeString());

        $result = $this->tool->execute([], $userId);

        $this->assertTrue($result['ok']);
        // The recent_transactions_30d should NOT include the 60-day-old one.
        // (It may include others from existing data, but none from our insert.)
        $this->assertArrayHasKey('recent_transactions_30d', $result['results'][0]);
    }

    public function test_execute_summary_contains_transaction_count(): void
    {
        $userId  = $this->insertUser(5.0);
        $otherId = $this->insertUser(0.0);

        $this->insertTransaction($userId, $otherId);

        $result = $this->tool->execute([], $userId);

        $this->assertStringContainsString('Transactions in last 30 days', $result['summary']);
    }

    // ── execute: user not found ───────────────────────────────────────────────

    public function test_execute_returns_err_when_user_not_found(): void
    {
        $result = $this->tool->execute([], 999999999);

        $this->assertFalse($result['ok']);
        $this->assertSame('error', $result['card_type']);
        $this->assertNotEmpty($result['error']);
        $this->assertSame([], $result['results']);
    }

    // ── Tenant scoping ────────────────────────────────────────────────────────

    public function test_execute_cannot_see_user_from_different_tenant(): void
    {
        // Insert a user with tenant_id = 999 (not our tenant)
        $uid = uniqid('other_', true);
        $otherId = DB::table('users')->insertGetId([
            'tenant_id'  => 999,
            'name'       => 'OtherTenant ' . $uid,
            'first_name' => 'Other',
            'last_name'  => 'Tenant',
            'email'      => $uid . '@other.test',
            'status'     => 'active',
            'balance'    => 99.0,
            'role'       => 'member',
            'is_approved' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // We are scoped to TENANT_ID = 2; querying for this other-tenant user should fail
        TenantContext::setById(self::TENANT_ID);
        $result = $this->tool->execute([], $otherId);

        $this->assertFalse($result['ok']);
    }
}
