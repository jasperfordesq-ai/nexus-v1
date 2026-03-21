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
 * Feature tests for OrgWalletController — organization wallet endpoints.
 */
class OrgWalletControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------------------
    //  GET /organizations/{id}/members
    // ------------------------------------------------------------------

    public function test_org_members_requires_auth(): void
    {
        $response = $this->apiGet('/organizations/1/members');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /organizations/{id}/wallet/balance
    // ------------------------------------------------------------------

    public function test_org_balance_requires_auth(): void
    {
        $response = $this->apiGet('/organizations/1/wallet/balance');

        $response->assertStatus(401);
    }
}
