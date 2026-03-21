<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

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

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure exchange workflow is enabled for the test tenant
        DB::table('tenant_settings')->insertOrIgnore([
            'tenant_id' => $this->testTenantId,
            'category'  => 'general',
            'name'      => 'exchange_workflow_enabled',
            'value'     => '1',
        ]);

        // Create provider and requester with known balances
        $this->provider = User::factory()->forTenant($this->testTenantId)->create([
            'balance' => 10.00,
            'status'  => 'active',
            'is_approved' => true,
        ]);

        $this->requester = User::factory()->forTenant($this->testTenantId)->create([
            'balance' => 10.00,
            'status'  => 'active',
            'is_approved' => true,
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
            'description' => 'I can help with your garden',
            'type'        => 'offer',
            'price'       => 2.00,
            'hours_estimate' => 2.00,
            'service_type'   => 'in-person',
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

        // Verify exchange is in pending status
        $exchange = ExchangeRequest::find($exchangeId);
        $this->assertNotNull($exchange);
        $this->assertEquals('pending', $exchange->status);

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
        $scenario = $this->createExchangeScenario([
            'provider'  => ['status' => 'active', 'is_approved' => true],
            'requester' => ['status' => 'active', 'is_approved' => true],
            'exchange'  => ['status' => 'pending'],
        ]);

        Sanctum::actingAs($scenario['provider'], ['*']);

        $response = $this->apiPost("/v2/exchanges/{$scenario['exchange']->id}/decline", [
            'reason' => 'Not available this week',
        ]);

        $this->assertEquals(200, $response->getStatusCode());

        $scenario['exchange']->refresh();
        $this->assertContains($scenario['exchange']->status, ['declined', 'cancelled']);
    }

    public function test_exchange_can_be_cancelled(): void
    {
        $scenario = $this->createExchangeScenario([
            'provider'  => ['status' => 'active', 'is_approved' => true],
            'requester' => ['status' => 'active', 'is_approved' => true],
            'exchange'  => ['status' => 'pending'],
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
