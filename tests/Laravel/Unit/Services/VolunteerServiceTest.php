<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\VolunteerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class VolunteerServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(2);
    }

    public function test_getOpportunities_returns_expected_structure(): void
    {
        $result = VolunteerService::getOpportunities();

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('cursor', $result);
        $this->assertArrayHasKey('has_more', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsBool($result['has_more']);
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        $this->assertNull(VolunteerService::getById(2147483647));
    }

    public function test_verifyHours_auto_pay_keeps_fractional_remainder_in_org_wallet(): void
    {
        $admin = User::factory()->forTenant(2)->create(['balance' => 0]);
        $volunteer = User::factory()->forTenant(2)->create(['balance' => 0]);

        $orgId = DB::table('vol_organizations')->insertGetId([
            'tenant_id' => 2,
            'user_id' => $admin->id,
            'name' => 'Fractional Hours Org',
            'slug' => 'fractional-hours-org-' . uniqid(),
            'description' => 'A volunteer organisation used for wallet regression coverage.',
            'contact_email' => 'fractional@example.test',
            'status' => 'active',
            'auto_pay_enabled' => 1,
            'balance' => 10.00,
            'created_at' => now(),
        ]);

        $opportunityId = DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => 2,
            'organization_id' => $orgId,
            'title' => 'Fractional Hours Opportunity',
            'description' => 'Help with a fractional shift.',
            'is_active' => 1,
            'status' => 'open',
            'created_by' => $admin->id,
            'created_at' => now(),
        ]);

        $logId = DB::table('vol_logs')->insertGetId([
            'tenant_id' => 2,
            'user_id' => $volunteer->id,
            'organization_id' => $orgId,
            'opportunity_id' => $opportunityId,
            'date_logged' => now()->subDay()->toDateString(),
            'hours' => 2.75,
            'description' => 'Fractional shift',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        // Factory creates above fire model observers that reset TenantContext to
        // tenant 1; re-pin tenant 2 so verifyHours scopes to the seeded rows.
        TenantContext::setById(2);

        $this->assertTrue(VolunteerService::verifyHours($logId, $admin->id, 'approve'));

        $this->assertEquals(8.00, (float) DB::table('vol_organizations')->where('id', $orgId)->value('balance'));
        $this->assertEquals(2, (int) DB::table('users')->where('id', $volunteer->id)->value('balance'));

        $orgTx = DB::table('vol_org_transactions')->where('vol_log_id', $logId)->first();
        $this->assertNotNull($orgTx);
        $this->assertEquals(-2.00, (float) $orgTx->amount);
        $this->assertEquals(8.00, (float) $orgTx->balance_after);

        $mainTx = DB::table('transactions')
            ->where('tenant_id', 2)
            ->where('receiver_id', $volunteer->id)
            ->where('transaction_type', 'volunteer')
            ->latest('id')
            ->first();
        $this->assertNotNull($mainTx);
        $this->assertEquals(2, (int) $mainTx->amount);
    }

    /**
     * Classic timebanking: approving hours ALWAYS mints credits to the volunteer,
     * even when the org wallet is empty and auto-pay is disabled. The org wallet
     * is a reconciliation figure and is allowed to go negative.
     */
    public function test_verifyHours_mints_credits_even_when_org_wallet_empty(): void
    {
        $admin = User::factory()->forTenant(2)->create(['balance' => 0]);
        $volunteer = User::factory()->forTenant(2)->create(['balance' => 0]);

        $orgId = DB::table('vol_organizations')->insertGetId([
            'tenant_id' => 2,
            'user_id' => $admin->id,
            'name' => 'Empty Wallet Org',
            'slug' => 'empty-wallet-org-' . uniqid(),
            'description' => 'A volunteer organisation with an empty wallet and auto-pay off.',
            'contact_email' => 'empty@example.test',
            'status' => 'active',
            'auto_pay_enabled' => 0, // auto-pay OFF — approval must still mint
            'balance' => 0.00,        // empty wallet — approval must still mint
            'created_at' => now(),
        ]);

        $logId = DB::table('vol_logs')->insertGetId([
            'tenant_id' => 2,
            'user_id' => $volunteer->id,
            'organization_id' => $orgId,
            'opportunity_id' => null,
            'date_logged' => now()->subDay()->toDateString(),
            'hours' => 3,
            'description' => 'Three whole hours',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        TenantContext::setById(2);

        $this->assertTrue(VolunteerService::verifyHours($logId, $admin->id, 'approve'));
        $this->assertSame('paid', VolunteerService::getLastPaymentOutcome());

        // Volunteer credited 3 whole hours.
        $this->assertEquals(3, (int) DB::table('users')->where('id', $volunteer->id)->value('balance'));

        // Org wallet driven NEGATIVE (0 - 3).
        $this->assertEquals(-3.00, (float) DB::table('vol_organizations')->where('id', $orgId)->value('balance'));

        // Log is approved.
        $this->assertSame('approved', DB::table('vol_logs')->where('id', $logId)->value('status'));

        // Ledger row recorded with the negative post-debit balance.
        $orgTx = DB::table('vol_org_transactions')->where('vol_log_id', $logId)->first();
        $this->assertNotNull($orgTx);
        $this->assertEquals(-3.00, (float) $orgTx->amount);
        $this->assertEquals(-3.00, (float) $orgTx->balance_after);

        $mainTx = DB::table('transactions')
            ->where('tenant_id', 2)
            ->where('receiver_id', $volunteer->id)
            ->where('transaction_type', 'volunteer')
            ->latest('id')
            ->first();
        $this->assertNotNull($mainTx);
        $this->assertEquals(3, (int) $mainTx->amount);
    }

    /**
     * Double-approval must not double-credit: the second call is idempotent.
     */
    public function test_verifyHours_double_approve_does_not_double_credit(): void
    {
        $admin = User::factory()->forTenant(2)->create(['balance' => 0]);
        $volunteer = User::factory()->forTenant(2)->create(['balance' => 0]);

        $orgId = DB::table('vol_organizations')->insertGetId([
            'tenant_id' => 2,
            'user_id' => $admin->id,
            'name' => 'Idempotency Org',
            'slug' => 'idempotency-org-' . uniqid(),
            'description' => 'A volunteer organisation used for idempotency coverage.',
            'contact_email' => 'idempotency@example.test',
            'status' => 'active',
            'auto_pay_enabled' => 1,
            'balance' => 5.00,
            'created_at' => now(),
        ]);

        $logId = DB::table('vol_logs')->insertGetId([
            'tenant_id' => 2,
            'user_id' => $volunteer->id,
            'organization_id' => $orgId,
            'opportunity_id' => null,
            'date_logged' => now()->subDay()->toDateString(),
            'hours' => 2,
            'description' => 'Two whole hours',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        TenantContext::setById(2);

        // First approval mints credits.
        $this->assertTrue(VolunteerService::verifyHours($logId, $admin->id, 'approve'));
        $this->assertSame('paid', VolunteerService::getLastPaymentOutcome());

        // Second approval: the log is no longer pending, so the pre-transaction
        // "only pending can be verified" guard rejects it — no re-credit.
        $this->assertFalse(VolunteerService::verifyHours($logId, $admin->id, 'approve'));

        // Credited exactly once.
        $this->assertEquals(2, (int) DB::table('users')->where('id', $volunteer->id)->value('balance'));
        $this->assertEquals(3.00, (float) DB::table('vol_organizations')->where('id', $orgId)->value('balance'));
        $this->assertSame(1, DB::table('vol_org_transactions')->where('vol_log_id', $logId)->where('type', 'volunteer_payment')->count());
    }

    public function test_create_review_rejects_cross_tenant_application_history(): void
    {
        DB::table('tenants')->insertOrIgnore([
            'id' => 999,
            'name' => 'Foreign Review Tenant',
            'slug' => 'foreign-review-tenant',
            'is_active' => true,
            'depth' => 0,
            'allows_subtenants' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $owner = User::factory()->forTenant(2)->create();
        $reviewer = User::factory()->forTenant(2)->create();
        $target = User::factory()->forTenant(2)->create();

        $orgId = DB::table('vol_organizations')->insertGetId([
            'tenant_id' => 2,
            'user_id' => $owner->id,
            'name' => 'Review Isolation Org',
            'slug' => 'review-isolation-org-' . uniqid(),
            'description' => 'Organisation used for volunteer review tenant isolation coverage.',
            'contact_email' => 'review-isolation@example.test',
            'status' => 'active',
            'balance' => 0.00,
            'created_at' => now(),
        ]);

        $opportunityId = DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => 2,
            'organization_id' => $orgId,
            'title' => 'Tenant-scoped Review Shift',
            'description' => 'A shared volunteering opportunity.',
            'is_active' => 1,
            'status' => 'open',
            'created_by' => $owner->id,
            'created_at' => now(),
        ]);

        DB::table('vol_applications')->insert([
            'tenant_id' => 2,
            'opportunity_id' => $opportunityId,
            'user_id' => $reviewer->id,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        DB::table('vol_applications')->insert([
            'tenant_id' => 999,
            'opportunity_id' => $opportunityId,
            'user_id' => $target->id,
            'status' => 'approved',
            'created_at' => now(),
        ]);

        TenantContext::setById(2);
        $reviewId = VolunteerService::createReview($reviewer->id, 'user', $target->id, 5, 'Great teamwork.');

        $this->assertNull($reviewId);
        $this->assertSame('FORBIDDEN', VolunteerService::getErrors()[0]['code'] ?? null);
        $this->assertDatabaseMissing('vol_reviews', [
            'tenant_id' => 2,
            'reviewer_id' => $reviewer->id,
            'target_type' => 'user',
            'target_id' => $target->id,
        ]);
    }

    public function test_createOrganization_rejects_non_http_website_scheme(): void
    {
        // Regression (audit M5): the website renders as a public <a href>, so a
        // javascript:/data: URL is a link-injection hole. FILTER_VALIDATE_URL
        // alone accepted these — the scheme allow-list must reject them.
        $owner = User::factory()->forTenant(2)->create();

        $orgId = VolunteerService::createOrganization($owner->id, [
            'name' => 'Scheme Test Org',
            'description' => 'A description long enough to pass validation checks.',
            'contact_email' => 'scheme-org@test.test',
            'website' => 'javascript://%0aalert(1)',
        ]);

        $this->assertNull($orgId);
        $this->assertSame('website', VolunteerService::getErrors()[0]['field'] ?? null);
    }

    public function test_createOrganization_accepts_https_website(): void
    {
        $owner = User::factory()->forTenant(2)->create();

        $orgId = VolunteerService::createOrganization($owner->id, [
            'name' => 'Valid Website Org ' . uniqid(),
            'description' => 'A description long enough to pass validation checks.',
            'contact_email' => 'valid-org@test.test',
            'website' => 'https://example.org',
        ]);

        $this->assertNotNull($orgId);
    }
}
