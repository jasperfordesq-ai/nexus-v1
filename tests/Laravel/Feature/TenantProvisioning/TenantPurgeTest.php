<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\TenantProvisioning;

use App\Models\User;
use App\Services\TenantProvisioning\TenantPurgeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Guards the god-only permanent tenant purge. Because a purge is irreversible and
 * touches ~600 tenant-scoped tables + external systems, these tests pin the
 * critical invariants: it only runs on a deactivated, childless, non-Master
 * tenant; it deletes ordinary members but preserves platform super-admins; and a
 * dry run changes nothing.
 */
class TenantPurgeTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Insert a throwaway tenant and return its id. Parents under the always-present
     * test tenant (id 2) by default — the FK `fk_tenant_parent` requires a real
     * parent, and the test DB has no Master tenant (id 1).
     */
    private function makeTenant(bool $active, ?int $parentId = null): int
    {
        $slug = 'purge-' . substr(md5(uniqid('', true)), 0, 10);

        return (int) DB::table('tenants')->insertGetId([
            'name'       => 'Purge Test ' . $slug,
            'slug'       => $slug,
            'parent_id'  => $parentId ?? $this->testTenantId,
            'is_active'  => $active ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_purge_removes_tenant_its_data_and_members(): void
    {
        $tenantId = $this->makeTenant(active: false);

        // A tenant-scoped row (same shape TenantDefaultsSeeder uses) and a member.
        DB::table('tenant_settings')->insert([
            'tenant_id'     => $tenantId,
            'setting_key'   => 'general.registration_mode',
            'setting_value' => 'open',
            'setting_type'  => 'string',
        ]);
        $member = User::factory()->create(['tenant_id' => $tenantId]);

        $report = TenantPurgeService::purge($tenantId);

        $this->assertTrue($report['success'], $report['error'] ?? 'purge should succeed');
        $this->assertNull(DB::table('tenants')->where('id', $tenantId)->first(), 'tenant row should be gone');
        $this->assertSame(0, DB::table('tenant_settings')->where('tenant_id', $tenantId)->count(), 'tenant_settings should be purged');
        $this->assertNull(DB::table('users')->where('id', $member->id)->first(), 'ordinary member should be deleted');
    }

    public function test_purge_preserves_platform_super_admin(): void
    {
        $tenantId = $this->makeTenant(active: false, parentId: $this->testTenantId);

        $member     = User::factory()->create(['tenant_id' => $tenantId]);
        $superAdmin = User::factory()->create(['tenant_id' => $tenantId, 'is_super_admin' => true]);

        $report = TenantPurgeService::purge($tenantId);

        $this->assertTrue($report['success']);
        $this->assertNull(DB::table('users')->where('id', $member->id)->first(), 'member deleted');

        $survivor = DB::table('users')->where('id', $superAdmin->id)->first();
        $this->assertNotNull($survivor, 'platform super-admin must not be deleted');
        $this->assertSame($this->testTenantId, (int) $survivor->tenant_id, 'super-admin reassigned to parent tenant');
    }

    public function test_purge_refuses_master_tenant(): void
    {
        $report = TenantPurgeService::purge(1);
        $this->assertFalse($report['success']);
        $this->assertStringContainsStringIgnoringCase('master', $report['error']);
    }

    public function test_purge_refuses_active_tenant(): void
    {
        $tenantId = $this->makeTenant(active: true);
        $report = TenantPurgeService::purge($tenantId);
        $this->assertFalse($report['success']);
        $this->assertStringContainsStringIgnoringCase('deactivate', $report['error']);
    }

    public function test_purge_refuses_when_children_exist(): void
    {
        $parentId = $this->makeTenant(active: false);
        $this->makeTenant(active: false, parentId: $parentId);

        $report = TenantPurgeService::purge($parentId);
        $this->assertFalse($report['success']);
        $this->assertStringContainsStringIgnoringCase('sub-tenant', $report['error']);
    }

    public function test_dry_run_counts_without_deleting(): void
    {
        $tenantId = $this->makeTenant(active: false);
        $member = User::factory()->create(['tenant_id' => $tenantId]);

        $report = TenantPurgeService::purge($tenantId, ['dry_run' => true]);

        $this->assertTrue($report['success']);
        $this->assertTrue($report['dry_run']);
        $this->assertGreaterThanOrEqual(1, $report['members_to_delete']);
        // Nothing was actually removed.
        $this->assertNotNull(DB::table('tenants')->where('id', $tenantId)->first(), 'tenant still exists after dry run');
        $this->assertNotNull(DB::table('users')->where('id', $member->id)->first(), 'member still exists after dry run');
    }

    public function test_dry_run_allowed_on_active_tenant(): void
    {
        // The preview must work BEFORE deactivation so an operator can see the
        // blast radius; only the real purge requires deactivation.
        $tenantId = $this->makeTenant(active: true);
        $report = TenantPurgeService::purge($tenantId, ['dry_run' => true]);
        $this->assertTrue($report['success']);
    }
}
