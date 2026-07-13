<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\MarketplaceConfigurationService;
use App\Services\MarketplaceShippingOptionService;
use App\Services\TenantSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class MarketplaceShippingOptionServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Services\MarketplaceShippingOptionService::class));
    }

    public function test_has_public_methods(): void
    {
        $ref = new \ReflectionClass(\App\Services\MarketplaceShippingOptionService::class);
        foreach (['getSellerOptions', 'createOption', 'updateOption', 'deleteOption', 'setDefault'] as $m) {
            $this->assertTrue($ref->hasMethod($m), "Missing method: {$m}");
            $this->assertTrue($ref->getMethod($m)->isPublic(), "Not public: {$m}");
        }
    }

    public function test_create_option_defaults_to_the_tenant_payment_currency(): void
    {
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        app(TenantSettingsService::class)->set(
            $this->testTenantId,
            'general.default_currency',
            'jpy'
        );
        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_ALLOW_SHIPPING,
            true,
        );
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $sellerProfileId = (int) DB::table('marketplace_seller_profiles')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'seller_type' => 'private',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $option = MarketplaceShippingOptionService::createOption($sellerProfileId, [
            'courier_name' => 'Japan Post',
            'price' => 500,
        ]);

        $this->assertSame('JPY', $option->currency);
    }

    public function test_create_option_rejects_fractional_zero_decimal_currency(): void
    {
        TenantContext::setById($this->testTenantId);
        MarketplaceConfigurationService::set(
            MarketplaceConfigurationService::CONFIG_ALLOW_SHIPPING,
            true,
        );
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $sellerProfileId = (int) DB::table('marketplace_seller_profiles')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'seller_type' => 'private',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        MarketplaceShippingOptionService::createOption($sellerProfileId, [
            'courier_name' => 'Japan Post',
            'price' => 500.5,
            'currency' => 'JPY',
        ]);
    }
}
