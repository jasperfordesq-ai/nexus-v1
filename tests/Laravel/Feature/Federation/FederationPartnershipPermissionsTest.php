<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Federation;

use App\Core\TenantContext;
use App\Services\FederationPartnershipService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Guards on FederationPartnershipService::updatePermissions() (audit C2):
 * permissions are only editable on an ACTIVE partnership, permission keys are
 * whitelisted, and no flag can be switched on beyond what the partnership's
 * federation_level grants by default.
 */
class FederationPartnershipPermissionsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function seedPartnerTenant(): int
    {
        return (int) DB::table('tenants')->insertGetId([
            'name' => 'Permissions Partner',
            'slug' => 'perm-partner-' . substr(uniqid(), -8),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedPartnership(int $partnerTenantId, string $status, int $level): int
    {
        $data = [
            'tenant_id' => $this->testTenantId,
            'partner_tenant_id' => $partnerTenantId,
            'status' => $status,
            'federation_level' => $level,
            'profiles_enabled' => 1,
            'messaging_enabled' => $level >= 2 ? 1 : 0,
            'transactions_enabled' => $level >= 3 ? 1 : 0,
            'listings_enabled' => $level >= 2 ? 1 : 0,
            'events_enabled' => $level >= 2 ? 1 : 0,
            'groups_enabled' => $level >= 4 ? 1 : 0,
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (DB::getSchemaBuilder()->hasColumn('federation_partnerships', 'canonical_pair')) {
            $data['canonical_pair'] = min($this->testTenantId, $partnerTenantId) . '-' . max($this->testTenantId, $partnerTenantId);
        }

        return (int) DB::table('federation_partnerships')->insertGetId($data);
    }

    public function test_permissions_cannot_be_edited_on_a_non_active_partnership(): void
    {
        $partnerTenantId = $this->seedPartnerTenant();

        foreach (['pending', 'rejected', 'suspended', 'terminated'] as $status) {
            $id = $this->seedPartnership($this->seedPartnerTenant(), $status, 4);
            $result = FederationPartnershipService::updatePermissions($id, 1, ['messaging' => false]);
            $this->assertFalse((bool) ($result['success'] ?? true), "Permissions edit must be rejected on a '{$status}' partnership");
        }

        // Sanity: an active partnership CAN be edited.
        $activeId = $this->seedPartnership($partnerTenantId, 'active', 4);
        $result = FederationPartnershipService::updatePermissions($activeId, 1, ['messaging' => false]);
        $this->assertTrue((bool) ($result['success'] ?? false));
        $this->assertSame(0, (int) DB::table('federation_partnerships')->where('id', $activeId)->value('messaging_enabled'));
    }

    public function test_unknown_permission_keys_are_rejected(): void
    {
        $id = $this->seedPartnership($this->seedPartnerTenant(), 'active', 4);

        $result = FederationPartnershipService::updatePermissions($id, 1, ['transactions' => true, 'sudo' => true]);

        $this->assertFalse((bool) ($result['success'] ?? true));
        $this->assertStringContainsString('sudo', (string) ($result['error'] ?? ''));
    }

    public function test_permission_cannot_exceed_federation_level_cap(): void
    {
        // Level 1 (discovery) only grants profiles by default — transactions
        // must not be switchable on.
        $id = $this->seedPartnership($this->seedPartnerTenant(), 'active', 1);

        $result = FederationPartnershipService::updatePermissions($id, 1, ['transactions' => true]);

        $this->assertFalse((bool) ($result['success'] ?? true));
        $this->assertSame(0, (int) DB::table('federation_partnerships')->where('id', $id)->value('transactions_enabled'));

        // Disabling a flag below the cap is always allowed.
        $ok = FederationPartnershipService::updatePermissions($id, 1, ['profiles' => false]);
        $this->assertTrue((bool) ($ok['success'] ?? false));
        $this->assertSame(0, (int) DB::table('federation_partnerships')->where('id', $id)->value('profiles_enabled'));
    }
}
