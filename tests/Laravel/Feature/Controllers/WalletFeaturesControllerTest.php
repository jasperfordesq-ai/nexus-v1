<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * Feature tests for WalletFeaturesController — statement, categories, community fund, donations.
 */
class WalletFeaturesControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'balance' => 10.00,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------------------
    //  GET /v2/wallet/statement
    // ------------------------------------------------------------------

    public function test_statement_requires_auth(): void
    {
        $response = $this->apiGet('/v2/wallet/statement');

        $response->assertStatus(401);
    }

    public function test_statement_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/wallet/statement');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/wallet/categories
    // ------------------------------------------------------------------

    public function test_categories_requires_auth(): void
    {
        $response = $this->apiGet('/v2/wallet/categories');

        $response->assertStatus(401);
    }

    public function test_categories_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/wallet/categories');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/wallet/categories
    // ------------------------------------------------------------------

    public function test_create_category_requires_auth(): void
    {
        $response = $this->apiPost('/v2/wallet/categories', ['name' => 'Groceries']);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/wallet/community-fund
    // ------------------------------------------------------------------

    public function test_community_fund_requires_auth(): void
    {
        $response = $this->apiGet('/v2/wallet/community-fund');

        $response->assertStatus(401);
    }

    public function test_community_fund_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/wallet/community-fund');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/wallet/community-fund/transactions
    // ------------------------------------------------------------------

    public function test_community_fund_transactions_requires_auth(): void
    {
        $response = $this->apiGet('/v2/wallet/community-fund/transactions');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/wallet/donate
    // ------------------------------------------------------------------

    public function test_donate_requires_auth(): void
    {
        $response = $this->apiPost('/v2/wallet/donate', [
            'amount' => 1.0,
            'recipient_id' => 1,
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/wallet/donations
    // ------------------------------------------------------------------

    public function test_donation_history_requires_auth(): void
    {
        $response = $this->apiGet('/v2/wallet/donations');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/wallet/starting-balance
    // ------------------------------------------------------------------

    public function test_starting_balance_requires_auth(): void
    {
        $response = $this->apiGet('/v2/wallet/starting-balance');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/users/{id}/rating
    // ------------------------------------------------------------------

    public function test_user_rating_requires_auth(): void
    {
        $response = $this->apiGet('/v2/users/1/rating');

        $response->assertStatus(401);
    }

    public function test_user_rating_returns_data(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->apiGet("/v2/users/{$user->id}/rating");

        $response->assertStatus(200);
    }
}
