<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Seeds the test tenant (hour-timebank, tenant_id=2) with sample
     * users, listings, and events for local development.
     */
    public function run(): void
    {
        // Seed the hour-timebank tenant first (categories, settings, admin)
        $this->call(TenantSeeder::class);

        $tenantId = 2;

        // Create 10 users for the test tenant
        $users = User::factory()
            ->count(10)
            ->forTenant($tenantId)
            ->create();

        // Create 15 listings split between offers and requests
        Listing::factory()
            ->count(8)
            ->offer()
            ->forTenant($tenantId)
            ->recycle($users)
            ->create();

        Listing::factory()
            ->count(7)
            ->request()
            ->forTenant($tenantId)
            ->recycle($users)
            ->create();

        // Create 8 upcoming events
        Event::factory()
            ->count(8)
            ->forTenant($tenantId)
            ->recycle($users)
            ->create();
    }
}
