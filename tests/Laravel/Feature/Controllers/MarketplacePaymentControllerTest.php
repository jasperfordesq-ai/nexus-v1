<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\TenantFeatureConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for MarketplacePaymentController.
 *
 * Focus on the controller-level guards that don't require Stripe:
 *  - Auth required on every endpoint
 *  - Feature flag gating
 *  - Validation (missing order_id, missing payment_intent_id)
 *  - 404 on unknown payment status lookup
 */
class MarketplacePaymentControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function enableMarketplaceFeature(int $tenantId): void
    {
        $features = TenantFeatureConfig::FEATURE_DEFAULTS;
        $features['marketplace'] = true;

        DB::table('tenants')->where('id', $tenantId)->update([
            'features' => json_encode($features),
        ]);

        TenantContext::setById($tenantId);
    }

    public function test_create_intent_requires_auth(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $response = $this->apiPost('/v2/marketplace/payments/create-intent', [
            'order_id' => 1,
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_create_intent_returns_403_when_feature_disabled(): void
    {
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode(['marketplace' => false]),
        ]);
        TenantContext::setById($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiPost('/v2/marketplace/payments/create-intent', [
            'order_id' => 1,
        ]);

        $response->assertStatus(403);
    }

    public function test_create_intent_validates_order_id(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiPost('/v2/marketplace/payments/create-intent', []);

        $response->assertStatus(422);
    }

    public function test_confirm_validates_payment_intent_id(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiPost('/v2/marketplace/payments/confirm', []);

        $response->assertStatus(422);
    }

    public function test_status_returns_404_for_unknown_payment(): void
    {
        if (! \Schema::hasTable('marketplace_payments')) {
            $this->markTestSkipped('marketplace_payments table not present in test DB.');
        }

        $this->enableMarketplaceFeature($this->testTenantId);

        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiGet('/v2/marketplace/payments/9999999/status');

        $response->assertStatus(404);
    }

    public function test_balance_requires_auth(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $response = $this->apiGet('/v2/marketplace/seller/balance');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_payouts_requires_auth(): void
    {
        $this->enableMarketplaceFeature($this->testTenantId);

        $response = $this->apiGet('/v2/marketplace/seller/payouts');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }
}
