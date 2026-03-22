<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\JobFeedService;
use App\Models\JobVacancy;
use Illuminate\Support\Facades\Cache;
use Mockery;

class JobFeedServiceTest extends TestCase
{
    private JobFeedService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new JobFeedService();
    }

    // ── generateRssFeed ─────────────────────────────────────────

    public function test_generateRssFeed_returns_cached_result(): void
    {
        $cachedXml = '<rss>cached</rss>';
        Cache::shouldReceive('get')->with("job_feed_rss_{$this->testTenantId}")->andReturn($cachedXml);

        $result = $this->service->generateRssFeed($this->testTenantId);
        $this->assertSame($cachedXml, $result);
    }

    public function test_generateRssFeed_generates_valid_xml(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $this->mockOpenJobs([]);

        $result = $this->service->generateRssFeed($this->testTenantId);

        $this->assertStringContainsString('<?xml version="1.0"', $result);
        $this->assertStringContainsString('<rss version="2.0"', $result);
        $this->assertStringContainsString('<channel>', $result);
        $this->assertStringContainsString('</rss>', $result);
    }

    public function test_generateRssFeed_includes_job_items(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $job = $this->makeJobModel(10, 'Senior Developer', 'Great opportunity', 'Engineering');
        $this->mockOpenJobs([$job]);

        $result = $this->service->generateRssFeed($this->testTenantId);

        $this->assertStringContainsString('<item>', $result);
        $this->assertStringContainsString('Senior Developer', $result);
        $this->assertStringContainsString('/jobs/10', $result);
    }

    public function test_generateRssFeed_includes_category(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $job = $this->makeJobModel(10, 'Dev', 'Description', 'Engineering');
        $this->mockOpenJobs([$job]);

        $result = $this->service->generateRssFeed($this->testTenantId);
        $this->assertStringContainsString('<category>Engineering</category>', $result);
    }

    public function test_generateRssFeed_escapes_xml_special_characters(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $job = $this->makeJobModel(10, 'Dev & Designer <Lead>', 'Description');
        $this->mockOpenJobs([$job]);

        $result = $this->service->generateRssFeed($this->testTenantId);
        $this->assertStringNotContainsString('<Lead>', $result);
        $this->assertStringContainsString('&amp;', $result);
    }

    public function test_generateRssFeed_caches_result_for_900_seconds(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once()->withArgs(function ($key, $value, $ttl) {
            return $key === "job_feed_rss_{$this->testTenantId}" && $ttl === 900;
        });

        $this->mockOpenJobs([]);

        $this->service->generateRssFeed($this->testTenantId);
    }

    // ── generateJsonFeed ────────────────────────────────────────

    public function test_generateJsonFeed_returns_cached_result(): void
    {
        $cached = ['jobs' => [['title' => 'Cached']]];
        Cache::shouldReceive('get')->with("job_feed_json_{$this->testTenantId}")->andReturn($cached);

        $result = $this->service->generateJsonFeed($this->testTenantId);
        $this->assertSame($cached, $result);
    }

    public function test_generateJsonFeed_returns_jobs_array(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $this->mockOpenJobs([]);

        $result = $this->service->generateJsonFeed($this->testTenantId);
        $this->assertArrayHasKey('jobs', $result);
        $this->assertIsArray($result['jobs']);
    }

    public function test_generateJsonFeed_uses_schema_org_format(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $job = $this->makeJobModel(10, 'Backend Dev', 'Build APIs', null, 'full_time');
        $this->mockOpenJobs([$job]);

        $result = $this->service->generateJsonFeed($this->testTenantId);
        $posting = $result['jobs'][0];

        $this->assertSame('https://schema.org', $posting['@context']);
        $this->assertSame('JobPosting', $posting['@type']);
        $this->assertSame('Backend Dev', $posting['title']);
        $this->assertArrayHasKey('hiringOrganization', $posting);
    }

    public function test_generateJsonFeed_maps_full_time_commitment(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $job = $this->makeJobModel(10, 'Dev', 'Desc', null, 'full_time');
        $this->mockOpenJobs([$job]);

        $result = $this->service->generateJsonFeed($this->testTenantId);
        $this->assertSame('FULL_TIME', $result['jobs'][0]['employmentType']);
    }

    public function test_generateJsonFeed_maps_part_time_commitment(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $job = $this->makeJobModel(10, 'Dev', 'Desc', null, 'part_time');
        $this->mockOpenJobs([$job]);

        $result = $this->service->generateJsonFeed($this->testTenantId);
        $this->assertSame('PART_TIME', $result['jobs'][0]['employmentType']);
    }

    public function test_generateJsonFeed_maps_one_off_to_temporary(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $job = $this->makeJobModel(10, 'Dev', 'Desc', null, 'one_off');
        $this->mockOpenJobs([$job]);

        $result = $this->service->generateJsonFeed($this->testTenantId);
        $this->assertSame('TEMPORARY', $result['jobs'][0]['employmentType']);
    }

    public function test_generateJsonFeed_sets_telecommute_for_remote(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $job = $this->makeJobModel(10, 'Dev', 'Desc');
        $job->is_remote = true;
        $this->mockOpenJobs([$job]);

        $result = $this->service->generateJsonFeed($this->testTenantId);
        $this->assertSame('TELECOMMUTE', $result['jobs'][0]['jobLocationType']);
    }

    public function test_generateJsonFeed_includes_salary_range(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $job = $this->makeJobModel(10, 'Dev', 'Desc');
        $job->salary_min = 50000;
        $job->salary_max = 80000;
        $job->salary_currency = 'USD';
        $job->salary_type = 'annual';
        $this->mockOpenJobs([$job]);

        $result = $this->service->generateJsonFeed($this->testTenantId);
        $salary = $result['jobs'][0]['baseSalary'];

        $this->assertSame('MonetaryAmount', $salary['@type']);
        $this->assertSame('USD', $salary['currency']);
        $this->assertSame(50000, $salary['value']['minValue']);
        $this->assertSame(80000, $salary['value']['maxValue']);
    }

    public function test_generateJsonFeed_includes_hourly_salary(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $job = $this->makeJobModel(10, 'Dev', 'Desc');
        $job->salary_min = 25;
        $job->salary_max = null;
        $job->salary_currency = 'EUR';
        $job->salary_type = 'hourly';
        $this->mockOpenJobs([$job]);

        $result = $this->service->generateJsonFeed($this->testTenantId);
        $salary = $result['jobs'][0]['baseSalary'];

        $this->assertSame('HOUR', $salary['value']['unitText']);
    }

    public function test_generateJsonFeed_includes_deadline_as_valid_through(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once();

        $job = $this->makeJobModel(10, 'Dev', 'Desc');
        $job->deadline = now()->addDays(30);
        $this->mockOpenJobs([$job]);

        $result = $this->service->generateJsonFeed($this->testTenantId);
        $this->assertArrayHasKey('validThrough', $result['jobs'][0]);
    }

    public function test_generateJsonFeed_caches_result(): void
    {
        Cache::shouldReceive('get')->andReturn(null);
        Cache::shouldReceive('put')->once()->withArgs(function ($key, $value, $ttl) {
            return $key === "job_feed_json_{$this->testTenantId}" && $ttl === 900;
        });

        $this->mockOpenJobs([]);

        $this->service->generateJsonFeed($this->testTenantId);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function makeJobModel(
        int $id,
        string $title,
        ?string $description = null,
        ?string $category = null,
        ?string $commitment = 'flexible'
    ): object {
        return (object) [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'category' => $category,
            'commitment' => $commitment,
            'location' => 'Dublin',
            'is_remote' => false,
            'organization_name' => 'Test Org',
            'salary_min' => null,
            'salary_max' => null,
            'salary_currency' => 'EUR',
            'salary_type' => 'annual',
            'deadline' => null,
            'skills_required' => null,
            'created_at' => now(),
        ];
    }

    private function mockOpenJobs(array $jobs): void
    {
        $builder = Mockery::mock();
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereNull')->andReturnSelf();
        $builder->shouldReceive('orWhere')->andReturnSelf();
        $builder->shouldReceive('leftJoin')->andReturnSelf();
        $builder->shouldReceive('select')->andReturnSelf();
        $builder->shouldReceive('orderByDesc')->andReturnSelf();
        $builder->shouldReceive('limit')->with(100)->andReturnSelf();
        $builder->shouldReceive('get')->andReturn(collect($jobs));

        $mock = Mockery::mock('alias:' . JobVacancy::class);
        $mock->shouldReceive('where')->andReturn($builder);
    }
}
