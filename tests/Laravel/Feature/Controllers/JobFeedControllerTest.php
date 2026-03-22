<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\JobVacancy;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Laravel\TestCase;

/**
 * Feature tests for JobFeedController — public RSS and JSON job feeds.
 *
 * Both feed endpoints are public (no auth required) but tenant-scoped.
 * They are designed for consumption by Google Jobs, Indeed, and other
 * job aggregators.
 */
class JobFeedControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    private function createOpenVacancy(array $overrides = []): JobVacancy
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        return JobVacancy::factory()->forTenant($this->testTenantId)->create(array_merge([
            'user_id' => $owner->id,
            'status' => 'open',
            'title' => 'Community Coordinator',
            'description' => 'Help coordinate community activities.',
            'deadline' => now()->addMonth(),
        ], $overrides));
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Clear feed caches before each test so we get fresh data
        Cache::forget("job_feed_rss_{$this->testTenantId}");
        Cache::forget("job_feed_json_{$this->testTenantId}");
    }

    // =====================================================================
    // RSS FEED — GET /v2/jobs/feed.xml
    // =====================================================================

    public function test_rss_feed_does_not_require_auth(): void
    {
        // Public endpoint — should NOT return 401
        $response = $this->getJson(
            '/api/v2/jobs/feed.xml',
            $this->withTenantHeader()
        );

        $this->assertNotEquals(401, $response->status());
    }

    public function test_rss_feed_returns_200(): void
    {
        $response = $this->get(
            '/api/v2/jobs/feed.xml',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);
    }

    public function test_rss_feed_returns_xml_content_type(): void
    {
        $response = $this->get(
            '/api/v2/jobs/feed.xml',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public function test_rss_feed_contains_valid_xml_structure(): void
    {
        $this->createOpenVacancy(['title' => 'XML Test Job']);

        $response = $this->get(
            '/api/v2/jobs/feed.xml',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringContainsString('<?xml version="1.0"', $content);
        $this->assertStringContainsString('<rss version="2.0"', $content);
        $this->assertStringContainsString('<channel>', $content);
    }

    public function test_rss_feed_includes_open_vacancies(): void
    {
        $this->createOpenVacancy(['title' => 'RSS Vacancy Title']);

        $response = $this->get(
            '/api/v2/jobs/feed.xml',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringContainsString('RSS Vacancy Title', $content);
        $this->assertStringContainsString('<item>', $content);
    }

    public function test_rss_feed_excludes_closed_vacancies(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        JobVacancy::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $owner->id,
            'status' => 'closed',
            'title' => 'Closed Job Should Not Appear',
        ]);

        $response = $this->get(
            '/api/v2/jobs/feed.xml',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringNotContainsString('Closed Job Should Not Appear', $content);
    }

    public function test_rss_feed_has_cache_control_header(): void
    {
        $response = $this->get(
            '/api/v2/jobs/feed.xml',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);
        $response->assertHeader('Cache-Control');
    }

    public function test_rss_feed_is_tenant_scoped(): void
    {
        // Create a vacancy in tenant 2
        $this->createOpenVacancy(['title' => 'Tenant 2 Job']);

        // Request with tenant 2 header — should include the job
        $response = $this->get(
            '/api/v2/jobs/feed.xml',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString('Tenant 2 Job', $content);
    }

    // =====================================================================
    // JSON FEED — GET /v2/jobs/feed.json
    // =====================================================================

    public function test_json_feed_does_not_require_auth(): void
    {
        // Public endpoint — should NOT return 401
        $response = $this->getJson(
            '/api/v2/jobs/feed.json',
            $this->withTenantHeader()
        );

        $this->assertNotEquals(401, $response->status());
    }

    public function test_json_feed_returns_200(): void
    {
        $response = $this->getJson(
            '/api/v2/jobs/feed.json',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);
    }

    public function test_json_feed_returns_json_content_type(): void
    {
        $response = $this->getJson(
            '/api/v2/jobs/feed.json',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    public function test_json_feed_has_jobs_array(): void
    {
        $response = $this->getJson(
            '/api/v2/jobs/feed.json',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);
        $response->assertJsonStructure(['jobs']);
    }

    public function test_json_feed_includes_open_vacancies(): void
    {
        $this->createOpenVacancy(['title' => 'JSON Feed Test Vacancy']);

        $response = $this->getJson(
            '/api/v2/jobs/feed.json',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertNotEmpty($data['jobs']);

        $titles = array_column($data['jobs'], 'title');
        $this->assertContains('JSON Feed Test Vacancy', $titles);
    }

    public function test_json_feed_uses_schema_org_format(): void
    {
        $this->createOpenVacancy(['title' => 'Schema.org Test']);

        $response = $this->getJson(
            '/api/v2/jobs/feed.json',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertNotEmpty($data['jobs']);

        $firstJob = $data['jobs'][0];
        $this->assertEquals('https://schema.org', $firstJob['@context']);
        $this->assertEquals('JobPosting', $firstJob['@type']);
        $this->assertArrayHasKey('title', $firstJob);
        $this->assertArrayHasKey('description', $firstJob);
        $this->assertArrayHasKey('datePosted', $firstJob);
        $this->assertArrayHasKey('url', $firstJob);
        $this->assertArrayHasKey('hiringOrganization', $firstJob);
    }

    public function test_json_feed_excludes_closed_vacancies(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        JobVacancy::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $owner->id,
            'status' => 'closed',
            'title' => 'Closed JSON Feed Job',
        ]);

        $response = $this->getJson(
            '/api/v2/jobs/feed.json',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);

        $data = $response->json();
        $titles = array_column($data['jobs'] ?? [], 'title');
        $this->assertNotContains('Closed JSON Feed Job', $titles);
    }

    public function test_json_feed_includes_employment_type(): void
    {
        $this->createOpenVacancy([
            'title' => 'Full Time Position',
            'commitment' => 'full_time',
        ]);

        $response = $this->getJson(
            '/api/v2/jobs/feed.json',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);

        $data = $response->json();
        $job = collect($data['jobs'])->firstWhere('title', 'Full Time Position');

        if ($job) {
            $this->assertEquals('FULL_TIME', $job['employmentType']);
        }
    }

    public function test_json_feed_includes_salary_when_present(): void
    {
        $this->createOpenVacancy([
            'title' => 'Paid Role With Salary',
            'salary_min' => 40000,
            'salary_max' => 60000,
            'salary_type' => 'annual',
            'salary_currency' => 'EUR',
        ]);

        $response = $this->getJson(
            '/api/v2/jobs/feed.json',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);

        $data = $response->json();
        $job = collect($data['jobs'])->firstWhere('title', 'Paid Role With Salary');

        if ($job) {
            $this->assertArrayHasKey('baseSalary', $job);
            $this->assertEquals('MonetaryAmount', $job['baseSalary']['@type']);
        }
    }

    public function test_json_feed_marks_remote_jobs(): void
    {
        $this->createOpenVacancy([
            'title' => 'Remote Position',
            'is_remote' => true,
        ]);

        $response = $this->getJson(
            '/api/v2/jobs/feed.json',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);

        $data = $response->json();
        $job = collect($data['jobs'])->firstWhere('title', 'Remote Position');

        if ($job) {
            $this->assertEquals('TELECOMMUTE', $job['jobLocationType']);
        }
    }

    public function test_json_feed_has_cache_control_header(): void
    {
        $response = $this->getJson(
            '/api/v2/jobs/feed.json',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);
        $response->assertHeader('Cache-Control');
    }

    public function test_json_feed_is_tenant_scoped(): void
    {
        $this->createOpenVacancy(['title' => 'Tenant Scoped JSON Job']);

        $response = $this->getJson(
            '/api/v2/jobs/feed.json',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);

        $data = $response->json();
        $titles = array_column($data['jobs'] ?? [], 'title');
        $this->assertContains('Tenant Scoped JSON Job', $titles);
    }

    public function test_json_feed_excludes_expired_vacancies(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active']);

        // Create a vacancy with a past deadline
        JobVacancy::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $owner->id,
            'status' => 'open',
            'title' => 'Expired Deadline Job',
            'deadline' => now()->subDay(),
        ]);

        $response = $this->getJson(
            '/api/v2/jobs/feed.json',
            $this->withTenantHeader()
        );

        $response->assertStatus(200);

        $data = $response->json();
        $titles = array_column($data['jobs'] ?? [], 'title');
        $this->assertNotContains('Expired Deadline Job', $titles);
    }
}
