<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Volunteering;

use App\Core\TenantContext;
use App\Services\GuardianConsentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Real-database regression tests for the guardian-consent lifecycle.
 *
 * Bug history (2026-06-12 Fable hunt): grantConsent / withdrawConsent /
 * expireOldConsents all wrote phantom columns (granted_at, granted_ip,
 * withdrawn_at, withdrawn_by, updated_at) that do not exist on
 * vol_guardian_consents — the real columns are consent_given_at, consent_ip
 * and consent_withdrawn_at. Every UPDATE threw, was swallowed by the
 * catch-all, and the methods returned false: consent could never be granted
 * or withdrawn, and the expiry cron errored daily in production. The old
 * unit tests mocked the DB facade so the wrong column names passed for
 * months — these tests hit the real table so a column drift fails loudly.
 */
class GuardianConsentLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
    }

    private function insertConsent(array $overrides = []): int
    {
        return (int) DB::table('vol_guardian_consents')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'minor_user_id' => 1,
            'guardian_name' => 'Test Guardian',
            'guardian_email' => 'guardian@example.com',
            'relationship' => 'parent',
            'consent_token' => bin2hex(random_bytes(32)),
            'status' => 'pending',
            'expires_at' => now()->addDays(30)->format('Y-m-d H:i:s'),
            'created_at' => now(),
        ], $overrides));
    }

    public function test_grant_consent_activates_record_with_real_columns(): void
    {
        $token = bin2hex(random_bytes(32));
        $id = $this->insertConsent(['consent_token' => $token]);

        $this->assertTrue(GuardianConsentService::grantConsent($token, '203.0.113.7'));

        $row = DB::table('vol_guardian_consents')->where('id', $id)->first();
        $this->assertSame('active', $row->status);
        $this->assertNotNull($row->consent_given_at);
        $this->assertSame('203.0.113.7', $row->consent_ip);
    }

    public function test_withdraw_consent_by_minor_sets_withdrawn_status(): void
    {
        $id = $this->insertConsent(['status' => 'active', 'minor_user_id' => 42]);

        $this->assertTrue(GuardianConsentService::withdrawConsent($id, 42));

        $row = DB::table('vol_guardian_consents')->where('id', $id)->first();
        $this->assertSame('withdrawn', $row->status);
        $this->assertNotNull($row->consent_withdrawn_at);
    }

    public function test_expire_old_consents_expires_overdue_pending_and_active_rows(): void
    {
        $pendingOverdue = $this->insertConsent([
            'expires_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ]);
        $activeOverdue = $this->insertConsent([
            'status' => 'active',
            'expires_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ]);
        $pendingFresh = $this->insertConsent();

        $expired = GuardianConsentService::expireOldConsents();

        $this->assertGreaterThanOrEqual(2, $expired);
        $this->assertSame('expired', DB::table('vol_guardian_consents')->where('id', $pendingOverdue)->value('status'));
        $this->assertSame('expired', DB::table('vol_guardian_consents')->where('id', $activeOverdue)->value('status'));
        $this->assertSame('pending', DB::table('vol_guardian_consents')->where('id', $pendingFresh)->value('status'));
    }

    public function test_expire_old_consents_is_cross_tenant(): void
    {
        // The cron caller (CronJobRunner::volunteerExpireConsentsInternal) runs
        // this once for the whole platform, whatever tenant the worker context
        // points at — rows in OTHER tenants must still expire.
        $otherTenantRow = $this->insertConsent([
            'tenant_id' => 1,
            'expires_at' => now()->subDay()->format('Y-m-d H:i:s'),
        ]);

        TenantContext::setById($this->testTenantId);
        GuardianConsentService::expireOldConsents();

        $this->assertSame('expired', DB::table('vol_guardian_consents')->where('id', $otherTenantRow)->value('status'));
    }

    public function test_admin_listing_maps_ui_fields_and_hides_consent_token(): void
    {
        $this->insertConsent(['status' => 'active']);

        $result = GuardianConsentService::getConsentsForAdmin();

        $this->assertNotEmpty($result['items']);
        $first = $result['items'][0];
        $this->assertArrayHasKey('consent_date', $first);
        $this->assertArrayHasKey('expires_date', $first);
        $this->assertArrayHasKey('opportunity_title', $first);
        $this->assertArrayNotHasKey('consent_token', $first);
    }
}
