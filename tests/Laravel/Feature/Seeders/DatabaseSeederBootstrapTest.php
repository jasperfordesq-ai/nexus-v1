<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Seeders;

use App\Core\TenantContext;
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
        $this->assertNull($master->parent_id);
        $this->assertSame('/1/', $master->path);
        $this->assertSame(0, (int) $master->depth);
        $this->assertSame(1, (int) $master->allows_subtenants);
        $this->assertSame(3, (int) $master->max_depth);
        $this->assertSame('platform', $master->tenant_category);
        $this->assertSame(1, (int) $master->is_active);

        $sentinel = DB::table('tenants')->where('id', 2)->first();
        $this->assertSame('Sentinel Tenant 2', $sentinel->name);
        $this->assertSame('sentinel-tenant-2', $sentinel->slug);

        DB::table('tenants')
            ->where('id', 2)
            ->update([
                'path' => '/1/2/',
                'parent_id' => TenantSeeder::MASTER_TENANT_ID,
                'depth' => 1,
                'allows_subtenants' => 0,
                'max_depth' => 0,
                'updated_at' => now(),
            ]);

        $tenantTwoGodEmail = 'tenant2.god.' . bin2hex(random_bytes(4)) . '@example.test';

        DB::table('users')->insert([
            'tenant_id' => 2,
            'email' => $tenantTwoGodEmail,
            'password_hash' => password_hash('Tenant2GodPass123!', PASSWORD_BCRYPT),
            'first_name' => 'Tenant',
            'last_name' => 'Two God',
            'role' => 'god',
            'is_admin' => 1,
            'is_super_admin' => 0,
            'is_tenant_super_admin' => 0,
            'is_god' => 1,
            'status' => 'active',
            'is_approved' => 1,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

        $token = $response->json('token');
        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        $authHeaders = [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => (string) TenantSeeder::MASTER_TENANT_ID,
            'Accept' => 'application/json',
        ];

        $dashboardResponse = $this->getJson('/api/v2/admin/super/dashboard', $authHeaders)
            ->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, (int) $dashboardResponse->json('data.total_tenants'));
        $this->assertGreaterThanOrEqual(1, (int) $dashboardResponse->json('data.hub_tenants'));

        $this->getJson('/api/v2/admin/super/tenants', $authHeaders)
            ->assertStatus(200)
            ->assertJsonFragment([
                'id' => TenantSeeder::MASTER_TENANT_ID,
                'name' => 'Master Tenant',
                'path' => '/1/',
                'allows_subtenants' => true,
            ]);

        $this->getJson('/api/v2/admin/super/tenants/hierarchy', $authHeaders)
            ->assertStatus(200)
            ->assertJsonFragment([
                'id' => TenantSeeder::MASTER_TENANT_ID,
                'path' => '/1/',
                'allows_subtenants' => true,
            ]);

        $childSlug = 'first-child-tenant-' . bin2hex(random_bytes(4));

        $createResponse = $this->postJson('/api/v2/admin/super/tenants', [
            'name' => 'First Child Tenant',
            'slug' => $childSlug,
            'parent_id' => TenantSeeder::MASTER_TENANT_ID,
            'allows_subtenants' => true,
            'max_depth' => 2,
        ], $authHeaders);

        $createResponse->assertStatus(201);
        $childTenantId = (int) $createResponse->json('data.tenant_id');
        $this->assertGreaterThan(0, $childTenantId);

        $child = DB::table('tenants')->where('id', $childTenantId)->first();
        $this->assertNotNull($child);
        $this->assertSame(TenantSeeder::MASTER_TENANT_ID, (int) $child->parent_id);
        $this->assertSame('/1/' . $childTenantId . '/', $child->path);
        $this->assertSame(1, (int) $child->depth);
        $this->assertSame(1, (int) $child->allows_subtenants);

        TenantContext::reset();
        unset($_SERVER['HTTP_X_TENANT_ID'], $_SERVER['HTTP_X_TENANT_SLUG'], $_SERVER['HTTP_AUTHORIZATION']);

        $tenantTwoLogin = $this->postJson('/api/auth/login', [
            'email' => $tenantTwoGodEmail,
            'password' => 'Tenant2GodPass123!',
        ], ['X-Tenant-ID' => '2', 'Accept' => 'application/json']);

        $tenantTwoLogin->assertStatus(200);
        $tenantTwoLogin->assertJsonPath('user.tenant_id', 2);
        $tenantTwoLogin->assertJsonPath('user.role', 'god');

        $tenantTwoToken = $tenantTwoLogin->json('token');
        $this->assertIsString($tenantTwoToken);
        $this->assertNotEmpty($tenantTwoToken);

        $tenantTwoAuthHeaders = [
            'Authorization' => 'Bearer ' . $tenantTwoToken,
            'X-Tenant-ID' => '2',
            'Accept' => 'application/json',
        ];

        TenantContext::reset();
        unset($_SERVER['HTTP_X_TENANT_ID'], $_SERVER['HTTP_X_TENANT_SLUG'], $_SERVER['HTTP_AUTHORIZATION']);

        $this->getJson('/api/v2/admin/super/dashboard', $tenantTwoAuthHeaders)
            ->assertStatus(200);

        TenantContext::reset();
        unset($_SERVER['HTTP_X_TENANT_ID'], $_SERVER['HTTP_X_TENANT_SLUG'], $_SERVER['HTTP_AUTHORIZATION']);

        $this->getJson('/api/v2/admin/super/tenants', $tenantTwoAuthHeaders)
            ->assertStatus(200)
            ->assertJsonFragment([
                'id' => TenantSeeder::MASTER_TENANT_ID,
                'name' => 'Master Tenant',
            ]);

        $tenantTwoChildSlug = 'tenant-two-god-child-' . bin2hex(random_bytes(4));

        TenantContext::reset();
        unset($_SERVER['HTTP_X_TENANT_ID'], $_SERVER['HTTP_X_TENANT_SLUG'], $_SERVER['HTTP_AUTHORIZATION']);

        $tenantTwoCreateResponse = $this->postJson('/api/v2/admin/super/tenants', [
            'name' => 'Tenant Two God Child',
            'slug' => $tenantTwoChildSlug,
            'allows_subtenants' => true,
            'max_depth' => 2,
        ], $tenantTwoAuthHeaders);

        $tenantTwoCreateResponse->assertStatus(201);
        $tenantTwoChildId = (int) $tenantTwoCreateResponse->json('data.tenant_id');
        $tenantTwoChild = DB::table('tenants')->where('id', $tenantTwoChildId)->first();
        $this->assertNotNull($tenantTwoChild);
        $this->assertSame(TenantSeeder::MASTER_TENANT_ID, (int) $tenantTwoChild->parent_id);
        $this->assertSame('/1/' . $tenantTwoChildId . '/', $tenantTwoChild->path);
    }
}
