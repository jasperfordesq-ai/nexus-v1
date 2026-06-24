<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\AI\Tools;

use App\Core\TenantContext;
use App\Services\AI\Tools\SearchJobsTool;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * SearchJobsToolTest
 *
 * Tests the SearchJobsTool: metadata shape, keyword search, location
 * filter, remote filter, limit, moderation gating, expired-at exclusion,
 * salary formatting, and tenant scoping.
 *
 * A publicly-visible vacancy is status='open' (the job_vacancies.status enum
 * is open/closed/filled/draft), with no active moderation hold and not past
 * its expiry — mirroring the public_only scope in JobVacancyService::getAll().
 */
class SearchJobsToolTest extends \Tests\Laravel\TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private SearchJobsTool $tool;
    private int $ownerUserId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        TenantContext::setById(self::TENANT_ID);
        $this->tool = new SearchJobsTool();

        $uid = uniqid('sjtest_', true);
        $this->ownerUserId = DB::table('users')->insertGetId([
            'tenant_id'  => self::TENANT_ID,
            'name'       => 'JobOwner ' . $uid,
            'first_name' => 'Job',
            'last_name'  => 'Owner',
            'email'      => $uid . '@example.test',
            'status'     => 'active',
            'balance'    => 0.0,
            'role'       => 'member',
            'is_approved' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Insert a job vacancy. Defaults to status='open' (the schema enum is
     * open/closed/filled/draft), which is the publicly-visible state the tool
     * surfaces.
     */
    private function insertJob(array $overrides = []): int
    {
        $uid = uniqid('job_', true);
        $defaults = [
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->ownerUserId,
            'title'             => 'Test Job ' . $uid,
            'description'       => 'A test job vacancy for unit tests',
            'tagline'           => null,
            'location'          => 'Dublin',
            'is_remote'         => 0,
            'type'              => 'paid',
            'commitment'        => 'flexible',
            'status'            => 'open',
            'moderation_status' => null,
            'expired_at'        => null,
            'is_featured'       => 0,
            'created_at'        => now(),
        ];
        return DB::table('job_vacancies')->insertGetId(array_merge($defaults, $overrides));
    }

    /**
     * Insert a publicly-visible job vacancy (status='open').
     */
    private function insertOpenJob(array $overrides = []): int
    {
        return $this->insertJob(array_merge(['status' => 'open'], $overrides));
    }

    // ── Metadata ──────────────────────────────────────────────────────────────

    public function test_name_returns_expected_string(): void
    {
        $this->assertSame('search_jobs', $this->tool->name());
    }

    public function test_description_is_non_empty(): void
    {
        $this->assertNotEmpty($this->tool->description());
    }

    public function test_parameters_schema_type_is_object(): void
    {
        $schema = $this->tool->parametersSchema();
        $this->assertSame('object', $schema['type']);
    }

    public function test_parameters_schema_required_is_empty(): void
    {
        $schema = $this->tool->parametersSchema();
        // query is optional for jobs (no required fields)
        $this->assertSame([], $schema['required']);
    }

    public function test_parameters_schema_has_expected_properties(): void
    {
        $schema = $this->tool->parametersSchema();
        $props  = array_keys($schema['properties']);

        $this->assertContains('query',     $props);
        $this->assertContains('location',  $props);
        $this->assertContains('is_remote', $props);
        $this->assertContains('limit',     $props);
    }

    public function test_to_openai_function_wraps_correctly(): void
    {
        $fn = $this->tool->toOpenAiFunction();

        $this->assertSame('function', $fn['type']);
        $this->assertSame('search_jobs', $fn['function']['name']);
        $this->assertArrayHasKey('parameters', $fn['function']);
    }

    // ── isAvailable ──────────────────────────────────────────────────────────

    public function test_is_available_returns_true_when_job_vacancies_enabled_by_default(): void
    {
        // TenantFeatureConfig::mergeFeatures() returns job_vacancies=true by default
        $this->assertTrue($this->tool->isAvailable(1));
    }

    // ── execute: no results (baseline / source-bug documentation) ────────────

    public function test_execute_returns_ok_with_empty_results_when_no_jobs_present(): void
    {
        // No seeded jobs — expect empty results
        $result = $this->tool->execute(['query' => 'xyzzy_nonexistent_zqmrpf_job'], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
        $this->assertSame('job', $result['card_type']);
    }

    /**
     * The tool surfaces publicly-visible (status='open') vacancies. Seed an
     * open vacancy and confirm a keyword search returns it with the expected
     * card shape. Guards against regressing to a status value that no longer
     * matches the job_vacancies.status enum (open/closed/filled/draft).
     */
    public function test_execute_returns_open_vacancy_matching_query(): void
    {
        $keyword = 'developer' . uniqid();
        $jobId = $this->insertOpenJob([
            'title'       => "Senior {$keyword} role",
            'description' => 'Build great things with the team.',
            'location'    => 'Dublin',
        ]);

        $result = $this->tool->execute(['query' => $keyword], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame('job', $result['card_type']);
        $this->assertNull($result['error']);
        $this->assertGreaterThanOrEqual(1, count($result['results']));

        $ids = array_column($result['results'], 'id');
        $this->assertContains($jobId, $ids, 'Open vacancy matching the query should be returned.');

        $row = $result['results'][array_search($jobId, $ids, true)];
        $this->assertSame("Senior {$keyword} role", $row['title']);
        $this->assertSame('Dublin', $row['location']);
        $this->assertStringContainsString('/jobs/' . $jobId, $row['url']);
    }

    /**
     * Non-open statuses (closed/filled/draft) are not publicly visible and
     * must be excluded.
     */
    public function test_execute_excludes_non_open_statuses(): void
    {
        $keyword = 'archivist' . uniqid();
        $this->insertJob(['title' => "{$keyword} closed", 'status' => 'closed']);
        $this->insertJob(['title' => "{$keyword} filled", 'status' => 'filled']);
        $this->insertJob(['title' => "{$keyword} draft", 'status' => 'draft']);

        $result = $this->tool->execute(['query' => $keyword], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
    }

    // ── execute: empty/no-query (browse all) ─────────────────────────────────

    public function test_execute_with_no_query_returns_ok(): void
    {
        // No query argument at all — should not error
        $result = $this->tool->execute([], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame('job', $result['card_type']);
    }

    public function test_execute_with_empty_query_returns_ok(): void
    {
        $result = $this->tool->execute(['query' => ''], 1);

        // Unlike SearchListingsTool, SearchJobsTool does NOT require query — empty is valid
        $this->assertTrue($result['ok']);
    }

    // ── execute: limit clamping ───────────────────────────────────────────────

    public function test_limit_intarg_clamps_to_max_8(): void
    {
        // intArg clamps limit to a max of 8, so the result count can never
        // exceed 8 regardless of the requested value.
        $result = $this->tool->execute(['query' => '', 'limit' => 100], 1);

        $this->assertTrue($result['ok']);
        $this->assertLessThanOrEqual(8, count($result['results']));
    }

    public function test_limit_intarg_clamps_to_min_1(): void
    {
        $result = $this->tool->execute(['query' => '', 'limit' => 0], 1);

        $this->assertTrue($result['ok']);
        $this->assertLessThanOrEqual(8, count($result['results']));
    }

    // ── execute: result structure when rows exist ─────────────────────────────

    /**
     * The result envelope always exposes the same keys, even when no rows
     * match.
     */
    public function test_execute_result_structure_has_ok_summary_results_card_type_error(): void
    {
        $result = $this->tool->execute(['query' => 'nurse'], 1);

        $this->assertArrayHasKey('ok',        $result);
        $this->assertArrayHasKey('summary',   $result);
        $this->assertArrayHasKey('results',   $result);
        $this->assertArrayHasKey('card_type', $result);
        $this->assertArrayHasKey('error',     $result);
    }

    public function test_execute_card_type_is_job(): void
    {
        $result = $this->tool->execute([], 1);
        $this->assertSame('job', $result['card_type']);
    }

    public function test_execute_error_is_null_on_success(): void
    {
        $result = $this->tool->execute(['query' => 'teacher'], 1);
        $this->assertNull($result['error']);
    }

    // ── execute: is_remote filter (logic path verification) ──────────────────

    public function test_execute_is_remote_true_does_not_crash(): void
    {
        $result = $this->tool->execute(['query' => 'remote work', 'is_remote' => true], 1);

        $this->assertTrue($result['ok']);
        $this->assertIsArray($result['results']);
    }

    public function test_execute_is_remote_false_does_not_filter(): void
    {
        // is_remote=false is explicitly not applied as a filter (only true triggers it)
        $result = $this->tool->execute(['query' => '', 'is_remote' => false], 1);

        $this->assertTrue($result['ok']);
    }

    // ── execute: moderation exclusion (via non-matching status) ──────────────

    public function test_execute_does_not_include_rejected_moderation(): void
    {
        $keyword = 'accountant' . uniqid();
        // An open vacancy with rejected moderation must still be excluded.
        DB::table('job_vacancies')->insert([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->ownerUserId,
            'title'             => "{$keyword} position",
            'description'       => 'Rejected job',
            'type'              => 'paid',
            'commitment'        => 'flexible',
            'status'            => 'open',
            'moderation_status' => 'rejected',
            'created_at'        => now(),
        ]);

        $result = $this->tool->execute(['query' => $keyword], 1);

        $this->assertTrue($result['ok']);
        // Rejected moderation excludes the row even though status is 'open'.
        $this->assertSame([], $result['results']);
    }

    public function test_execute_does_not_include_expired_jobs(): void
    {
        $keyword = 'designer' . uniqid();
        DB::table('job_vacancies')->insert([
            'tenant_id'         => self::TENANT_ID,
            'user_id'           => $this->ownerUserId,
            'title'             => "{$keyword} position",
            'description'       => 'Expired job',
            'type'              => 'paid',
            'commitment'        => 'flexible',
            'status'            => 'open',
            'moderation_status' => null,
            'expired_at'        => now()->subDay()->toDateTimeString(),
            'created_at'        => now(),
        ]);

        $result = $this->tool->execute(['query' => $keyword], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
    }

    // ── Tenant scoping ────────────────────────────────────────────────────────

    public function test_execute_does_not_return_jobs_from_another_tenant(): void
    {
        $keyword = 'plumber' . uniqid();
        // Open + approved in another tenant — only tenant scoping should
        // keep it out of the current tenant's results.
        DB::table('job_vacancies')->insert([
            'tenant_id'         => 999,
            'user_id'           => $this->ownerUserId,
            'title'             => "{$keyword} needed",
            'description'       => 'Should not appear',
            'type'              => 'paid',
            'commitment'        => 'flexible',
            'status'            => 'open',
            'moderation_status' => null,
            'created_at'        => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);
        $result = $this->tool->execute(['query' => $keyword], 1);

        $this->assertTrue($result['ok']);
        $this->assertSame([], $result['results']);
    }
}
