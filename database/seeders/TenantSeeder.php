<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder
{
    /**
     * Seed the hour-timebank tenant (tenant_id=2) with categories,
     * settings, and an admin user.
     */
    public function run(): void
    {
        $tenantId = 2;

        // Ensure the tenant row exists
        DB::table('tenants')->updateOrInsert(
            ['id' => $tenantId],
            [
                'name'       => 'Hour Timebank',
                'slug'       => 'hour-timebank',
                'domain'     => 'hour-timebank.project-nexus.ie',
                'status'     => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        // Seed default categories
        $categories = [
            'Home & Garden',
            'Technology',
            'Education & Tutoring',
            'Health & Wellness',
            'Transport',
            'Creative & Arts',
            'Professional Services',
            'Community',
        ];

        foreach ($categories as $sort => $name) {
            DB::table('categories')->updateOrInsert(
                ['tenant_id' => $tenantId, 'name' => $name],
                [
                    'slug'       => str($name)->slug()->toString(),
                    'sort_order' => $sort,
                    'status'     => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        // Seed tenant settings
        $settings = [
            'site_name'          => 'Hour Timebank',
            'currency_name'      => 'Hours',
            'currency_symbol'    => 'hr',
            'default_balance'    => '5.00',
            'registration_mode'  => 'open',
            'theme'              => 'default',
        ];

        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['tenant_id' => $tenantId, 'setting_key' => $key],
                [
                    'setting_value' => $value,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
            );
        }

        // Create admin user for the test tenant
        User::factory()
            ->admin()
            ->forTenant($tenantId)
            ->create([
                'first_name'    => 'Admin',
                'last_name'     => 'User',
                'name'          => 'Admin User',
                'email'         => 'admin@hour-timebank.test',
            ]);
    }
}
