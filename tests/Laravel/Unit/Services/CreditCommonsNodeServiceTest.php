<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CreditCommonsNodeService;
use App\Services\Protocols\CreditCommonsAdapter;
use Illuminate\Support\Facades\DB;

class CreditCommonsNodeServiceTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Hashchain: computeHash
    // ─────────────────────────────────────────────────────────────────────────

    public function test_computeHash_returns_deterministic_sha256(): void
    {
        $hash1 = CreditCommonsNodeService::computeHash('prev-hash', 'uuid-1', 2.5, 'alice', 'bob');
        $hash2 = CreditCommonsNodeService::computeHash('prev-hash', 'uuid-1', 2.5, 'alice', 'bob');

        $this->assertEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1)); // SHA-256 produces 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash1);
    }

    public function test_computeHash_different_inputs_different_hashes(): void
    {
        $hash1 = CreditCommonsNodeService::computeHash('prev-hash', 'uuid-1', 2.5, 'alice', 'bob');
        $hash2 = CreditCommonsNodeService::computeHash('prev-hash', 'uuid-2', 2.5, 'alice', 'bob');
        $hash3 = CreditCommonsNodeService::computeHash('prev-hash', 'uuid-1', 3.0, 'alice', 'bob');
        $hash4 = CreditCommonsNodeService::computeHash('prev-hash', 'uuid-1', 2.5, 'carol', 'bob');
        $hash5 = CreditCommonsNodeService::computeHash('different-prev', 'uuid-1', 2.5, 'alice', 'bob');

        // All should be different
        $hashes = [$hash1, $hash2, $hash3, $hash4, $hash5];
        $this->assertCount(5, array_unique($hashes), 'All hashes should be unique for different inputs');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hashchain: verifyHash
    // ─────────────────────────────────────────────────────────────────────────

    public function test_verifyHash_returns_true_when_no_remote_hash(): void
    {
        // No remote hash sent = first interaction, always passes
        $result = CreditCommonsNodeService::verifyHash(null);
        $this->assertTrue($result);
    }

    public function test_verifyHash_returns_true_when_no_local_hash(): void
    {
        // When no local hash exists, verifyHash accepts the remote hash
        // and records it. We use the fluent mock pattern.
        $mock = \Mockery::mock('overload:query_builder');
        DB::shouldReceive('table->where->first')->andReturn((object) [
            'tenant_id' => 2,
            'node_slug' => 'test-node',
            'display_name' => 'Test Node',
            'currency_format' => '<quantity> hours',
            'exchange_rate' => 1.0,
            'validated_window' => 300,
            'parent_node_url' => null,
            'parent_node_slug' => null,
            'last_hash' => null,
        ]);

        // recordHash is called
        DB::shouldReceive('table->where->update')->once()->andReturn(1);

        $result = CreditCommonsNodeService::verifyHash('remote-hash-abc', 2);
        $this->assertTrue($result);
    }

    public function test_verifyHash_returns_true_on_match(): void
    {
        $matchingHash = str_repeat('a', 64);

        DB::shouldReceive('table->where->first')->andReturn((object) [
            'tenant_id' => 2,
            'node_slug' => 'test-node',
            'display_name' => 'Test Node',
            'currency_format' => '<quantity> hours',
            'exchange_rate' => 1.0,
            'validated_window' => 300,
            'parent_node_url' => null,
            'parent_node_slug' => null,
            'last_hash' => $matchingHash,
        ]);

        $result = CreditCommonsNodeService::verifyHash($matchingHash, 2);
        $this->assertTrue($result);
    }

    public function test_verifyHash_returns_false_on_mismatch(): void
    {
        $localHash = str_repeat('a', 64);
        $remoteHash = str_repeat('b', 64);

        DB::shouldReceive('table->where->first')->andReturn((object) [
            'tenant_id' => 2,
            'node_slug' => 'test-node',
            'display_name' => 'Test Node',
            'currency_format' => '<quantity> hours',
            'exchange_rate' => 1.0,
            'validated_window' => 300,
            'parent_node_url' => null,
            'parent_node_slug' => null,
            'last_hash' => $localHash,
        ]);

        $result = CreditCommonsNodeService::verifyHash($remoteHash, 2);
        $this->assertFalse($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // isLocalAccount
    // ─────────────────────────────────────────────────────────────────────────

    public function test_isLocalAccount_returns_true_for_local_path(): void
    {
        DB::shouldReceive('table->where->first')->andReturn((object) [
            'tenant_id' => 2,
            'node_slug' => 'my-node',
            'display_name' => 'My Node',
            'currency_format' => '<quantity> hours',
            'exchange_rate' => 1.0,
            'validated_window' => 300,
            'parent_node_url' => null,
            'parent_node_slug' => null,
            'last_hash' => null,
        ]);

        $result = CreditCommonsNodeService::isLocalAccount('my-node/alice', 2);
        $this->assertTrue($result);
    }

    public function test_isLocalAccount_returns_true_for_simple_name(): void
    {
        DB::shouldReceive('table->where->first')->andReturn((object) [
            'tenant_id' => 2,
            'node_slug' => 'my-node',
            'display_name' => 'My Node',
            'currency_format' => '<quantity> hours',
            'exchange_rate' => 1.0,
            'validated_window' => 300,
            'parent_node_url' => null,
            'parent_node_slug' => null,
            'last_hash' => null,
        ]);

        // No slash = no node prefix = local
        $result = CreditCommonsNodeService::isLocalAccount('alice', 2);
        $this->assertTrue($result);
    }

    public function test_isLocalAccount_returns_false_for_foreign_node(): void
    {
        DB::shouldReceive('table->where->first')->andReturn((object) [
            'tenant_id' => 2,
            'node_slug' => 'my-node',
            'display_name' => 'My Node',
            'currency_format' => '<quantity> hours',
            'exchange_rate' => 1.0,
            'validated_window' => 300,
            'parent_node_url' => null,
            'parent_node_slug' => null,
            'last_hash' => null,
        ]);

        $result = CreditCommonsNodeService::isLocalAccount('other-node/bob', 2);
        $this->assertFalse($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Exchange rate round-trip (uses KomunitinAdapter helpers)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_floatToRate_and_rateToFloat_round_trip_consistency(): void
    {
        $values = [0.25, 0.5, 0.75, 1.0, 1.5, 2.0, 3.0, 4.0];

        foreach ($values as $original) {
            $rate = \App\Services\Protocols\KomunitinAdapter::floatToRate($original);
            $roundTrip = \App\Services\Protocols\KomunitinAdapter::rateToFloat($rate);
            $this->assertEqualsWithDelta(
                $original,
                $roundTrip,
                0.001,
                "Round-trip failed for {$original}: n={$rate['n']}, d={$rate['d']}"
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validation Timeouts: expireValidatedTransactions
    // ─────────────────────────────────────────────────────────────────────────

    public function test_expireValidatedTransactions_expires_stale_entries(): void
    {
        $staleEntry = (object) [
            'id' => 101,
            'tenant_id' => 2,
            'transaction_uuid' => 'stale-uuid-1',
            'state' => 'V',
        ];

        // get all tenant configs
        DB::shouldReceive('table->get')->once()->andReturn(collect([
            (object) ['tenant_id' => 2, 'validated_window' => 300],
        ]));

        // find stale validated entries
        DB::shouldReceive('table->where->where->where->get')->once()
            ->andReturn(collect([$staleEntry]));

        // update stale entry to Erased
        DB::shouldReceive('table->where->update')->once()->andReturn(1);

        \Illuminate\Support\Facades\Log::shouldReceive('info')->once();

        $expired = CreditCommonsNodeService::expireValidatedTransactions();
        $this->assertEquals(1, $expired);
    }

    public function test_expireValidatedTransactions_returns_zero_when_no_stale(): void
    {
        DB::shouldReceive('table->get')->once()->andReturn(collect([
            (object) ['tenant_id' => 2, 'validated_window' => 300],
        ]));

        DB::shouldReceive('table->where->where->where->get')->once()
            ->andReturn(collect([]));

        $expired = CreditCommonsNodeService::expireValidatedTransactions();
        $this->assertEquals(0, $expired);
    }
}
