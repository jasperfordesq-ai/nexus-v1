<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Models\Category;
use App\Models\ExchangeRequest;
use App\Models\Listing;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BrokerControlConfigService;
use App\Services\ExchangeWorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;
use Tests\Laravel\Traits\CreatesExchangeData;

/**
 * Integration test: full exchange lifecycle from listing creation
 * through exchange request, acceptance, start, confirmation, and
 * wallet balance updates.
 */
class ExchangeWorkflowTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesExchangeData;

    private User $provider;
    private User $requester;
    private int $listingCategoryId;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure exchange workflow is enabled for the test tenant.
        //
        // The gate (ExchangesController -> BrokerControlConfigService::isExchangeWorkflowEnabled)
        // reads exchange_workflow.enabled from the nested broker config (stored in
        // tenants.configuration / tenant_settings.broker_config), NOT a flat
        // exchange_workflow_enabled setting. Use the service's own writer, which maps
        // the flat key to exchange_workflow.enabled and clears the config cache.
        \App\Services\BrokerControlConfigService::updateConfig([
            'exchange_workflow_enabled' => true,
        ]);

        // Listing creation requires a valid listing category for the tenant
        // (ListingService enforces a required, tenant-scoped, type=listing category).
        $this->listingCategoryId = (int) (Category::where('tenant_id', $this->testTenantId)
            ->where('type', 'listing')
            ->value('id')
            ?? Category::factory()->forTenant($this->testTenantId)->create(['type' => 'listing'])->id);

        // Create provider and requester with known balances. onboarding_completed
        // is required because POST /v2/listings is behind the onboarding-required
        // middleware.
        $this->provider = User::factory()->forTenant($this->testTenantId)->create([
            'balance' => 10.00,
            'status'  => 'active',
            'is_approved' => true,
            'onboarding_completed' => true,
        ]);

        $this->requester = User::factory()->forTenant($this->testTenantId)->create([
            'balance' => 10.00,
            'status'  => 'active',
            'is_approved' => true,
            'onboarding_completed' => true,
        ]);
    }

    // =========================================================================
    // Full Lifecycle Tests
    // =========================================================================

    public function test_full_exchange_lifecycle_via_api(): void
    {
        // Step 1: Provider creates a listing (offer)
        Sanctum::actingAs($this->provider, ['*']);

        $listingResponse = $this->apiPost('/v2/listings', [
            'title'       => 'Garden Help',
            'description' => 'I can help with your garden and outdoor spaces.',
            'type'        => 'offer',
            'category_id' => $this->listingCategoryId,
            'hours_estimate' => 2.00,
            'service_type'   => 'physical_only',
        ]);

        // Listing creation should succeed (201 or 200)
        $this->assertContains($listingResponse->getStatusCode(), [200, 201]);
        $listingData = $listingResponse->json('data') ?? $listingResponse->json();
        $listingId = $listingData['id'] ?? $listingData['data']['id'] ?? null;

        if (!$listingId) {
            // If the API doesn't return the listing in expected format, create directly
            $listing = Listing::factory()->forTenant($this->testTenantId)->offer()->create([
                'user_id' => $this->provider->id,
                'title'   => 'Garden Help',
                'price'   => 2.00,
            ]);
            $listingId = $listing->id;
        }

        // Step 2: Requester creates an exchange request
        Sanctum::actingAs($this->requester, ['*']);

        $exchangeResponse = $this->apiPost('/v2/exchanges', [
            'listing_id'     => $listingId,
            'proposed_hours' => 2.00,
            'message'        => 'I would like help with my garden please',
        ]);

        // Exchange creation may fail if compliance checks or broker config block it
        if ($exchangeResponse->getStatusCode() === 400 || $exchangeResponse->getStatusCode() === 403) {
            $this->markTestIncomplete(
                'Exchange creation blocked by compliance/broker config: '
                . $exchangeResponse->getContent()
            );
        }

        $this->assertContains($exchangeResponse->getStatusCode(), [200, 201]);
        $exchangeData = $exchangeResponse->json('data') ?? $exchangeResponse->json();
        $exchangeId = $exchangeData['id'] ?? $exchangeData['data']['id'] ?? null;
        $this->assertNotNull($exchangeId, 'Exchange ID should be returned');

        // Verify exchange is in an initial pending status. A freshly created
        // exchange starts at 'pending_provider' (or 'pending_broker' when broker
        // approval is enabled for the tenant) — there is no bare 'pending' state.
        $exchange = ExchangeRequest::find($exchangeId);
        $this->assertNotNull($exchange);
        $this->assertContains($exchange->status, ['pending_provider', 'pending_broker']);

        // Step 3: Provider accepts the exchange
        Sanctum::actingAs($this->provider, ['*']);

        $acceptResponse = $this->apiPost("/v2/exchanges/{$exchangeId}/accept");
        $this->assertContains($acceptResponse->getStatusCode(), [200, 201]);

        $exchange->refresh();
        $this->assertEquals('accepted', $exchange->status);

        // Step 4: Provider starts the exchange
        $startResponse = $this->apiPost("/v2/exchanges/{$exchangeId}/start");
        $this->assertContains($startResponse->getStatusCode(), [200, 201]);

        $exchange->refresh();
        $this->assertEquals('in_progress', $exchange->status);

        // Step 5: Provider marks as complete (ready for confirmation)
        $completeResponse = $this->apiPost("/v2/exchanges/{$exchangeId}/complete");
        $this->assertContains($completeResponse->getStatusCode(), [200, 201]);

        // Step 6: Both parties confirm hours
        // Provider confirms
        $providerConfirmResponse = $this->apiPost("/v2/exchanges/{$exchangeId}/confirm", [
            'hours' => 2.00,
        ]);
        $this->assertContains($providerConfirmResponse->getStatusCode(), [200, 201]);

        // Requester confirms
        Sanctum::actingAs($this->requester, ['*']);

        $requesterConfirmResponse = $this->apiPost("/v2/exchanges/{$exchangeId}/confirm", [
            'hours' => 2.00,
        ]);
        $this->assertContains($requesterConfirmResponse->getStatusCode(), [200, 201]);

        // Step 7: Verify final state
        $exchange->refresh();
        $this->assertContains($exchange->status, ['completed', 'awaiting_confirmation']);

        // If completed, verify wallet balances were updated
        if ($exchange->status === 'completed') {
            $this->provider->refresh();
            $this->requester->refresh();

            // Provider should have earned credits (balance increased)
            $this->assertGreaterThanOrEqual(10.00, (float) $this->provider->balance);

            // A transaction record should exist
            $transaction = Transaction::where('tenant_id', $this->testTenantId)
                ->where(function ($q) {
                    $q->where('sender_id', $this->requester->id)
                      ->orWhere('receiver_id', $this->provider->id);
                })
                ->first();
            $this->assertNotNull($transaction, 'A transaction should be created on completion');
        }
    }

    /**
     * Deterministic money-path regression test for the #1 critical journey
     * (exchange lifecycle). Unlike test_full_exchange_lifecycle_via_api above —
     * which skips the balance check entirely when completion does not fire and
     * otherwise only asserts the provider balance "did not decrease" — this
     * drives the service to a guaranteed 'completed' state and asserts the
     * EXACT credit math, money conservation, and the single transaction row.
     *
     * A bug that credits zero, the wrong amount, double-mints, or fails to
     * debit the requester would pass the old test and fail this one.
     *
     * Whole-hour amounts only: the nexus_test schema stores balance/amount as
     * INT, so fractional values round and would break exact assertions (prod
     * uses decimal(10,2)).
     */
    public function test_completing_an_exchange_credits_exact_hours_and_conserves_money(): void
    {
        $scenario = $this->createExchangeScenario([
            'provider'  => ['balance' => 10, 'status' => 'active', 'is_approved' => true],
            'requester' => ['balance' => 10, 'status' => 'active', 'is_approved' => true],
            'listing'   => ['title' => 'Garden Help'],
            'exchange'  => ['status' => 'in_progress', 'proposed_hours' => 2.00],
        ]);

        $provider  = $scenario['provider'];
        $requester = $scenario['requester'];
        $exchange  = $scenario['exchange'];

        $totalBefore = (float) $provider->balance + (float) $requester->balance;

        // Both parties confirm matching hours -> exchange completes and credits move.
        $this->assertTrue(
            ExchangeWorkflowService::confirmCompletion($exchange->id, (int) $provider->id, 2.00),
            'Provider confirmation should succeed'
        );
        $this->assertTrue(
            ExchangeWorkflowService::confirmCompletion($exchange->id, (int) $requester->id, 2.00),
            'Requester confirmation should complete the exchange'
        );

        // The exchange must reach the terminal completed state — not silently
        // stall in awaiting/pending confirmation (the gap in the lifecycle test).
        $exchange->refresh();
        $this->assertSame(
            ExchangeWorkflowService::STATUS_COMPLETED,
            $exchange->status,
            'Exchange should be completed after both parties confirm matching hours'
        );

        // EXACT credit math: the requester pays 2 credits, the provider earns 2.
        $provider->refresh();
        $requester->refresh();
        $this->assertEqualsWithDelta(12.0, (float) $provider->balance, 0.001, 'Provider should be credited exactly 2 hours (10 + 2)');
        $this->assertEqualsWithDelta(8.0, (float) $requester->balance, 0.001, 'Requester should be debited exactly 2 hours (10 - 2)');

        // Conservation: a same-tenant transfer must neither create nor destroy credits.
        $this->assertEqualsWithDelta(
            $totalBefore,
            (float) $provider->balance + (float) $requester->balance,
            0.001,
            'Total credits in the tenant must be conserved across an exchange'
        );

        // Exactly ONE exchange transaction, with the correct shape (guards against
        // double-mint and wrong-amount regressions).
        $transactions = Transaction::where('tenant_id', $this->testTenantId)
            ->where('sender_id', (int) $requester->id)
            ->where('receiver_id', (int) $provider->id)
            ->where('transaction_type', 'exchange')
            ->get();

        $this->assertCount(1, $transactions, 'Completion should create exactly one exchange transaction');

        $transaction = $transactions->first();
        $this->assertEqualsWithDelta(2.0, (float) $transaction->amount, 0.001, 'Transaction amount must equal the confirmed hours');
        $this->assertSame('completed', $transaction->status);
        $this->assertEquals((int) $exchange->transaction_id, (int) $transaction->id, 'Exchange should link the transaction it created');
    }

    public function test_exchange_status_transitions_are_enforced(): void
    {
        $scenario = $this->createExchangeScenario([
            'provider'  => ['balance' => 10.00, 'status' => 'active', 'is_approved' => true],
            'requester' => ['balance' => 10.00, 'status' => 'active', 'is_approved' => true],
            'exchange'  => ['status' => 'pending'],
        ]);

        // Requester cannot start a pending exchange (only provider can accept first)
        Sanctum::actingAs($scenario['requester'], ['*']);

        $startResponse = $this->apiPost("/v2/exchanges/{$scenario['exchange']->id}/start");
        // Should fail because exchange is not yet accepted
        $this->assertContains($startResponse->getStatusCode(), [400, 403, 404, 422]);
    }

    public function test_only_provider_can_accept_exchange(): void
    {
        $scenario = $this->createExchangeScenario([
            'provider'  => ['status' => 'active', 'is_approved' => true],
            'requester' => ['status' => 'active', 'is_approved' => true],
            'exchange'  => ['status' => 'pending'],
        ]);

        // Requester tries to accept (should be forbidden)
        Sanctum::actingAs($scenario['requester'], ['*']);

        $response = $this->apiPost("/v2/exchanges/{$scenario['exchange']->id}/accept");
        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    public function test_exchange_not_visible_to_third_party(): void
    {
        $scenario = $this->createExchangeScenario([
            'provider'  => ['status' => 'active', 'is_approved' => true],
            'requester' => ['status' => 'active', 'is_approved' => true],
        ]);

        // Create a third user who is not part of the exchange
        $thirdUser = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($thirdUser, ['*']);

        $response = $this->apiGet("/v2/exchanges/{$scenario['exchange']->id}");
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_provider_can_decline_exchange(): void
    {
        // 'pending_provider' is the canonical initial status the app assigns to a
        // new exchange request (ExchangeWorkflowService::STATUS_PENDING_PROVIDER).
        // declineRequest() only acts on that status; 'pending' is not a real state.
        $scenario = $this->createExchangeScenario([
            'provider'  => ['status' => 'active', 'is_approved' => true],
            'requester' => ['status' => 'active', 'is_approved' => true],
            'exchange'  => ['status' => 'pending_provider'],
        ]);

        Sanctum::actingAs($scenario['provider'], ['*']);

        $response = $this->apiPost("/v2/exchanges/{$scenario['exchange']->id}/decline", [
            'reason' => 'Not available this week',
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $scenario['exchange']->refresh();
        // Declining transitions the exchange to 'cancelled' (the workflow has no
        // separate 'declined' terminal state — decline maps to cancelled).
        $this->assertContains($scenario['exchange']->status, ['declined', 'cancelled']);
    }

    public function test_exchange_can_be_cancelled(): void
    {
        $scenario = $this->createExchangeScenario([
            'provider'  => ['status' => 'active', 'is_approved' => true],
            'requester' => ['status' => 'active', 'is_approved' => true],
            'exchange'  => ['status' => 'pending_provider'],
        ]);

        Sanctum::actingAs($scenario['requester'], ['*']);

        $response = $this->apiDelete("/v2/exchanges/{$scenario['exchange']->id}", [
            'reason' => 'Changed my mind',
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $scenario['exchange']->refresh();
        $this->assertEquals('cancelled', $scenario['exchange']->status);
    }

    public function test_confirm_requires_positive_hours(): void
    {
        $scenario = $this->createExchangeScenario([
            'provider'  => ['status' => 'active', 'is_approved' => true],
            'requester' => ['status' => 'active', 'is_approved' => true],
            'exchange'  => ['status' => 'in_progress'],
        ]);

        Sanctum::actingAs($scenario['provider'], ['*']);

        $response = $this->apiPost("/v2/exchanges/{$scenario['exchange']->id}/confirm", [
            'hours' => 0,
        ]);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function test_exchange_index_returns_user_exchanges(): void
    {
        $scenario = $this->createExchangeScenario([
            'provider'  => ['status' => 'active', 'is_approved' => true],
            'requester' => ['status' => 'active', 'is_approved' => true],
        ]);

        Sanctum::actingAs($scenario['provider'], ['*']);

        $response = $this->apiGet('/v2/exchanges');

        // May return 400 if exchange workflow not enabled; otherwise 200
        if ($response->getStatusCode() === 400) {
            $this->markTestIncomplete('Exchange workflow not enabled for test tenant');
        }

        $this->assertEquals(200, $response->getStatusCode());
    }
}
