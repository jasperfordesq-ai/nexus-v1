<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\JobBiasAuditService;
use Illuminate\Support\Facades\DB;
use Mockery;

class JobBiasAuditServiceTest extends TestCase
{
    private JobBiasAuditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new JobBiasAuditService();
    }

    // ── generateReport ──────────────────────────────────────────

    public function test_generateReport_returns_all_sections(): void
    {
        // Mock DB facade for all the queries called by generateReport
        $this->mockDbForReport();

        $result = $this->service->generateReport($this->testTenantId);

        $this->assertArrayHasKey('period', $result);
        $this->assertArrayHasKey('total_applications', $result);
        $this->assertArrayHasKey('funnel', $result);
        $this->assertArrayHasKey('rejection_rates', $result);
        $this->assertArrayHasKey('avg_time_in_stage', $result);
        $this->assertArrayHasKey('skills_match_correlation', $result);
        $this->assertArrayHasKey('source_effectiveness', $result);
        $this->assertArrayHasKey('hiring_velocity_days', $result);
    }

    public function test_generateReport_uses_default_12_month_period(): void
    {
        $this->mockDbForReport();

        $result = $this->service->generateReport($this->testTenantId);

        $this->assertSame(date('Y-m-d'), $result['period']['to']);
        $this->assertSame(date('Y-m-d', strtotime('-12 months')), $result['period']['from']);
    }

    public function test_generateReport_uses_custom_date_range(): void
    {
        $this->mockDbForReport();

        $result = $this->service->generateReport(
            $this->testTenantId,
            null,
            '2025-01-01',
            '2025-12-31'
        );

        $this->assertSame('2025-01-01', $result['period']['from']);
        $this->assertSame('2025-12-31', $result['period']['to']);
    }

    public function test_generateReport_with_specific_job_id(): void
    {
        $this->mockDbForReport();

        $result = $this->service->generateReport($this->testTenantId, 42);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_applications', $result);
    }

    public function test_generateReport_total_applications_is_integer(): void
    {
        $this->mockDbForReport();

        $result = $this->service->generateReport($this->testTenantId);

        $this->assertIsInt($result['total_applications']);
    }

    public function test_generateReport_funnel_contains_all_stages(): void
    {
        $this->mockDbForReport(totalApps: 10);

        $result = $this->service->generateReport($this->testTenantId);

        $stages = array_column($result['funnel'], 'stage');
        $this->assertContains('applied', $stages);
        $this->assertContains('screening', $stages);
        $this->assertContains('interview', $stages);
        $this->assertContains('offer', $stages);
        $this->assertContains('accepted', $stages);
    }

    public function test_generateReport_funnel_has_percentage_and_count(): void
    {
        $this->mockDbForReport(totalApps: 20);

        $result = $this->service->generateReport($this->testTenantId);

        foreach ($result['funnel'] as $stage) {
            $this->assertArrayHasKey('stage', $stage);
            $this->assertArrayHasKey('count', $stage);
            $this->assertArrayHasKey('percentage', $stage);
        }
    }

    public function test_generateReport_funnel_zero_total_returns_zero_percentages(): void
    {
        $this->mockDbForReport(totalApps: 0);

        $result = $this->service->generateReport($this->testTenantId);

        foreach ($result['funnel'] as $stage) {
            $this->assertSame(0, $stage['count']);
            $this->assertSame(0, $stage['percentage']);
        }
    }

    public function test_generateReport_rejection_rates_excludes_accepted(): void
    {
        $this->mockDbForReport();

        $result = $this->service->generateReport($this->testTenantId);

        $this->assertArrayHasKey('applied', $result['rejection_rates']);
        $this->assertArrayHasKey('screening', $result['rejection_rates']);
        $this->assertArrayNotHasKey('accepted', $result['rejection_rates']);
    }

    public function test_generateReport_skills_match_has_acceptance_rate(): void
    {
        $this->mockDbForReport();

        $result = $this->service->generateReport($this->testTenantId);

        $this->assertArrayHasKey('accepted_count', $result['skills_match_correlation']);
        $this->assertArrayHasKey('rejected_count', $result['skills_match_correlation']);
        $this->assertArrayHasKey('acceptance_rate', $result['skills_match_correlation']);
    }

    public function test_generateReport_source_effectiveness_contains_direct(): void
    {
        $this->mockDbForReport();

        $result = $this->service->generateReport($this->testTenantId);

        $this->assertArrayHasKey('direct', $result['source_effectiveness']);
    }

    public function test_generateReport_hiring_velocity_is_nullable_float(): void
    {
        $this->mockDbForReport();

        $result = $this->service->generateReport($this->testTenantId);

        $this->assertTrue(
            is_null($result['hiring_velocity_days']) || is_float($result['hiring_velocity_days']),
            'hiring_velocity_days should be null or float'
        );
    }

    // ── Helper ──────────────────────────────────────────────────

    /**
     * Set up comprehensive DB mock for all queries in generateReport.
     */
    private function mockDbForReport(int $totalApps = 5): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('join')->andReturnSelf();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereBetween')->andReturnSelf();
        $builder->shouldReceive('whereIn')->andReturnSelf();
        $builder->shouldReceive('whereExists')->andReturnSelf();
        $builder->shouldReceive('orWhere')->andReturnSelf();
        $builder->shouldReceive('whereColumn')->andReturnSelf();
        $builder->shouldReceive('count')->andReturn($totalApps);
        $builder->shouldReceive('select')->andReturnSelf();
        $builder->shouldReceive('selectRaw')->andReturnSelf();
        $builder->shouldReceive('groupBy')->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect([]));
        $builder->shouldReceive('first')->andReturn((object) ['total' => $totalApps, 'accepted' => 0]);
        $builder->shouldReceive('value')->andReturn(null);
        $builder->shouldReceive('exists')->andReturn(false);

        DB::shouldReceive('table')->andReturn($builder);
        DB::shouldReceive('raw')->andReturn('1');
    }
}
