<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Federation;

use App\Core\TenantContext;
use App\Http\Controllers\Api\FederationV2Controller;
use App\Models\User;
use App\Services\FederationFeatureService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\Concerns\FederationIntegrationHarness;
use Tests\Laravel\TestCase;

/**
 * Money-movement regression tests for the internal (same-platform, cross-tenant)
 * federated time-credit transfer — FederationV2Controller::sendTransaction().
 *
 * This is the live successor to the removed App\Services\FederatedTransactionService,
 * whose old suite (tests/Laravel/Unit/Services/FederatedTransactionServiceTest.php)
 * was fully markTestSkipped AND referenced the deleted class — green theatre that
 * asserted nothing. It guards critical journey J6 in PRODUCTION-READINESS.md
 * ("Federation cross-tenant hour transfer — money, weakest atomicity").
 *
 * The internal transfer debits the sender and credits a member of a *partnered*
 * tenant inside a single DB transaction: an atomic conditional debit
 * (`UPDATE ... SET balance = balance - ? WHERE ... AND balance >= ?`) → credit the
 * receiver → write one federated ledger row → commit. These tests assert the money
 * movement conserves credits on the happy path and rolls back cleanly — moving
 * nothing — on every reject path (insufficient balance, out-of-range amount, self
 * transfer, unknown receiver).
 *
 * `balance`/`amount` are INT-typed on nexus_test, so every amount here is whole hours.
 *
 * Pattern mirrors FederationFeatureGateTest: call the controller method directly with
 * a bound JSON request (isolating the money path from auth/maintenance middleware) and
 * re-pin TenantContext before invocation.
 */
class FederationV2InternalTransferTest extends TestCase
{
    use DatabaseTransactions;
    use FederationIntegrationHarness;

    private const SOURCE_TENANT_ID = 2; // hour-timebank

    private int $destinationTenantId;

    protected function setUp(): void
    {
        parent::setUp();

        // Drive the 3-table federation gate to "allowed" for the SOURCE tenant — the
        // only tenant whose gate sendTransaction() consults. With the system control,
        // whitelist and tenant-federation flag seeded (and TENANT_TRANSACTIONS_ENABLED
        // defaulting to true), isOperationAllowed('transactions') returns allowed.
        $this->enableFederationForTenant(self::SOURCE_TENANT_ID);

        // A second tenant to receive the cross-tenant transfer.
        $this->destinationTenantId = (int) DB::table('tenants')->insertGetId([
            'name'       => 'Partner Coop',
            'slug'       => 'partner-coop-' . substr(uniqid(), -8),
            'is_active'  => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Active partnership that permits transactions between the two tenants.
        DB::table('federation_partnerships')->updateOrInsert(
            ['tenant_id' => self::SOURCE_TENANT_ID, 'partner_tenant_id' => $this->destinationTenantId],
            [
                'status'               => 'active',
                'federation_level'     => 4,
                'profiles_enabled'     => 1,
                'messaging_enabled'    => 1,
                'transactions_enabled' => 1,
                'requested_at'         => now(),
                'approved_at'          => now(),
                'created_at'           => now(),
                'updated_at'           => now(),
            ]
        );

        // FederationFeatureService caches the gate tables in-process; bust it so the
        // controller re-reads the rows we just seeded (DatabaseTransactions rolls them
        // back between tests).
        $this->app->make(FederationFeatureService::class)->clearCache();

        TenantContext::setById(self::SOURCE_TENANT_ID);
    }

    public function test_internal_transfer_debits_sender_credits_receiver_and_conserves_credits(): void
    {
        $sender   = $this->makeFederatedUser(self::SOURCE_TENANT_ID, 25);
        $receiver = $this->makeFederatedUser($this->destinationTenantId, 4);

        $totalBefore = $this->balanceOf($sender) + $this->balanceOf($receiver);

        $response = $this->callSendTransaction($sender, $receiver, $this->destinationTenantId, 10, 'Helped move a sofa');

        $this->assertSame(201, $response->getStatusCode(), (string) $response->getContent());

        // Money moved: sender −10, receiver +10.
        $this->assertEqualsWithDelta(15, $this->balanceOf($sender), 0.001);
        $this->assertEqualsWithDelta(14, $this->balanceOf($receiver), 0.001);

        // Conservation: nothing minted or burned across the two wallets.
        $this->assertEqualsWithDelta(
            $totalBefore,
            $this->balanceOf($sender) + $this->balanceOf($receiver),
            0.001,
            'Federated transfer must conserve total credits across both tenants.'
        );

        // Exactly one federated ledger row recording the committed cross-tenant movement.
        $this->assertDatabaseHas('transactions', [
            'tenant_id'          => self::SOURCE_TENANT_ID,
            'sender_id'          => $sender,
            'receiver_id'        => $receiver,
            'amount'             => 10,
            'status'             => 'completed',
            'is_federated'       => 1,
            'sender_tenant_id'   => self::SOURCE_TENANT_ID,
            'receiver_tenant_id' => $this->destinationTenantId,
        ]);
        $this->assertSame(1, (int) DB::table('transactions')
            ->where('sender_id', $sender)
            ->where('is_federated', 1)
            ->count());
    }

    public function test_insufficient_balance_rolls_back_and_moves_nothing(): void
    {
        $sender   = $this->makeFederatedUser(self::SOURCE_TENANT_ID, 5);
        $receiver = $this->makeFederatedUser($this->destinationTenantId, 4);

        $response = $this->callSendTransaction($sender, $receiver, $this->destinationTenantId, 10, 'More than I have');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('INSUFFICIENT_BALANCE', $this->errorCode($response));

        // The conditional debit deducted nothing, and the whole transaction rolled back:
        // neither wallet changed and no ledger row was written.
        $this->assertEqualsWithDelta(5, $this->balanceOf($sender), 0.001);
        $this->assertEqualsWithDelta(4, $this->balanceOf($receiver), 0.001);
        $this->assertSame(0, (int) DB::table('transactions')
            ->where('sender_id', $sender)
            ->where('is_federated', 1)
            ->count());
    }

    public function test_rejects_amount_over_maximum_without_moving_funds(): void
    {
        $sender   = $this->makeFederatedUser(self::SOURCE_TENANT_ID, 50);
        $receiver = $this->makeFederatedUser($this->destinationTenantId, 0);

        $response = $this->callSendTransaction($sender, $receiver, $this->destinationTenantId, 101, 'Over the cap');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('INVALID_AMOUNT', $this->errorCode($response));
        $this->assertEqualsWithDelta(50, $this->balanceOf($sender), 0.001);
        $this->assertEqualsWithDelta(0, $this->balanceOf($receiver), 0.001);
    }

    public function test_rejects_zero_amount_without_moving_funds(): void
    {
        $sender   = $this->makeFederatedUser(self::SOURCE_TENANT_ID, 10);
        $receiver = $this->makeFederatedUser($this->destinationTenantId, 0);

        $response = $this->callSendTransaction($sender, $receiver, $this->destinationTenantId, 0, 'Nothing at all');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('INVALID_AMOUNT', $this->errorCode($response));
        $this->assertEqualsWithDelta(10, $this->balanceOf($sender), 0.001);
    }

    public function test_rejects_self_transaction_without_moving_funds(): void
    {
        $sender = $this->makeFederatedUser(self::SOURCE_TENANT_ID, 10);

        // Same user + same tenant as the receiver.
        $response = $this->callSendTransaction($sender, $sender, self::SOURCE_TENANT_ID, 5, 'To myself');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('SELF_TRANSACTION', $this->errorCode($response));
        $this->assertEqualsWithDelta(10, $this->balanceOf($sender), 0.001);
    }

    public function test_rejects_unknown_receiver_without_moving_funds(): void
    {
        $sender = $this->makeFederatedUser(self::SOURCE_TENANT_ID, 10);

        $response = $this->callSendTransaction($sender, 999999, $this->destinationTenantId, 5, 'Ghost recipient');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('RECIPIENT_NOT_FOUND', $this->errorCode($response));
        $this->assertEqualsWithDelta(10, $this->balanceOf($sender), 0.001);
        $this->assertSame(0, (int) DB::table('transactions')
            ->where('sender_id', $sender)
            ->where('is_federated', 1)
            ->count());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Create an active, federation-opted-in user (federation_optin +
     * transactions_enabled_federated) with the given whole-hour balance.
     */
    private function makeFederatedUser(int $tenantId, float $balance): int
    {
        $userId = (int) DB::table('users')->insertGetId([
            'tenant_id'          => $tenantId,
            'first_name'         => 'Fed',
            'last_name'          => 'Member',
            'email'              => 'fed.' . uniqid('', true) . '@example.com',
            'username'           => 'u_' . substr(md5(uniqid('', true)), 0, 12),
            'password'           => password_hash('password', PASSWORD_BCRYPT),
            'balance'            => $balance,
            'status'             => 'active',
            'preferred_language' => 'en',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $this->optInUserToFederation($userId);

        return $userId;
    }

    /**
     * Invoke FederationV2Controller::sendTransaction() directly with a bound JSON
     * request and the sender authenticated. Re-pins TenantContext to the source tenant
     * immediately before invocation.
     *
     * @param int|string $receiverId
     * @param int|string $receiverTenantId
     * @param int|string $amount
     */
    private function callSendTransaction(
        int $senderId,
        $receiverId,
        $receiverTenantId,
        $amount,
        string $description
    ): JsonResponse {
        TenantContext::setById(self::SOURCE_TENANT_ID);
        $sender = User::query()->find($senderId);
        $this->assertNotNull($sender, 'Sender user must exist for the test.');
        $this->actingAs($sender);

        $this->app->instance('request', Request::create(
            '/api/v2/federation/transactions',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'receiver_id'        => $receiverId,
                'receiver_tenant_id' => $receiverTenantId,
                'amount'             => $amount,
                'description'        => $description,
            ])
        ));

        // The auth resolve above can shift the active tenant; re-pin so the controller
        // gate and queries evaluate the source tenant we configured.
        TenantContext::setById(self::SOURCE_TENANT_ID);

        return $this->app->make(FederationV2Controller::class)->sendTransaction();
    }

    private function balanceOf(int $userId): float
    {
        return (float) DB::table('users')->where('id', $userId)->value('balance');
    }

    /**
     * Extract errors[0].code from a v2 error envelope ({ "errors": [ { "code": ... } ] }).
     */
    private function errorCode(JsonResponse $response): ?string
    {
        $body = json_decode((string) $response->getContent(), true);

        return $body['errors'][0]['code'] ?? null;
    }
}
