<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Seeders;

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class DatabaseSeederBootstrapTest extends TestCase
{
    use DatabaseTransactions;

    public function test_database_seeder_bootstraps_master_tenant_and_loginable_god_admin(): void
    {
        DB::table('tenants')->updateOrInsert(
            ['id' => 2],
            [
                'name' => 'Sentinel Tenant 2',
                'slug' => 'sentinel-tenant-2',
                'domain' => null,
                'is_active' => 1,
                'depth' => 0,
                'allows_subtenants' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $this->seed(DatabaseSeeder::class);

        $master = DB::table('tenants')->where('id', TenantSeeder::MASTER_TENANT_ID)->first();
        $this->assertNotNull($master);
        $this->assertSame('Master Tenant', $master->name);
        $this->assertNull($master->slug);
        $this->assertSame(1, (int) $master->is_active);

        $sentinel = DB::table('tenants')->where('id', 2)->first();
        $this->assertSame('Sentinel Tenant 2', $sentinel->name);
        $this->assertSame('sentinel-tenant-2', $sentinel->slug);

        $admin = DB::table('users')
            ->where('tenant_id', TenantSeeder::MASTER_TENANT_ID)
            ->where('email', TenantSeeder::DEFAULT_ADMIN_EMAIL)
            ->first();

        $this->assertNotNull($admin);
        $this->assertSame('god', $admin->role);
        $this->assertSame(1, (int) $admin->is_god);
        $this->assertSame(1, (int) $admin->is_super_admin);
        $this->assertSame(1, (int) $admin->is_approved);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(password_verify(TenantSeeder::DEFAULT_ADMIN_PASSWORD, $admin->password_hash));

        $response = $this->postJson('/api/auth/login', [
            'email' => TenantSeeder::DEFAULT_ADMIN_EMAIL,
            'password' => TenantSeeder::DEFAULT_ADMIN_PASSWORD,
        ], [
            'X-Tenant-ID' => (string) TenantSeeder::MASTER_TENANT_ID,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('user.email', TenantSeeder::DEFAULT_ADMIN_EMAIL);
        $response->assertJsonPath('user.role', 'god');
    }
}
