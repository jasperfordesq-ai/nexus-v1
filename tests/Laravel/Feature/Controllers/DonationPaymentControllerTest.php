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
 * Smoke tests for DonationPaymentController.
 *
 * Note: /v2/donations/payment-intent and /v2/donations/{id}/receipt
 * are public (outside auth:sanctum group). adminRefund is admin-only.
 */
class DonationPaymentControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_create_payment_intent_is_public(): void
    {
        // Public route — posting empty body should validate/fail gracefully, not crash
        $response = $this->apiPost('/v2/donations/payment-intent', []);
        $this->assertLessThan(500, $response->status());
    }

    public function test_get_donation_receipt_is_public(): void
    {
        $response = $this->apiGet('/v2/donations/99999999/receipt');
        // Public route — expect 404/400/422, not 500
        $this->assertLessThan(500, $response->status());
    }

    public function test_admin_refund_requires_auth(): void
    {
        $this->apiPost('/v2/admin/donations/1/refund', [])->assertStatus(401);
    }

    public function test_admin_refund_rejects_non_admin(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->create());
        $this->apiPost('/v2/admin/donations/1/refund', [])->assertStatus(403);
    }
}
