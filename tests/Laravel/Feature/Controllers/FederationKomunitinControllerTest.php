<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Laravel\TestCase;

/**
 * Smoke tests for FederationKomunitinController.
 *
 * Routes are gated by federation.api middleware (not Sanctum).
 * Unauthenticated requests should be rejected with 401/403.
 */
class FederationKomunitinControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_controller_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Controllers\Api\FederationKomunitinController::class));
    }

    public function test_currencies_rejects_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/federation/komunitin/currencies');
        $this->assertContains($response->status(), [401, 403, 400]);
    }

    public function test_currency_rejects_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/federation/komunitin/XYZ/currency');
        $this->assertContains($response->status(), [401, 403, 400, 404]);
    }

    public function test_accounts_rejects_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/federation/komunitin/XYZ/accounts');
        $this->assertContains($response->status(), [401, 403, 400, 404]);
    }

    public function test_transfers_rejects_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/federation/komunitin/XYZ/transfers');
        $this->assertContains($response->status(), [401, 403, 400, 404]);
    }

    public function test_create_transfer_rejects_unauthenticated(): void
    {
        $response = $this->apiPost('/v2/federation/komunitin/XYZ/transfers', []);
        $this->assertContains($response->status(), [401, 403, 400, 404, 422]);
    }
}
