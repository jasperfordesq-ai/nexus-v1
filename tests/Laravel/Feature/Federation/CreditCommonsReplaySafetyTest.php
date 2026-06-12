<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Federation;

use App\Core\TenantContext;
use App\Http\Controllers\Api\FederationCreditCommonsController;
use App\Services\Protocols\CreditCommonsAdapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression tests for Credit Commons ledger replay/concurrency safety.
 *
 * Bug history (2026-06-12 hunt): commitTransaction and the C→E erase path
 * read the entry without a lock and updated state keyed on id only — two
 * concurrent deliveries of the same UUID both passed the state gate and both
 * applied/reversed balances (double credit / double reverse). The fix claims
 * the state transition atomically (UPDATE ... WHERE state = <expected>)
 * BEFORE touching balances, so a losing racer affects 0 rows and returns the
 * standard InvalidStateTransition response. proposeTransaction also ignored
 * the caller-supplied UUID, so replays accumulated duplicate committable
 * PENDING proposals; it now answers idempotently. These tests pin the
 * observable contract: balances move exactly once and replays get the
 * idempotent/invalid-transition response, never a re-application.
 */
class CreditCommonsReplaySafetyTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId;
    private object $payer;
    private object $payee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantId = (int) DB::table('tenants')->where('is_active', 1)->orderBy('id')->value('id');
        if (!$this->tenantId) {
            $this->markTestSkipped('Test DB lacks an active tenant');
        }
        TenantContext::setById($this->tenantId);

        $users = DB::table('users')
            ->where('tenant_id', $this->tenantId)
            ->where('status', 'active')
            ->whereNotNull('username')
            ->where('username', '!=', '')
            ->orderBy('id')
            ->limit(2)
            ->get(['id', 'username']);
        if ($users->count() < 2) {
            $this->markTestSkipped('Test DB lacks two active users with usernames');
        }
        [$this->payer, $this->payee] = [$users[0], $users[1]];

        DB::table('users')->whereIn('id', [$this->payer->id, $this->payee->id])->update([
            'federation_optin' => 1,
            'balance' => 100.00,
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    private function insertEntry(string $uuid, string $state): void
    {
        DB::table('federation_cc_entries')->insert([
            'tenant_id' => $this->tenantId,
            'transaction_uuid' => $uuid,
            'payer' => $this->payer->username,
            'payee' => $this->payee->username,
            'quant' => 5.00,
            'description' => 'replay-safety test',
            'state' => $state,
            'workflow' => '+|PPC-PE+CE-',
            'author' => $this->payer->username,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function balances(): array
    {
        return [
            (float) DB::table('users')->where('id', $this->payer->id)->value('balance'),
            (float) DB::table('users')->where('id', $this->payee->id)->value('balance'),
        ];
    }

    public function test_commit_applies_balances_once_and_replay_does_not_reapply(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $this->insertEntry($uuid, CreditCommonsAdapter::STATE_VALIDATED);

        $controller = new FederationCreditCommonsController();

        $first = $controller->commitTransaction(Request::create('/', 'POST'), $uuid);
        $this->assertSame(200, $first->getStatusCode(), $first->getContent());
        $this->assertSame([95.0, 105.0], $this->balances(), 'Commit must move the amount exactly once');

        // Replay (redelivery): must NOT re-apply balances.
        $second = $controller->commitTransaction(Request::create('/', 'POST'), $uuid);
        $this->assertSame(400, $second->getStatusCode(), 'Replayed commit must be rejected');
        $this->assertSame([95.0, 105.0], $this->balances(), 'Replayed commit must not double-apply balances');
    }

    public function test_erase_reverses_balances_once_and_replay_does_not_rereverse(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $this->insertEntry($uuid, CreditCommonsAdapter::STATE_COMPLETED);

        $controller = new FederationCreditCommonsController();

        $first = $controller->transitionTransaction(Request::create('/', 'PATCH'), $uuid, 'E');
        $this->assertSame(201, $first->getStatusCode(), $first->getContent());
        $this->assertSame([105.0, 95.0], $this->balances(), 'Erase must reverse the amount exactly once');

        $second = $controller->transitionTransaction(Request::create('/', 'PATCH'), $uuid, 'E');
        $this->assertSame(400, $second->getStatusCode(), 'Replayed erase must be rejected');
        $this->assertSame([105.0, 95.0], $this->balances(), 'Replayed erase must not double-reverse balances');
    }

    public function test_propose_honours_caller_uuid_and_replays_idempotently(): void
    {
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $payload = [
            'uuid' => $uuid,
            'payer' => $this->payer->username,
            'payee' => $this->payee->username,
            'quant' => 2.5,
            'description' => 'propose replay test',
        ];

        $controller = new FederationCreditCommonsController();

        $first = $controller->proposeTransaction($this->jsonRequest($payload));
        $this->assertSame(201, $first->getStatusCode(), $first->getContent());

        $second = $controller->proposeTransaction($this->jsonRequest($payload));
        $this->assertSame(200, $second->getStatusCode(), 'Replayed propose must answer idempotently');
        $this->assertTrue(
            (bool) (json_decode((string) $second->getContent(), true)['meta']['idempotent_replay'] ?? false)
        );

        $rows = DB::table('federation_cc_entries')
            ->where('tenant_id', $this->tenantId)
            ->where('transaction_uuid', $uuid)
            ->count();
        $this->assertSame(1, $rows, 'Replayed propose must not create duplicate PENDING proposals');
    }

    private function jsonRequest(array $payload): Request
    {
        return Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
    }
}
