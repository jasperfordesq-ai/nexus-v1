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

        $users = collect(range(1, 10))->map(function (int $index) use ($tenantId) {
            $email = sprintf('demo.user.%02d@project-nexus.local', $index);
            $existing = User::query()
                ->where('tenant_id', $tenantId)
                ->where('email', $email)
                ->first();

            if ($existing) {
                return $existing;
            }

            return User::factory()
                ->forTenant($tenantId)
                ->create([
                    'email' => $email,
                    'name' => sprintf('Demo User %02d', $index),
                    'first_name' => 'Demo',
                    'last_name' => sprintf('User %02d', $index),
                ]);
        });

        foreach (range(1, 8) as $index) {
            $title = sprintf('Demo offer %02d', $index);
            if (! Listing::query()->where('tenant_id', $tenantId)->where('title', $title)->exists()) {
                Listing::factory()
                    ->offer()
                    ->forTenant($tenantId)
                    ->create([
                        'title' => $title,
                        'user_id' => $users[($index - 1) % $users->count()]->id,
                    ]);
            }
        }

        foreach (range(1, 7) as $index) {
            $title = sprintf('Demo request %02d', $index);
            if (! Listing::query()->where('tenant_id', $tenantId)->where('title', $title)->exists()) {
                Listing::factory()
                    ->request()
                    ->forTenant($tenantId)
                    ->create([
                        'title' => $title,
                        'user_id' => $users[($index - 1) % $users->count()]->id,
                    ]);
            }
        }

        foreach (range(1, 8) as $index) {
            $title = sprintf('Demo event %02d', $index);
            if (! Event::query()->where('tenant_id', $tenantId)->where('title', $title)->exists()) {
                Event::factory()
                    ->forTenant($tenantId)
                    ->create([
                        'title' => $title,
                        'user_id' => $users[($index - 1) % $users->count()]->id,
                    ]);
            }
        }
    }
}
