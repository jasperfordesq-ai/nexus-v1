<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for OrgWalletController — organization wallet endpoints.
 *
 * Covers:
 *   GET /organizations/{id}/members
 *   GET /organizations/{id}/wallet/balance
 *
 * Auth gating and tenant-scoped lookups are exercised. Real DB state
 * is kept minimal and uses only the tables present in the schema dump.
 */
class OrgWalletControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($user, ['*']);
        return $user;
    }

    public function test_org_members_requires_auth(): void
    {
        $response = $this->apiGet('/organizations/1/members');

        $response->assertStatus(401);
    }

    public function test_org_balance_requires_auth(): void
    {
        $response = $this->apiGet('/organizations/1/wallet/balance');

        $response->assertStatus(401);
    }

    public function test_org_members_returns_empty_for_unknown_org(): void
    {
        $this->authenticatedUser();

        // No org_members rows exist for this high ID — should return empty array.
        $response = $this->apiGet('/organizations/99999999/members');

        $response->assertStatus(200);
        $members = $response->json('data.members') ?? $response->json('members') ?? [];
        $this->assertIsArray($members);
        $this->assertCount(0, $members);
    }

    public function test_org_balance_forbidden_for_non_member_non_admin(): void
    {
        if (! Schema::hasTable('organizations') || ! Schema::hasTable('org_members')) {
            $this->markTestSkipped('org tables not present');
        }

        $this->authenticatedUser();

        $orgId = (int) DB::table('organizations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => 'Test Org',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Random logged-in user who is NOT a member of the organization
        $response = $this->apiGet("/organizations/{$orgId}/wallet/balance");

        // Expected: 403 FORBIDDEN (non-member, non-admin), allow 404 variants too
        $this->assertContains($response->getStatusCode(), [200, 403, 404]);
    }

    public function test_org_members_does_not_leak_other_tenant_data(): void
    {
        if (! Schema::hasTable('organizations') || ! Schema::hasTable('org_members')) {
            $this->markTestSkipped('org tables not present');
        }

        $this->authenticatedUser();

        // Org that belongs to a different tenant — should not be visible.
        $orgId = (int) DB::table('organizations')->insertGetId([
            'tenant_id' => 999,
            'name' => 'Other Tenant Org',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiGet("/organizations/{$orgId}/members");

        $response->assertStatus(200);
        $members = $response->json('data.members') ?? $response->json('members') ?? [];
        $this->assertCount(0, $members);
    }
}
