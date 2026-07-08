<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Services;

use App\Services\RetentionPolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class RetentionEnforcementTest extends TestCase
{
    use DatabaseTransactions;

    private function insertActivityRow(int $tenantId, \DateTimeInterface $createdAt): int
    {
        return (int) DB::table('activity_log')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => null,
            'action' => 'retention_test',
            'created_at' => $createdAt,
        ]);
    }

    public function test_enforcement_deletes_only_expired_rows_for_the_policy_tenant(): void
    {
        $otherTenantId = $this->testTenantId + 1;

        $expiredId = $this->insertActivityRow($this->testTenantId, now()->subDays(400));
        $freshId = $this->insertActivityRow($this->testTenantId, now()->subDays(10));
        // Same age as the expired row but belongs to ANOTHER tenant — must survive
        $otherTenantId1 = $this->insertActivityRow($otherTenantId, now()->subDays(400));

        $error = RetentionPolicyService::upsertPolicy($this->testTenantId, 'activity_log', 365, true);
        $this->assertNull($error);

        $results = RetentionPolicyService::enforceForTenant($this->testTenantId);

        $this->assertArrayHasKey('activity_log', $results);
        $this->assertSame('completed', $results['activity_log']['status']);
        $this->assertGreaterThanOrEqual(1, $results['activity_log']['affected']);

        $this->assertNull(DB::table('activity_log')->find($expiredId), 'expired row must be disposed');
        $this->assertNotNull(DB::table('activity_log')->find($freshId), 'fresh row must survive');
        $this->assertNotNull(DB::table('activity_log')->find($otherTenantId1), 'other tenant data must never be touched');

        // Disposal must be evidenced in the run log
        $run = DB::table('tenant_retention_runs')
            ->where('tenant_id', $this->testTenantId)
            ->where('data_type', 'activity_log')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($run);
        $this->assertSame('completed', $run->status);
    }

    public function test_disabled_policies_are_not_enforced(): void
    {
        $expiredId = $this->insertActivityRow($this->testTenantId, now()->subDays(400));

        $error = RetentionPolicyService::upsertPolicy($this->testTenantId, 'activity_log', 365, false);
        $this->assertNull($error);

        $results = RetentionPolicyService::enforceForTenant($this->testTenantId);

        $this->assertArrayNotHasKey('activity_log', $results);
        $this->assertNotNull(DB::table('activity_log')->find($expiredId), 'disabled policy must retain data');
    }

    public function test_safeguarding_purge_never_touches_open_cases(): void
    {
        $t = $this->testTenantId;
        $mk = fn (string $status, \DateTimeInterface $created): int => (int) DB::table('vol_safeguarding_incidents')->insertGetId([
            'tenant_id' => $t,
            'reported_by' => 1,
            'incident_type' => 'concern',
            'severity' => 'low',
            'description' => 'x',
            'status' => $status,
            'created_at' => $created,
            'updated_at' => $created,
        ]);

        $oldOpen = $mk('open', now()->subDays(4000));          // ancient but OPEN — must survive
        $oldInvestigating = $mk('investigating', now()->subDays(4000)); // active case — must survive
        $oldClosed = $mk('closed', now()->subDays(3000));      // concluded + expired — purged
        $freshClosed = $mk('closed', now()->subDays(10));      // concluded but fresh — survives

        $this->assertNull(RetentionPolicyService::upsertPolicy($t, 'vol_safeguarding_incidents', 2555, true));
        $results = RetentionPolicyService::enforceForTenant($t);

        $this->assertSame('completed', $results['vol_safeguarding_incidents']['status']);
        $this->assertNotNull(DB::table('vol_safeguarding_incidents')->find($oldOpen), 'open case must never be purged');
        $this->assertNotNull(DB::table('vol_safeguarding_incidents')->find($oldInvestigating), 'active case must never be purged');
        $this->assertNull(DB::table('vol_safeguarding_incidents')->find($oldClosed), 'expired closed case must be purged');
        $this->assertNotNull(DB::table('vol_safeguarding_incidents')->find($freshClosed), 'fresh closed case must survive');
    }

    public function test_guardian_consent_purge_measures_from_expiry_and_skips_unexpired(): void
    {
        $t = $this->testTenantId;
        $mk = fn (?\DateTimeInterface $expires): int => (int) DB::table('vol_guardian_consents')->insertGetId([
            'tenant_id' => $t,
            'minor_user_id' => 1,
            'guardian_name' => 'G',
            'guardian_email' => 'g@example.test',
            'relationship' => 'parent',
            'consent_token' => bin2hex(random_bytes(32)),
            'status' => 'active',
            'expires_at' => $expires,
            'created_at' => now()->subDays(4000),
        ]);

        $longLapsed = $mk(now()->subDays(500));  // expired > 365d ago — purged
        $justLapsed = $mk(now()->subDays(10));   // expired recently — retained as evidence
        $noExpiry = $mk(null);                   // no expiry — never matched

        $this->assertNull(RetentionPolicyService::upsertPolicy($t, 'vol_guardian_consents', 365, true));
        RetentionPolicyService::enforceForTenant($t);

        $this->assertNull(DB::table('vol_guardian_consents')->find($longLapsed), 'long-lapsed consent must be purged');
        $this->assertNotNull(DB::table('vol_guardian_consents')->find($justLapsed), 'recently lapsed consent retained as evidence');
        $this->assertNotNull(DB::table('vol_guardian_consents')->find($noExpiry), 'no-expiry consent never matched');
    }
}
