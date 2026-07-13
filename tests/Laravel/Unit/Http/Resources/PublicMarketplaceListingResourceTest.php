<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Http\Resources;

use App\Core\TenantContext;
use App\Http\Resources\PublicMarketplaceListingResource;
use App\Models\User;
use App\Services\TenantSettingsService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class PublicMarketplaceListingResourceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_collection_contract_hydrates_missing_details_in_one_query(): void
    {
        TenantContext::setById($this->testTenantId);
        $seller = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);
        $items = [];

        foreach (range(1, 3) as $number) {
            $id = DB::table('marketplace_listings')->insertGetId([
                'tenant_id' => $this->testTenantId,
                'user_id' => $seller->id,
                'title' => "Batch contract {$number}",
                'description' => "Detail {$number}",
                'price_currency' => 'EUR',
                'price_type' => 'free',
                'quantity' => 1,
                'shipping_available' => false,
                'local_pickup' => true,
                'delivery_method' => 'pickup',
                'seller_type' => 'private',
                'status' => 'active',
                'moderation_status' => 'approved',
                'expires_at' => now()->addDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $items[] = [
                'id' => $id,
                'title' => "Batch contract {$number}",
                'price_currency' => 'EUR',
                'price_type' => 'free',
            ];
        }

        request()->headers->set('X-Public-Contract', 'true');
        $detailQueries = 0;
        DB::listen(static function (QueryExecuted $query) use (&$detailQueries): void {
            $sql = strtolower($query->sql);
            if (str_contains($sql, 'from `marketplace_listings`')
                && str_contains($sql, '`description`')
                && str_contains($sql, '`shipping_available`')) {
                $detailQueries++;
            }
        });

        $augmented = PublicMarketplaceListingResource::augmentCollection($items);

        $this->assertSame(1, $detailQueries);
        $this->assertSame(
            ['Detail 1', 'Detail 2', 'Detail 3'],
            array_column(array_column($augmented, 'public_contract'), 'description')
        );
    }

    public function test_public_contract_reduces_coordinate_precision(): void
    {
        $contract = PublicMarketplaceListingResource::fromArray([
            'id' => 101,
            'title' => 'Approximate location listing',
            'description' => 'Exact seller coordinates must not be public.',
            'latitude' => 53.349805,
            'longitude' => -6.26031,
            'price_type' => 'free',
        ]);

        $this->assertSame(53.35, $contract['location']['latitude']);
        $this->assertSame(-6.26, $contract['location']['longitude']);
        $this->assertNotSame(53.349805, $contract['location']['latitude']);
        $this->assertNotSame(-6.26031, $contract['location']['longitude']);
    }

    public function test_public_contract_uses_tenant_currency_when_legacy_data_has_none(): void
    {
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        app(TenantSettingsService::class)->set(
            $this->testTenantId,
            'general.default_currency',
            'jpy'
        );

        $contract = PublicMarketplaceListingResource::fromArray([
            'id' => 102,
            'title' => 'Tenant currency fallback',
            'description' => 'Legacy listings must not imply a different regional currency.',
            'price' => 500,
            'price_type' => 'fixed',
        ]);

        $this->assertSame('JPY', $contract['price']['currency']);
    }
}
