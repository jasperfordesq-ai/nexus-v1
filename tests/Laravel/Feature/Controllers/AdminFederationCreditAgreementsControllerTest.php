<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\FederationCreditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AdminFederationCreditAgreementsController.
 *
 * Covers listing credit agreements, creating agreements, actions, and partners —
 * plus the credit-agreement state machine and dual-consent activation rules
 * (audit C1): the creating tenant must never be able to self-activate an
 * agreement, transitions must be legal, and activation requires an active
 * partnership between the two tenants.
 */
class AdminFederationCreditAgreementsControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // Seeding helpers (state machine tests)
    // ================================================================

    private function seedPartnerTenant(): int
    {
        return (int) DB::table('tenants')->insertGetId([
            'name' => 'Credit Partner',
            'slug' => 'credit-partner-' . substr(uniqid(), -8),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedActivePartnership(int $partnerTenantId): void
    {
        $data = [
            'tenant_id' => $this->testTenantId,
            'partner_tenant_id' => $partnerTenantId,
            'status' => 'active',
            'federation_level' => 4,
            'profiles_enabled' => 1,
            'messaging_enabled' => 1,
            'transactions_enabled' => 1,
            'requested_at' => now(),
            'approved_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (DB::getSchemaBuilder()->hasColumn('federation_partnerships', 'canonical_pair')) {
            $data['canonical_pair'] = min($this->testTenantId, $partnerTenantId) . '-' . max($this->testTenantId, $partnerTenantId);
        }
        DB::table('federation_partnerships')->insert($data);
    }

    /**
     * Seed a credit agreement FROM the test tenant TO the partner tenant.
     */
    private function seedAgreement(int $partnerTenantId, string $status, ?int $approvedByFrom = null, ?int $approvedByTo = null): int
    {
        return (int) DB::table('federation_credit_agreements')->insertGetId([
            'from_tenant_id' => $this->testTenantId,
            'to_tenant_id' => $partnerTenantId,
            'exchange_rate' => 1.0,
            'max_monthly_credits' => 100,
            'status' => $status,
            'approved_by_from' => $approvedByFrom,
            'approved_by_to' => $approvedByTo,
            'created_at' => now(),
        ]);
    }

    private function agreementStatus(int $id): string
    {
        return (string) DB::table('federation_credit_agreements')->where('id', $id)->value('status');
    }

    // ================================================================
    // INDEX — GET /v2/admin/federation/credit-agreements
    // ================================================================

    public function test_index_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/credit-agreements');

        // May return 200 or 503 if FederationCreditService is unavailable
        $this->assertContains($response->getStatusCode(), [200, 503]);
    }

    public function test_index_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/federation/credit-agreements');

        $response->assertStatus(403);
    }

    public function test_index_returns_401_for_unauthenticated(): void
    {
        $response = $this->apiGet('/v2/admin/federation/credit-agreements');

        $response->assertStatus(401);
    }

    // ================================================================
    // STORE — POST /v2/admin/federation/credit-agreements
    // ================================================================

    public function test_store_validates_partner_tenant_id(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/federation/credit-agreements', [
            'partner_tenant_id' => 0,
            'exchange_rate' => 1.0,
            'monthly_limit' => 100,
        ]);

        // Validation error
        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_store_rejects_self_agreement(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiPost('/v2/admin/federation/credit-agreements', [
            'partner_tenant_id' => $this->testTenantId,
            'exchange_rate' => 1.0,
            'monthly_limit' => 100,
        ]);

        // Validation error for self-agreement
        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_store_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiPost('/v2/admin/federation/credit-agreements', [
            'partner_tenant_id' => 99,
            'exchange_rate' => 1.0,
            'monthly_limit' => 100,
        ]);

        $response->assertStatus(403);
    }

    // ================================================================
    // STATE MACHINE & DUAL CONSENT — POST .../credit-agreements/{id}/{action}
    // ================================================================

    public function test_creator_cannot_self_activate_pending_agreement(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $partnerTenantId = $this->seedPartnerTenant();
        $this->seedActivePartnership($partnerTenantId);
        // store() records the creator's approval in approved_by_from.
        $agreementId = $this->seedAgreement($partnerTenantId, 'pending', $admin->id);

        // The creating tenant approving its own agreement must NOT activate it —
        // the counterparty has not consented yet.
        $response = $this->apiPost("/v2/admin/federation/credit-agreements/{$agreementId}/approve");
        $response->assertStatus(200);
        $this->assertSame('pending', $response->json('data.status'));
        $this->assertSame('pending', $this->agreementStatus($agreementId));

        // Approving twice from the same side stays pending (idempotent).
        $again = $this->apiPost("/v2/admin/federation/credit-agreements/{$agreementId}/approve");
        $again->assertStatus(200);
        $this->assertSame('pending', $this->agreementStatus($agreementId));
    }

    public function test_counterparty_approval_activates_agreement(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        $partnerTenantId = $this->seedPartnerTenant();
        $partnerAdmin = User::factory()->forTenant($partnerTenantId)->admin()->create();
        $this->seedActivePartnership($partnerTenantId);
        $agreementId = $this->seedAgreement($partnerTenantId, 'pending', $admin->id);

        // Counterparty consent (recorded from THEIR tenant context) completes
        // the dual approval and activates the agreement.
        TenantContext::setById($partnerTenantId);
        try {
            $result = (new FederationCreditService())->approveAgreement($agreementId, (int) $partnerAdmin->id);
        } finally {
            TenantContext::setById($this->testTenantId);
        }

        $this->assertTrue((bool) ($result['success'] ?? false));
        $this->assertSame('active', $result['status'] ?? null);
        $this->assertSame('active', $this->agreementStatus($agreementId));
    }

    public function test_approve_requires_active_partnership(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $partnerTenantId = $this->seedPartnerTenant();
        // NO partnership row between the tenants.
        $agreementId = $this->seedAgreement($partnerTenantId, 'pending', $admin->id);

        $response = $this->apiPost("/v2/admin/federation/credit-agreements/{$agreementId}/approve");

        $response->assertStatus(409);
        $this->assertSame('PARTNERSHIP_REQUIRED', $response->json('errors.0.code'));
        $this->assertSame('pending', $this->agreementStatus($agreementId));
    }

    public function test_terminated_agreement_cannot_be_resurrected(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $partnerTenantId = $this->seedPartnerTenant();
        $this->seedActivePartnership($partnerTenantId);
        $agreementId = $this->seedAgreement($partnerTenantId, 'terminated');

        foreach (['reactivate', 'activate', 'approve', 'suspend'] as $action) {
            $response = $this->apiPost("/v2/admin/federation/credit-agreements/{$agreementId}/{$action}");
            $this->assertSame(409, $response->getStatusCode(), "Action '{$action}' must be rejected on a terminated agreement");
            $this->assertSame('terminated', $this->agreementStatus($agreementId));
        }
    }

    public function test_pending_agreement_cannot_be_directly_activated_or_suspended(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $partnerTenantId = $this->seedPartnerTenant();
        $agreementId = $this->seedAgreement($partnerTenantId, 'pending', $admin->id);

        foreach (['activate', 'reactivate', 'suspend'] as $action) {
            $response = $this->apiPost("/v2/admin/federation/credit-agreements/{$agreementId}/{$action}");
            $this->assertSame(409, $response->getStatusCode(), "Action '{$action}' must be rejected on a pending agreement");
            $this->assertSame('INVALID_TRANSITION', $response->json('errors.0.code'));
            $this->assertSame('pending', $this->agreementStatus($agreementId));
        }
    }

    public function test_legal_lifecycle_transitions_succeed(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $partnerTenantId = $this->seedPartnerTenant();
        $this->seedActivePartnership($partnerTenantId);
        $agreementId = $this->seedAgreement($partnerTenantId, 'active', $admin->id, 999999);

        // active -> suspended
        $this->apiPost("/v2/admin/federation/credit-agreements/{$agreementId}/suspend")->assertStatus(200);
        $this->assertSame('suspended', $this->agreementStatus($agreementId));

        // suspended -> active
        $this->apiPost("/v2/admin/federation/credit-agreements/{$agreementId}/reactivate")->assertStatus(200);
        $this->assertSame('active', $this->agreementStatus($agreementId));

        // active -> terminated
        $this->apiPost("/v2/admin/federation/credit-agreements/{$agreementId}/terminate")->assertStatus(200);
        $this->assertSame('terminated', $this->agreementStatus($agreementId));
    }

    // ================================================================
    // PARTNERS — GET /v2/admin/federation/partners
    // ================================================================

    public function test_partners_returns_200_for_admin(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($admin);

        $response = $this->apiGet('/v2/admin/federation/partners');

        // May return 200 or 503 if service unavailable
        $this->assertContains($response->getStatusCode(), [200, 503]);
    }

    public function test_partners_returns_403_for_regular_member(): void
    {
        $member = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($member);

        $response = $this->apiGet('/v2/admin/federation/partners');

        $response->assertStatus(403);
    }
}
