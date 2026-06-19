<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Accessible (GOV.UK) frontend — Bias Audit admin page.
 *
 * Covers: admin sees the page (200) with metric table headings; non-admin
 * member gets 403; feature-off gets 403; anonymous redirected to login.
 * Mirrors setUp scrubbing from JobsParityTest.
 */
class JobsBiasAuditParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->forgetGuards();

        foreach ([
            'HTTP_X_TENANT_ID',
            'HTTP_X_TENANT_SLUG',
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
        ] as $serverKey) {
            unset($_SERVER[$serverKey]);
        }

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $this->enableJobsFeature();
    }

    // =========================================================================
    // Auth gating
    // =========================================================================

    public function test_jobs_bias_audit_requires_authentication(): void
    {
        $url      = "/{$this->testTenantSlug}/alpha/jobs/bias-audit";
        $response = $this->get($url);

        $response->assertRedirect();
        $this->assertStringContainsString(
            "/{$this->testTenantSlug}/alpha/login",
            $response->headers->get('Location') ?? ''
        );
    }

    // =========================================================================
    // Admin-only access
    // =========================================================================

    public function test_jobs_bias_audit_renders_for_admin(): void
    {
        $this->authenticatedAdmin();

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/bias-audit");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_jobs.bias_audit.title'));
        $response->assertSee(__('govuk_alpha_jobs.bias_audit.funnel_heading'));
        $response->assertSee(__('govuk_alpha_jobs.bias_audit.rejection_rates_heading'));
        $response->assertSee(__('govuk_alpha_jobs.bias_audit.time_in_stage_heading'));
        $response->assertSee(__('govuk_alpha_jobs.bias_audit.skills_match_heading'));
        $response->assertSee(__('govuk_alpha_jobs.bias_audit.source_effectiveness_heading'));
    }

    public function test_jobs_bias_audit_forbidden_for_regular_member(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/bias-audit");

        $response->assertForbidden();
    }

    // =========================================================================
    // Feature gate
    // =========================================================================

    public function test_jobs_bias_audit_forbidden_when_feature_off(): void
    {
        $this->disableJobsFeature();
        $this->authenticatedAdmin();

        $response = $this->get("/{$this->testTenantSlug}/alpha/jobs/bias-audit");

        $response->assertForbidden();
    }

    // =========================================================================
    // Filter params accepted without error
    // =========================================================================

    public function test_jobs_bias_audit_accepts_date_range_and_job_id_filters(): void
    {
        $this->authenticatedAdmin();

        $response = $this->get(
            "/{$this->testTenantSlug}/alpha/jobs/bias-audit?from=2026-01-01&to=2026-06-30&job_id=999999"
        );

        // Should render (200) even when job_id matches nothing — empty state shown
        $response->assertOk();
        $response->assertSee(__('govuk_alpha_jobs.bias_audit.title'));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function enableJobsFeature(): void
    {
        $row     = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['job_vacancies'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function disableJobsFeature(): void
    {
        $row     = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['job_vacancies'] = false;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    /** Authenticated non-admin member. */
    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status'      => 'active',
            'is_approved' => true,
            'role'        => 'member',
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /** Authenticated tenant admin. */
    private function authenticatedAdmin(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status'      => 'active',
            'is_approved' => true,
            'role'        => 'admin',
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }
}
