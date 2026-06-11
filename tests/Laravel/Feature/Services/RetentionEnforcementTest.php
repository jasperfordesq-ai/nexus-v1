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
}
