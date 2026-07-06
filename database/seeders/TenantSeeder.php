<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    public const MASTER_TENANT_ID = 1;
    public const DEFAULT_ADMIN_EMAIL = 'admin@project-nexus.local';
    public const DEFAULT_ADMIN_PASSWORD = 'ChangeMe123!';

    /**
     * Seed the master tenant and first-run platform administrator.
     */
    public function run(): void
    {
        $tenantId = self::MASTER_TENANT_ID;
        $now = now();

        DB::table('tenants')->updateOrInsert(
            ['id' => $tenantId],
            [
                'name'              => 'Master Tenant',
                'slug'              => null,
                'domain'            => null,
                'accessible_domain' => null,
                'tenant_category'   => 'platform',
                'tagline'           => 'Project NEXUS master tenant',
                'is_active'         => 1,
                'depth'             => 0,
                'allows_subtenants' => true,
                'max_depth'         => 3,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
        );

        $categories = [
            'Home and Garden',
            'Technology Support',
            'Education and Tutoring',
            'Health and Wellbeing',
            'Transport',
            'Creative Arts',
            'Professional Services',
            'Community',
        ];

        foreach ($categories as $sort => $name) {
            DB::table('categories')->updateOrInsert(
                ['tenant_id' => $tenantId, 'name' => $name],
                [
                    'slug'       => str($name)->slug()->toString(),
                    'sort_order' => $sort,
                    'is_active'  => 1,
                    'type'       => 'listing',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        $settings = [
            'site_name'          => 'Project NEXUS',
            'currency_name'      => 'Time Credits',
            'currency_symbol'    => 'hr',
            'default_balance'    => '5.00',
            'registration_mode'  => 'open',
            'theme'              => 'default',
        ];

        foreach ($settings as $key => $value) {
            DB::table('tenant_settings')->updateOrInsert(
                ['tenant_id' => $tenantId, 'setting_key' => $key],
                [
                    'setting_value' => $value,
                    'setting_type'  => 'string',
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ],
            );
        }

        $email = (string) env('NEXUS_BOOTSTRAP_ADMIN_EMAIL', self::DEFAULT_ADMIN_EMAIL);
        $password = (string) env('NEXUS_BOOTSTRAP_ADMIN_PASSWORD', self::DEFAULT_ADMIN_PASSWORD);

        if (app()->environment('production') && $password === self::DEFAULT_ADMIN_PASSWORD) {
            $this->command?->warn(
                'Skipping bootstrap admin: set NEXUS_BOOTSTRAP_ADMIN_EMAIL and NEXUS_BOOTSTRAP_ADMIN_PASSWORD in production.'
            );

            return;
        }

        $passwordHash = Hash::make($password);

        DB::table('users')->updateOrInsert(
            ['tenant_id' => $tenantId, 'email' => $email],
            [
                'first_name'              => 'Platform',
                'last_name'               => 'Admin',
                'name'                    => 'Platform Admin',
                'username'                => $email,
                'password_hash'           => $passwordHash,
                'password'                => $passwordHash,
                'role'                    => 'god',
                'status'                  => 'active',
                'is_admin'                => 1,
                'is_super_admin'          => 1,
                'is_tenant_super_admin'   => 1,
                'is_god'                  => 1,
                'is_approved'             => 1,
                'is_verified'             => 1,
                'is_active'               => 1,
                'email_verified_at'       => $now,
                'onboarding_completed'    => 1,
                'profile_type'            => 'individual',
                'preferred_language'      => 'en',
                'timezone'                => 'UTC',
                'totp_setup_required'     => 0,
                'max_permission_level'    => 100,
                'permissions_last_updated' => $now,
                'created_at'              => $now,
                'updated_at'              => $now,
            ],
        );
    }
}
