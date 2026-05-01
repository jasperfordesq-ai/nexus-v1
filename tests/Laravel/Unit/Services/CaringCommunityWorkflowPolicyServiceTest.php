<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\CaringCommunityWorkflowPolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class CaringCommunityWorkflowPolicyServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->testTenantId);
        // Wipe any existing workflow policy keys for this tenant so tests start clean.
        DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->where('setting_key', 'like', 'caring_community.workflow.%')
            ->delete();
    }

    private function service(): CaringCommunityWorkflowPolicyService
    {
        return app(CaringCommunityWorkflowPolicyService::class);
    }

    public function test_get_returns_safe_defaults_when_no_db_rows_exist(): void
    {
        $policy = $this->service()->get($this->testTenantId);

        $this->assertTrue($policy['approval_required']);
        $this->assertFalse($policy['auto_approve_trusted_reviewers']);
        $this->assertSame(7, $policy['review_sla_days']);
        $this->assertSame(14, $policy['escalation_sla_days']);
        $this->assertTrue($policy['allow_member_self_log']);
        $this->assertTrue($policy['require_organisation_for_partner_hours']);
        $this->assertSame(1, $policy['monthly_statement_day']);
        $this->assertSame('last_90_days', $policy['municipal_report_default_period']);
        $this->assertTrue($policy['include_social_value_estimate']);
        $this->assertSame(35, $policy['default_hour_value_chf']);
    }

    public function test_update_persists_and_get_reads_back(): void
    {
        $this->service()->update($this->testTenantId, [
            'approval_required' => false,
            'review_sla_days' => 10,
            'escalation_sla_days' => 20,
            'monthly_statement_day' => 15,
            'default_hour_value_chf' => 40,
        ]);

        $policy = $this->service()->get($this->testTenantId);

        $this->assertFalse($policy['approval_required']);
        $this->assertSame(10, $policy['review_sla_days']);
        $this->assertSame(20, $policy['escalation_sla_days']);
        $this->assertSame(15, $policy['monthly_statement_day']);
        $this->assertSame(40, $policy['default_hour_value_chf']);
    }

    public function test_update_is_idempotent(): void
    {
        $this->service()->update($this->testTenantId, ['review_sla_days' => 5]);
        $this->service()->update($this->testTenantId, ['review_sla_days' => 5]);

        $count = DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->where('setting_key', 'caring_community.workflow.review_sla_days')
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_escalation_sla_is_always_at_least_review_sla(): void
    {
        // Try to set escalation below review — normaliser should floor it.
        $this->service()->update($this->testTenantId, [
            'review_sla_days' => 14,
            'escalation_sla_days' => 3,
        ]);

        $policy = $this->service()->get($this->testTenantId);

        $this->assertGreaterThanOrEqual($policy['review_sla_days'], $policy['escalation_sla_days']);
    }

    public function test_review_sla_is_clamped_between_1_and_30(): void
    {
        $this->service()->update($this->testTenantId, ['review_sla_days' => 0]);
        $this->assertSame(1, $this->service()->get($this->testTenantId)['review_sla_days']);

        $this->service()->update($this->testTenantId, ['review_sla_days' => 999]);
        $this->assertSame(30, $this->service()->get($this->testTenantId)['review_sla_days']);
    }

    public function test_monthly_statement_day_is_clamped_to_1_28(): void
    {
        $this->service()->update($this->testTenantId, ['monthly_statement_day' => 0]);
        $this->assertSame(1, $this->service()->get($this->testTenantId)['monthly_statement_day']);

        $this->service()->update($this->testTenantId, ['monthly_statement_day' => 31]);
        $this->assertSame(28, $this->service()->get($this->testTenantId)['monthly_statement_day']);
    }

    public function test_hour_value_chf_is_clamped_to_0_500(): void
    {
        $this->service()->update($this->testTenantId, ['default_hour_value_chf' => -10]);
        $this->assertSame(0, $this->service()->get($this->testTenantId)['default_hour_value_chf']);

        $this->service()->update($this->testTenantId, ['default_hour_value_chf' => 9999]);
        $this->assertSame(500, $this->service()->get($this->testTenantId)['default_hour_value_chf']);
    }

    public function test_unknown_period_falls_back_to_default(): void
    {
        $this->service()->update($this->testTenantId, ['municipal_report_default_period' => 'last_forever']);

        $policy = $this->service()->get($this->testTenantId);

        $this->assertSame('last_90_days', $policy['municipal_report_default_period']);
    }

    public function test_boolean_fields_are_cast_correctly_from_stringified_storage(): void
    {
        // Simulate settings stored as strings (the underlying storage format).
        DB::table('tenant_settings')->insert([
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'caring_community.workflow.approval_required', 'setting_value' => '0', 'setting_type' => 'boolean', 'category' => 'caring_community', 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'caring_community.workflow.auto_approve_trusted_reviewers', 'setting_value' => '1', 'setting_type' => 'boolean', 'category' => 'caring_community', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $policy = $this->service()->get($this->testTenantId);

        $this->assertFalse($policy['approval_required']);
        $this->assertTrue($policy['auto_approve_trusted_reviewers']);
        $this->assertIsBool($policy['approval_required']);
        $this->assertIsBool($policy['auto_approve_trusted_reviewers']);
    }

    public function test_settings_are_scoped_per_tenant(): void
    {
        $this->service()->update($this->testTenantId, ['review_sla_days' => 3]);
        $this->service()->update(999, ['review_sla_days' => 20]);

        $this->assertSame(3, $this->service()->get($this->testTenantId)['review_sla_days']);
        $this->assertSame(20, $this->service()->get(999)['review_sla_days']);
    }
}
