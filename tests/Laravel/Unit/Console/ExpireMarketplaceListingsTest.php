<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class ExpireMarketplaceListingsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_due_active_listings_are_expired_once_without_touching_future_or_draft_listings(): void
    {
        $seller = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        $expiredId = $this->createListing($seller, [
            'title' => 'Expired active listing',
            'expires_at' => now()->subMinute(),
        ]);
        $futureId = $this->createListing($seller, [
            'title' => 'Future active listing',
            'expires_at' => now()->addDay(),
        ]);
        $draftId = $this->createListing($seller, [
            'title' => 'Expired draft listing',
            'status' => 'draft',
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('marketplace:expire-listings', ['--limit' => 10])
            ->expectsOutputToContain('expired=1')
            ->assertSuccessful();

        $this->assertDatabaseHas('marketplace_listings', ['id' => $expiredId, 'status' => 'expired']);
        $this->assertDatabaseHas('marketplace_listings', ['id' => $futureId, 'status' => 'active']);
        $this->assertDatabaseHas('marketplace_listings', ['id' => $draftId, 'status' => 'draft']);

        $this->artisan('marketplace:expire-listings', ['--limit' => 10])
            ->expectsOutputToContain('expired=0')
            ->assertSuccessful();
    }

    private function createListing(User $seller, array $overrides = []): int
    {
        return (int) DB::table('marketplace_listings')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $seller->id,
            'title' => 'Marketplace expiry fixture',
            'description' => 'Marketplace listing expiry regression fixture.',
            'price_currency' => 'EUR',
            'price_type' => 'free',
            'quantity' => 1,
            'shipping_available' => 0,
            'local_pickup' => 1,
            'delivery_method' => 'pickup',
            'seller_type' => 'private',
            'status' => 'active',
            'moderation_status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
