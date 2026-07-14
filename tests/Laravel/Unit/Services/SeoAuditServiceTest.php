<?php

// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\SeoAuditService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Unit tests for SeoAuditService — real SEO audit implementation.
 */
class SeoAuditServiceTest extends TestCase
{
    use DatabaseTransactions;

    private SeoAuditService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SeoAuditService();
    }

    // =========================================================================
    // runAudit()
    // =========================================================================

    public function test_runAudit_returns_complete_structure(): void
    {
        $results = $this->service->runAudit($this->testTenantId);

        $this->assertArrayHasKey('checks', $results);
        $this->assertArrayHasKey('score', $results);
        $this->assertArrayHasKey('max_score', $results);
        $this->assertArrayHasKey('grade', $results);
        $this->assertArrayHasKey('run_at', $results);
    }

    public function test_runAudit_returns_all_check_types(): void
    {
        $results = $this->service->runAudit($this->testTenantId);

        $keys = array_column($results['checks'], 'code');

        $this->assertContains('tenant_metadata', $keys);
        $this->assertContains('seo_settings', $keys);
        $this->assertContains('blog_meta', $keys);
        $this->assertContains('page_meta', $keys);
        $this->assertContains('kb_meta', $keys);
        $this->assertContains('redirect_health', $keys);
        $this->assertContains('duplicate_titles', $keys);
        $this->assertContains('sitemap_coverage', $keys);
        $this->assertContains('canonical_urls', $keys);
        $this->assertContains('open_graph', $keys);
        $this->assertContains('content_quality', $keys);
    }

    public function test_runAudit_each_check_has_required_fields(): void
    {
        $results = $this->service->runAudit($this->testTenantId);

        foreach ($results['checks'] as $check) {
            $this->assertArrayHasKey('code', $check);
            $this->assertArrayHasKey('params', $check);
            $this->assertArrayHasKey('status', $check);
            $this->assertArrayHasKey('issues', $check);
            $this->assertArrayHasKey('issue_count', $check);
            $this->assertArrayHasKey('points', $check);
            $this->assertArrayHasKey('max_points', $check);
            $this->assertArrayNotHasKey('name', $check);
            $this->assertArrayNotHasKey('description', $check);
            $this->assertArrayNotHasKey('details', $check);
            $this->assertContains($check['status'], ['pass', 'warning', 'fail']);
            $this->assertIsArray($check['params']);
            $this->assertIsArray($check['issues']);
            $this->assertLessThanOrEqual($check['max_points'], $check['points']);

            foreach ($check['issues'] as $issue) {
                $this->assertIsArray($issue);
                $this->assertArrayHasKey('code', $issue);
                $this->assertArrayHasKey('params', $issue);
                $this->assertIsString($issue['code']);
                $this->assertIsArray($issue['params']);
            }
        }
    }

    public function test_runAudit_grade_is_valid(): void
    {
        $results = $this->service->runAudit($this->testTenantId);
        $this->assertContains($results['grade'], ['A', 'B', 'C', 'D', 'F', 'N/A']);
    }

    public function test_runAudit_score_does_not_exceed_max(): void
    {
        $results = $this->service->runAudit($this->testTenantId);
        $this->assertLessThanOrEqual($results['max_score'], $results['score']);
    }

    public function test_runAudit_persists_results(): void
    {
        $this->service->runAudit($this->testTenantId);

        $row = DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM seo_audits WHERE tenant_id = ?",
            [$this->testTenantId]
        );

        $this->assertGreaterThan(0, (int) $row->cnt);
    }

    // =========================================================================
    // getLatestAudit()
    // =========================================================================

    public function test_getLatestAudit_returns_null_when_none(): void
    {
        // Clear any previous audits
        DB::delete("DELETE FROM seo_audits WHERE tenant_id = ?", [$this->testTenantId]);

        $result = $this->service->getLatestAudit($this->testTenantId);
        $this->assertNull($result);
    }

    public function test_getLatestAudit_returns_most_recent(): void
    {
        $this->service->runAudit($this->testTenantId);

        $result = $this->service->getLatestAudit($this->testTenantId);
        $this->assertNotNull($result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('score', $result);
    }

    public function test_getLatestAudit_removes_legacy_english_display_copy(): void
    {
        DB::delete('DELETE FROM seo_audits WHERE tenant_id = ?', [$this->testTenantId]);
        DB::table('seo_audits')->insert([
            'tenant_id' => $this->testTenantId,
            'url' => '',
            'results' => json_encode([
                'checks' => [[
                    'key' => 'tenant_metadata',
                    'name' => 'Legacy English Name',
                    'description' => 'Legacy English Description',
                    'status' => 'warning',
                    'issues' => ['Legacy English Issue'],
                    'issue_count' => 1,
                    'points' => 5,
                    'max_points' => 10,
                ]],
                'score' => 5,
                'max_score' => 10,
                'grade' => 'F',
                'run_at' => '2026-01-01T00:00:00+00:00',
            ]),
            'created_at' => now(),
        ]);

        $result = $this->service->getLatestAudit($this->testTenantId);

        $this->assertNotNull($result);
        $check = $result['checks'][0];
        $this->assertSame('tenant_metadata', $check['code']);
        $this->assertArrayNotHasKey('name', $check);
        $this->assertArrayNotHasKey('description', $check);
        $this->assertSame('legacy_result_requires_rerun', $check['issues'][0]['code']);
        $this->assertSame([], $check['issues'][0]['params']);
    }

    // =========================================================================
    // Specific audit checks
    // =========================================================================

    public function test_redirect_loop_detection(): void
    {
        // Create a redirect loop
        DB::table('seo_redirects')->insert([
            ['tenant_id' => $this->testTenantId, 'source_url' => '/loop-a', 'destination_url' => '/loop-b', 'created_at' => now()],
            ['tenant_id' => $this->testTenantId, 'source_url' => '/loop-b', 'destination_url' => '/loop-a', 'created_at' => now()],
        ]);

        $results = $this->service->runAudit($this->testTenantId);
        $redirectCheck = collect($results['checks'])->firstWhere('code', 'redirect_health');

        $this->assertNotEmpty($redirectCheck['issues']);
    }

    public function test_redirect_chain_detection(): void
    {
        // Create a redirect chain: A → B → C
        DB::table('seo_redirects')->insert([
            ['tenant_id' => $this->testTenantId, 'source_url' => '/chain-a', 'destination_url' => '/chain-b', 'created_at' => now()],
            ['tenant_id' => $this->testTenantId, 'source_url' => '/chain-b', 'destination_url' => '/chain-c', 'created_at' => now()],
        ]);

        $results = $this->service->runAudit($this->testTenantId);
        $redirectCheck = collect($results['checks'])->firstWhere('code', 'redirect_health');

        $hasChainIssue = collect($redirectCheck['issues'])->contains(
            fn (array $issue): bool => $issue['code'] === 'redirect_chain'
                && $issue['params']['from'] === '/chain-a'
                && $issue['params']['via'] === '/chain-b'
                && $issue['params']['destination'] === '/chain-c'
        );
        $this->assertTrue($hasChainIssue);
    }
}
