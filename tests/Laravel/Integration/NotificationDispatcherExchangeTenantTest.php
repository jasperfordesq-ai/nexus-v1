<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Core\TenantContext;
use App\Models\Listing;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class NotificationDispatcherExchangeTenantTest extends TestCase
{
    use DatabaseTransactions;

    public function test_exchange_email_details_are_scoped_to_current_tenant(): void
    {
        $tenantId = 999;
        $requester = User::factory()->forTenant($tenantId)->create(['name' => 'Tenant B Requester']);
        $provider = User::factory()->forTenant($tenantId)->create(['name' => 'Tenant B Provider']);
        $listing = Listing::factory()->forTenant($tenantId)->create([
            'user_id' => $provider->id,
            'title' => 'Tenant B Listing',
        ]);

        $exchangeId = (int) DB::table('exchange_requests')->insertGetId([
            'tenant_id' => $tenantId,
            'listing_id' => $listing->id,
            'requester_id' => $requester->id,
            'provider_id' => $provider->id,
            'proposed_hours' => 2.0,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $method = new \ReflectionMethod(NotificationDispatcher::class, 'getExchangeDetailsForEmail');
        $method->setAccessible(true);

        TenantContext::setById(2);
        $this->assertSame([], $method->invoke(null, $exchangeId));

        TenantContext::setById($tenantId);
        $details = $method->invoke(null, $exchangeId);

        $this->assertSame('Tenant B Listing', $details['listing_title']);
        $this->assertSame($tenantId, (int) $details['tenant_id']);
    }
}
