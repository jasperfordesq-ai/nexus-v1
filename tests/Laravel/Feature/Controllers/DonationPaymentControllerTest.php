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
 * Tests for DonationPaymentController.
 *
 * Note: /v2/donations/payment-intent and /v2/donations/{id}/receipt
 * are not inside auth:sanctum middleware group, but the controller
 * calls requireAuth() internally — so they return 401 without a token.
 * adminRefund requires admin role.
 */
class DonationPaymentControllerTest extends TestCase
{
    use DatabaseTransactions;

    // -------- Smoke (kept) --------

    public function test_create_payment_intent_is_public(): void
    {
        $response = $this->apiPost('/v2/donations/payment-intent', []);
        $this->assertLessThan(500, $response->status());
    }

    public function test_get_donation_receipt_is_public(): void
    {
        $response = $this->apiGet('/v2/donations/99999999/receipt');
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

    // -------- Deep tests --------

    public function test_create_payment_intent_rejects_missing_amount(): void
    {
        Sanctum::actingAs(User::factory()->forTenant(2)->create(['status' => 'active', 'is_approved' => true]));
        $response = $this->apiPost('/v2/donations/payment-intent', ['currency' => 'EUR']);
        $this->assertEquals(422, $response->status());
    }

    public function test_create_payment_intent_rejects_negative_amount(): void
    {
        Sanctum::actingAs(User::factory()->forTenant(2)->create(['status' => 'active', 'is_approved' => true]));
        $response = $this->apiPost('/v2/donations/payment-intent', [
            'amount' => -10,
            'currency' => 'EUR',
        ]);
        $this->assertEquals(422, $response->status());
    }

    public function test_create_payment_intent_rejects_below_minimum_amount(): void
    {
        // min:0.50 per validator rules
        Sanctum::actingAs(User::factory()->forTenant(2)->create(['status' => 'active', 'is_approved' => true]));
        $response = $this->apiPost('/v2/donations/payment-intent', [
            'amount' => 0.1,
            'currency' => 'EUR',
        ]);
        $this->assertEquals(422, $response->status());
    }

    public function test_create_payment_intent_rejects_missing_currency(): void
    {
        Sanctum::actingAs(User::factory()->forTenant(2)->create(['status' => 'active', 'is_approved' => true]));
        $response = $this->apiPost('/v2/donations/payment-intent', ['amount' => 5]);
        $this->assertEquals(422, $response->status());
    }

    public function test_create_payment_intent_rejects_bad_currency_length(): void
    {
        Sanctum::actingAs(User::factory()->forTenant(2)->create(['status' => 'active', 'is_approved' => true]));
        $response = $this->apiPost('/v2/donations/payment-intent', [
            'amount' => 5,
            'currency' => 'EU',
        ]);
        $this->assertEquals(422, $response->status());
    }

    public function test_get_receipt_returns_404_for_missing_donation(): void
    {
        Sanctum::actingAs(User::factory()->forTenant(2)->create(['status' => 'active', 'is_approved' => true]));
        $response = $this->apiGet('/v2/donations/99999999/receipt');
        $this->assertEquals(404, $response->status());
    }

    public function test_admin_refund_allowed_for_admin_role(): void
    {
        $admin = User::factory()->forTenant(2)->create([
            'role' => 'admin',
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($admin, ['*']);

        // Non-existent donation → service will throw RuntimeException → 500 REFUND_ERROR
        // The point is we pass the requireAdmin() gate (not 403/401)
        $response = $this->apiPost('/v2/admin/donations/99999999/refund', []);
        $this->assertNotContains($response->status(), [401, 403]);
    }
}
