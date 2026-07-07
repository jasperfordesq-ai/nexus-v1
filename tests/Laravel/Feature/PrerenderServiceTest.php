<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature;

use App\Console\Commands\PrerenderProcessQueue;
use App\Models\User;
use App\Services\PrerenderService;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

/**
 * Unit-level coverage for PrerenderService and the artisan command's parsing
 * helpers. Uses a per-test temp directory as the snapshot cache so we don't
 * depend on the production volume.
 */
class PrerenderServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ?string $tmpCache = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('prerender_jobs')) {
            $this->markTestSkipped('prerender_jobs table not present.');
        }
        // Several tests assert the exact FIFO/priority order of freshly enqueued
        // jobs (claimNextJob returns the oldest eligible row). A stray queued row
        // committed by an earlier non-transactional run would always be claimed
        // first and break those assertions. Clear the table so each test starts
        // from a known-empty queue. The delete runs inside the DatabaseTransactions
        // wrapper, so it rolls back after the test and never mutates CI long-term.
        DB::table('prerender_jobs')->delete();
        $this->tmpCache = sys_get_temp_dir() . '/nexus-prerender-test-' . uniqid();
        mkdir($this->tmpCache, 0777, true);
        putenv('PRERENDER_CACHE_PATH=' . $this->tmpCache);
        putenv('PRERENDER_EVENT_LOG=' . $this->tmpCache . '/events.jsonl');
        putenv('PRERENDER_ASSETS_MANIFEST=' . $this->tmpCache . '/.assets-manifest.json');
    }

    protected function tearDown(): void
    {
        if ($this->tmpCache !== null) {
            $this->rrmdir($this->tmpCache);
        }
        putenv('PRERENDER_CACHE_PATH');
        putenv('PRERENDER_EVENT_LOG');
        putenv('PRERENDER_ASSETS_MANIFEST');
        parent::tearDown();
    }

    public function test_inventory_returns_empty_when_cache_empty(): void
    {
        $service = new PrerenderService();
        $this->assertSame([], $service->inventory(null, true));
    }

    public function test_inventory_lists_snapshot_with_age_staleness(): void
    {
        $this->writeSnapshot('example.com/about/index.html', '<html><body><h1>Hi</h1></body></html>');
        $service = new PrerenderService();
        $rows = $service->inventory(null, true);
        $this->assertCount(1, $rows);
        $this->assertSame('example.com', $rows[0]['host']);
        $this->assertSame('/about', $rows[0]['route']);
        $this->assertSame('fresh', $rows[0]['age_staleness']);
    }

    public function test_inspect_extracts_structured_seo_data(): void
    {
        $html = <<<'HTML'
<!doctype html><html><head>
<title>Page Title</title>
<meta name="description" content="A description">
<link rel="canonical" href="https://example.com/about">
<meta property="og:title" content="OG">
<script type="application/ld+json">{"@context":"https://schema.org","@type":"WebPage"}</script>
</head><body><h1>Headline</h1></body></html>
HTML;
        $this->writeSnapshot('example.com/about/index.html', $html);
        $service = new PrerenderService();
        $result = $service->inspect('example.com/about/index.html');

        $this->assertNotNull($result);
        $this->assertSame('Page Title', $result['title']);
        $this->assertSame('A description', $result['meta_description']);
        $this->assertSame('https://example.com/about', $result['canonical']);
        $this->assertSame('OG', $result['og_tags']['og:title']);
        $this->assertTrue($result['flags']['has_jsonld']);
        $this->assertTrue($result['flags']['jsonld_valid']);
        $this->assertSame(['WebPage'], $result['json_ld']['blocks'][0]['types']);
        $this->assertFalse($result['flags']['multiple_h1']);
    }

    public function test_inspect_flags_invalid_json_ld(): void
    {
        $html = '<html><head><script type="application/ld+json">{not json}</script></head><body></body></html>';
        $this->writeSnapshot('example.com/bad/index.html', $html);
        $service = new PrerenderService();
        $result = $service->inspect('example.com/bad/index.html');
        $this->assertFalse($result['flags']['jsonld_valid']);
    }

    public function test_inspect_rejects_path_traversal(): void
    {
        $this->writeSnapshot('a/index.html', '<html></html>');
        $service = new PrerenderService();
        $this->assertNull($service->inspect('../etc/passwd'));
        $this->assertNull($service->inspect('a/../b/index.html'));
        $this->assertNull($service->inspect('a/index.txt'));
    }

    public function test_inventory_detects_dead_asset_refs(): void
    {
        // Manifest declares only one valid asset
        file_put_contents(
            $this->tmpCache . '/.assets-manifest.json',
            json_encode(['/assets/index-VALID.js'])
        );
        $html = '<html><head><script src="/assets/index-DEAD.js"></script></head></html>';
        $this->writeSnapshot('example.com/index.html', $html);

        $service = new PrerenderService();
        $rows = $service->inventory(null, true);
        $this->assertCount(1, $rows);
        $this->assertContains('/assets/index-DEAD.js', $rows[0]['asset_issues']);
        $this->assertSame('stale', $rows[0]['staleness']); // asset issues bump to stale
    }

    public function test_claim_next_job_is_atomic(): void
    {
        $service = new PrerenderService();
        $id1 = $service->enqueueJob(null, '/about', false, false, null);
        $id2 = $service->enqueueJob(null, '/faq', false, false, null);

        $first = $service->claimNextJob('worker-1');
        $this->assertNotNull($first);
        $this->assertSame($id1, (int) $first['id']);
        $this->assertSame('claimed', DB::table('prerender_jobs')->where('id', $id1)->value('status'));

        $second = $service->claimNextJob('worker-2');
        $this->assertNotNull($second);
        $this->assertSame($id2, (int) $second['id']);

        $third = $service->claimNextJob('worker-3');
        $this->assertNull($third);
    }

    public function test_enqueue_dedupes_identical_queued_jobs(): void
    {
        $service = new PrerenderService();
        $a = $service->enqueueJob(null, '/about', true, false, 1);
        $b = $service->enqueueJob(null, '/about', true, false, 1);
        $this->assertSame($a, $b);
    }

    public function test_finalise_job_records_counters_and_status(): void
    {
        $service = new PrerenderService();
        $id = $service->enqueueJob(null, null, true, false, 1);
        $service->claimNextJob('w');
        $service->markRunning($id);
        $service->finaliseJob($id, 'succeeded', 10, 9, 1, 0, 42, 'log tail here');

        $row = DB::table('prerender_jobs')->where('id', $id)->first();
        $this->assertSame('succeeded', $row->status);
        $this->assertSame(10, (int) $row->planned_count);
        $this->assertSame(9, (int) $row->rendered_count);
        $this->assertSame(1, (int) $row->invalid_count);
        $this->assertSame(0, (int) $row->exit_code);
        $this->assertSame(42, (int) $row->duration_s);
        $this->assertSame('log tail here', $row->log_excerpt);
    }

    public function test_parse_counters_extracts_from_real_log(): void
    {
        $log = "[INFO] Planning cache refresh...\n"
             . "[INFO] Planned 27 page(s) to refresh out of 35 candidate page(s)\n"
             . "[OK]   13 pre-rendered page(s) injected into nexus-react-prod\n"
             . "[WARN] 2 rendered page(s) discarded because their asset references were stale\n";
        [$planned, $rendered, $invalid] = PrerenderProcessQueue::parseCounters($log);
        $this->assertSame(27, $planned);
        $this->assertSame(13, $rendered);
        $this->assertSame(2, $invalid);
    }

    public function test_prometheus_metrics_emit_required_series(): void
    {
        $service = new PrerenderService();
        $service->enqueueJob(null, null, true, false, 1);
        $body = $service->prometheusMetrics();

        $this->assertStringContainsString('# TYPE nexus_prerender_snapshots_total gauge', $body);
        $this->assertStringContainsString('# TYPE nexus_prerender_jobs_total counter', $body);
        $this->assertMatchesRegularExpression('/nexus_prerender_jobs_total\{status="queued"\} 1/', $body);
        $this->assertStringContainsString('nexus_prerender_coverage_ratio', $body);
    }

    public function test_tail_events_returns_newest_first(): void
    {
        $log = $this->tmpCache . '/events.jsonl';
        file_put_contents($log, json_encode(['ts' => '1', 'event' => 'a']) . "\n");
        file_put_contents($log, json_encode(['ts' => '2', 'event' => 'b']) . "\n", FILE_APPEND);
        file_put_contents($log, json_encode(['ts' => '3', 'event' => 'c']) . "\n", FILE_APPEND);

        $service = new PrerenderService();
        $events = $service->tailEvents(10);
        $this->assertSame(['c', 'b', 'a'], array_column($events, 'event'));
    }

    public function test_failures_registry_parses_tsv(): void
    {
        $now = time();
        file_put_contents(
            $this->tmpCache . '/.failures.tsv',
            "{$now}\texample.com/about/index.html\n" . ($now - 60) . "\texample.com/blog/index.html\n"
        );
        $service = new PrerenderService();
        $rows = $service->readFailures();
        $this->assertCount(2, $rows);
        $this->assertSame('example.com/about/index.html', $rows[0]['cache_path']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Round 5 additions: purgePattern, ttlForRoute, seoScore, invalidateRoutes,
    // status sidecar reading.
    // ─────────────────────────────────────────────────────────────────────────

    public function test_purge_pattern_matches_glob_single_segment(): void
    {
        $this->writeSnapshot('example.com/blog/post-1/index.html', '<html><body>a</body></html>');
        $this->writeSnapshot('example.com/blog/post-2/index.html', '<html><body>b</body></html>');
        $this->writeSnapshot('example.com/about/index.html', '<html><body>c</body></html>');

        $svc = new PrerenderService();
        $result = $svc->purgePattern('/blog/*', null, dryRun: true);

        $this->assertCount(2, $result['deleted']);
        $this->assertTrue($result['dry_run']);
        // /about should be untouched
        $this->assertTrue(in_array('example.com/blog/post-1/index.html', $result['deleted'], true));
        $this->assertTrue(in_array('example.com/blog/post-2/index.html', $result['deleted'], true));
    }

    public function test_purge_pattern_actually_deletes_files(): void
    {
        $this->writeSnapshot('example.com/blog/post-1/index.html', '<html><body>a</body></html>');
        $svc = new PrerenderService();
        $result = $svc->purgePattern('/blog/*', null, dryRun: false);
        $this->assertCount(1, $result['deleted']);
        $this->assertFileDoesNotExist($this->tmpCache . '/example.com/blog/post-1/index.html');
    }

    public function test_purge_pattern_recursive_double_star(): void
    {
        $this->writeSnapshot('example.com/blog/post-1/index.html', '<html><body>a</body></html>');
        $this->writeSnapshot('example.com/blog/category/foo/index.html', '<html><body>b</body></html>');
        $svc = new PrerenderService();
        $result = $svc->purgePattern('/blog/**', null, dryRun: true);
        $this->assertCount(2, $result['deleted']);
    }

    public function test_purge_pattern_scopes_to_shared_host_tenant_prefix(): void
    {
        $slugA = 'purge-a-' . uniqid();
        $slugB = 'purge-b-' . uniqid();
        DB::table('tenants')->insert([
            [
                'name' => 'Purge A',
                'slug' => $slugA,
                'domain' => null,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Purge B',
                'slug' => $slugB,
                'domain' => null,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ]);
        $host = parse_url((string) env('FRONTEND_URL', 'https://app.project-nexus.ie'), PHP_URL_HOST)
            ?: 'app.project-nexus.ie';
        $this->writeSnapshot("{$host}/{$slugA}/blog/post/index.html", '<html><body>a</body></html>');
        $this->writeSnapshot("{$host}/{$slugB}/blog/post/index.html", '<html><body>b</body></html>');

        $svc = new PrerenderService();
        $result = $svc->purgePattern('/blog/*', $slugA, dryRun: true);
        $this->assertCount(1, $result['deleted']);
        $this->assertSame("{$host}/{$slugA}/blog/post/index.html", $result['deleted'][0]);
    }

    public function test_inventory_tenant_filter_scopes_to_shared_host_tenant_prefix(): void
    {
        $slugA = 'inventory-a-' . uniqid();
        $slugB = 'inventory-b-' . uniqid();
        DB::table('tenants')->insert([
            [
                'name' => 'Inventory A',
                'slug' => $slugA,
                'domain' => null,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Inventory B',
                'slug' => $slugB,
                'domain' => null,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ]);
        $host = parse_url((string) env('FRONTEND_URL', 'https://app.project-nexus.ie'), PHP_URL_HOST)
            ?: 'app.project-nexus.ie';
        $this->writeSnapshot("{$host}/{$slugA}/about/index.html", '<html><body>a</body></html>');
        $this->writeSnapshot("{$host}/{$slugB}/about/index.html", '<html><body>b</body></html>');

        $svc = new PrerenderService();
        $rows = $svc->inventory($slugA, false);

        $this->assertCount(1, $rows);
        $this->assertSame($slugA, $rows[0]['tenant_slug']);
        $this->assertSame('/about', $rows[0]['tenant_route']);
        $this->assertSame("/{$slugA}/about", $rows[0]['route']);
    }

    public function test_ttl_for_route_picks_most_specific_pattern(): void
    {
        $svc = new PrerenderService();
        // From config/prerender.php defaults.
        $this->assertSame(6 * 3600, $svc->ttlForRoute('/'));
        $this->assertSame(12 * 3600, $svc->ttlForRoute('/blog'));
        $this->assertSame(7 * 24 * 3600, $svc->ttlForRoute('/blog/foo-post'));
        $this->assertSame(30 * 24 * 3600, $svc->ttlForRoute('/about'));
    }

    public function test_ttl_falls_back_to_default_for_unknown_route(): void
    {
        $svc = new PrerenderService();
        // Default in config/prerender.php is 7 days.
        $this->assertSame(7 * 24 * 3600, $svc->ttlForRoute('/some-route-not-in-config'));
    }

    public function test_seo_score_high_grade_for_complete_page(): void
    {
        $svc = new PrerenderService();
        $insp = [
            'title' => 'A reasonable title between ten and seventy characters',
            'meta_description' => str_repeat('x', 100),
            'canonical' => 'https://example.com/foo',
            'og_tags' => [
                'og:title' => 'Foo', 'og:description' => 'Bar', 'og:image' => 'https://example.com/og.png',
            ],
            'h1_texts' => ['Foo'],
            'json_ld' => ['blocks_count' => 1, 'all_valid' => true],
            'asset_issues' => [],
            'flags' => ['has_noscript' => true],
            'preview' => str_repeat('Lorem ipsum dolor sit amet. ', 60),
        ];
        $score = $svc->seoScore($insp);
        $this->assertGreaterThanOrEqual(90, $score['score']);
        $this->assertSame('A', $score['grade']);
        $this->assertEmpty($score['issues']);
    }

    public function test_seo_score_low_grade_when_critical_tags_missing(): void
    {
        $svc = new PrerenderService();
        $insp = [
            'title' => '',
            'meta_description' => null,
            'canonical' => null,
            'og_tags' => [],
            'h1_texts' => [],
            'json_ld' => ['blocks_count' => 0, 'all_valid' => true],
            'asset_issues' => ['a.js', 'b.js'],
            'flags' => ['has_noscript' => false],
            'preview' => '',
        ];
        $score = $svc->seoScore($insp);
        $this->assertLessThan(50, $score['score']);
        $this->assertSame('F', $score['grade']);
        $this->assertContains('Missing <title>', $score['issues']);
    }

    public function test_status_sidecar_is_surfaced_in_inspect(): void
    {
        $this->writeSnapshot('example.com/dead-link/index.html', '<html><body>not found</body></html>');
        file_put_contents($this->tmpCache . '/example.com/dead-link/_status', '404');

        $svc = new PrerenderService();
        $insp = $svc->inspect('example.com/dead-link/index.html');
        $this->assertNotNull($insp);
        $this->assertSame(404, $insp['http_status']);
    }

    public function test_status_sidecar_defaults_to_200_when_absent(): void
    {
        $this->writeSnapshot('example.com/ok/index.html', '<html><body>fine</body></html>');
        $svc = new PrerenderService();
        $insp = $svc->inspect('example.com/ok/index.html');
        $this->assertNotNull($insp);
        $this->assertSame(200, $insp['http_status']);
    }

    public function test_priority_promotion_on_duplicate_enqueue(): void
    {
        $svc = new PrerenderService();
        $id1 = $svc->enqueueJob(null, '/about', false, false, null, PrerenderService::PRIORITY_LOW);
        // Same args + higher priority → should promote the existing row.
        $id2 = $svc->enqueueJob(null, '/about', false, false, null, PrerenderService::PRIORITY_HIGH);
        $this->assertSame($id1, $id2);
        $row = DB::table('prerender_jobs')->where('id', $id1)->first();
        $this->assertSame(PrerenderService::PRIORITY_HIGH, (int) $row->priority);
    }

    public function test_claim_next_respects_priority(): void
    {
        $svc = new PrerenderService();
        // Older low-priority job comes in first.
        $oldLow = $svc->enqueueJob(null, '/about', false, false, null, PrerenderService::PRIORITY_LOW);
        // Newer high-priority job comes second.
        $newHigh = $svc->enqueueJob(null, '/faq', false, false, null, PrerenderService::PRIORITY_HIGH);

        $claimed = $svc->claimNextJob('test-host:0');
        $this->assertNotNull($claimed);
        $this->assertSame($newHigh, (int) $claimed['id'], 'high-priority should win even when queued later');
        $this->assertNotSame($oldLow, (int) $claimed['id']);
    }

    public function test_crawler_analytics_aggregates_jsonl_log(): void
    {
        $log = $this->tmpCache . '/.bot-access.jsonl';
        $now = date('c');
        $lines = [
            json_encode(['ts' => $now, 'host' => 'a.com', 'uri' => '/foo', 'status' => 200, 'crawler' => 'googlebot', 'verified' => '1', 'ua' => 'Googlebot', 'ip' => '66.249.66.1']),
            json_encode(['ts' => $now, 'host' => 'a.com', 'uri' => '/foo', 'status' => 200, 'crawler' => 'googlebot', 'verified' => '',  'ua' => 'Googlebot', 'ip' => '1.2.3.4']),
            json_encode(['ts' => $now, 'host' => 'a.com', 'uri' => '/bar', 'status' => 404, 'crawler' => 'bingbot',   'verified' => '1', 'ua' => 'Bingbot',  'ip' => '40.77.0.1']),
        ];
        file_put_contents($log, implode("\n", $lines) . "\n");

        $svc = new PrerenderService();
        $analytics = $svc->crawlerAnalytics(date('c', time() - 3600), 100);

        $this->assertSame(3, $analytics['total_hits']);
        $this->assertSame(2, $analytics['verified_hits']);
        $this->assertSame(['googlebot' => 1], $analytics['spoofed_by_crawler']);
        $this->assertSame(['googlebot' => 2, 'bingbot' => 1], $analytics['hits_by_crawler']);
    }

    // ─────── Round 2/3 tests ──────────────────────────────────────────────

    public function test_circuit_breaker_trips_after_threshold(): void
    {
        if (! Schema::hasTable('prerender_audit_log')) {
            $this->markTestSkipped('prerender_audit_log table not present.');
        }
        $service = new PrerenderService();
        $service->resetBreaker();
        $this->assertNull($service->breakerTrippedUntil(), 'breaker should start closed');

        // Pump 5 failed finalises inside the window — trips the breaker.
        $routes = ['/about', '/faq', '/contact', '/help', '/explore'];
        for ($i = 0; $i < PrerenderService::BREAKER_FAILURE_THRESHOLD; $i++) {
            $id = $service->enqueueJob(null, $routes[$i], false, false, 1);
            $service->claimNextJob('w');
            $service->markRunning($id);
            $service->finaliseJob($id, 'failed', 0, 0, 0, 1, 5, null, 'boom');
        }

        $this->assertNotNull($service->breakerTrippedUntil(), 'breaker should trip');

        // claimNextJob must return null while tripped, even with work queued.
        $service->resetBreaker();
        $service->tripBreaker(900);
        $service->enqueueJob(null, '/privacy', false, false, 1);
        $this->assertNull(
            $service->claimNextJob('w-after-trip'),
            'claimNextJob must return null while breaker is tripped'
        );
        $service->resetBreaker();
    }

    public function test_per_tenant_concurrency_cap_blocks_second_job(): void
    {
        [$tenantA, $tenantB] = $this->seedCmsTenantPair('concurrency-a', 'concurrency-b');
        $service = new PrerenderService();
        $service->resetBreaker();

        // First job for tenant 99 gets claimed and stays running.
        $id1 = $service->enqueueJob($tenantA, '/about', false, false, 1);
        $first = $service->claimNextJob('w');
        $this->assertNotNull($first);
        $service->markRunning((int) $first['id']);

        // Second job for the SAME tenant: queued, but claimNextJob skips it.
        $id2 = $service->enqueueJob($tenantA, '/faq', false, false, 1);

        // Third job for a DIFFERENT tenant: should be claimable past the cap.
        $id3 = $service->enqueueJob($tenantB, '/contact', false, false, 1);

        $second = $service->claimNextJob('w');
        $this->assertNotNull($second, 'a different tenant must still be claimable');
        $this->assertSame($id3, (int) $second['id'], 'tenant B should be picked over busy tenant A');

        // After finishing tenant 99's first job, tenant 99 should be claimable again.
        $service->finaliseJob($id1, 'succeeded', 1, 1, 0, 0, 1, null);
        $third = $service->claimNextJob('w');
        $this->assertNotNull($third);
        $this->assertSame($id2, (int) $third['id']);
    }

    public function test_route_validation_rejects_shell_metacharacters(): void
    {
        $service = new PrerenderService();
        $this->expectException(\InvalidArgumentException::class);
        $service->enqueueJob(null, '/legit,/about;rm -rf /', false, false, 1);
    }

    public function test_audit_redacts_secret_keys(): void
    {
        if (! Schema::hasTable('prerender_audit_log')) {
            $this->markTestSkipped('prerender_audit_log table not present.');
        }
        $service = new PrerenderService();
        $service->audit(
            'test_secret', 1, null, null, 'ok',
            ['routes' => ['/x'], 'api_token' => 'sk-LIVE-DEADBEEF', 'nested' => ['password' => 'hunter2']]
        );

        $row = DB::table('prerender_audit_log')->orderByDesc('id')->first();
        $this->assertNotNull($row);
        $decoded = json_decode($row->details, true);
        $this->assertSame('[REDACTED]', $decoded['api_token']);
        $this->assertSame('[REDACTED]', $decoded['nested']['password']);
        // Non-secret fields preserved.
        $this->assertSame(['/x'], $decoded['routes']);
    }

    public function test_health_returns_red_when_breaker_tripped(): void
    {
        $service = new PrerenderService();
        $service->resetBreaker();
        $service->tripBreaker(600);
        $h = $service->health();
        $this->assertSame('red', $h['status']);
        $names = array_column($h['checks'], 'name');
        $this->assertContains('circuit_breaker', $names);
        $service->resetBreaker();
    }

    public function test_health_reports_stuck_jobs(): void
    {
        $service = new PrerenderService();
        $service->resetBreaker();
        // Insert a row that LOOKS stuck (claimed >30min ago).
        DB::table('prerender_jobs')->insert([
            'tenant_id'  => null,
            'routes'     => '/stuck',
            'status'     => 'running',
            'priority'   => 5,
            'claimed_at' => date('Y-m-d H:i:s', time() - 3600),
            'started_at' => date('Y-m-d H:i:s', time() - 3600),
            'queued_at'  => date('Y-m-d H:i:s', time() - 3700),
        ]);

        $h = $service->health();
        $stuck = collect($h['checks'])->firstWhere('name', 'stuck_jobs');
        $this->assertNotNull($stuck);
        $this->assertSame('yellow', $stuck['status']);
    }

    public function test_verify_integrity_matches_sidecar(): void
    {
        $service = new PrerenderService();
        $abs = $this->tmpCache . '/example.com/foo/index.html';
        @mkdir(dirname($abs), 0777, true);
        $html = '<html><body>hello</body></html>';
        file_put_contents($abs, $html);
        file_put_contents($abs . '.sha256', hash('sha256', $html) . '  ' . strlen($html));

        $r = $service->verifyIntegrity($abs);
        $this->assertSame('ok', $r['status']);
        $this->assertSame(hash('sha256', $html), $r['expected']);
    }

    public function test_verify_integrity_detects_mismatch(): void
    {
        $service = new PrerenderService();
        $abs = $this->tmpCache . '/example.com/bar/index.html';
        @mkdir(dirname($abs), 0777, true);
        file_put_contents($abs, '<html>original</html>');
        file_put_contents($abs . '.sha256', str_repeat('f', 64));

        $r = $service->verifyIntegrity($abs);
        $this->assertSame('mismatch', $r['status']);
        $this->assertNotEquals($r['expected'], $r['actual']);
    }

    public function test_verify_integrity_missing_sidecar(): void
    {
        $service = new PrerenderService();
        $abs = $this->tmpCache . '/example.com/baz/index.html';
        @mkdir(dirname($abs), 0777, true);
        file_put_contents($abs, '<html/>');
        // No .sha256 sidecar.
        $r = $service->verifyIntegrity($abs);
        $this->assertSame('missing', $r['status']);
    }

    public function test_ttl_inspector_picks_most_specific_pattern(): void
    {
        // Stub config.
        config(['prerender.ttl' => [
            'default'  => 7 * 86400,
            '/blog'    => 3600,
            '/blog/*'  => 1800,
            '/blog/**' => 900,
        ]]);
        $service = new PrerenderService();

        $exact = $service->describeTtlForRoute('/blog');
        $this->assertSame('/blog', $exact['matched_pattern']);
        $this->assertSame(3600, $exact['ttl_seconds']);

        $oneLevel = $service->describeTtlForRoute('/blog/hello');
        $this->assertSame('/blog/*', $oneLevel['matched_pattern']);
        $this->assertSame(1800, $oneLevel['ttl_seconds']);

        $deep = $service->describeTtlForRoute('/blog/tags/foo/bar');
        $this->assertSame('/blog/**', $deep['matched_pattern']);
        $this->assertSame(900, $deep['ttl_seconds']);

        $miss = $service->describeTtlForRoute('/nope');
        $this->assertNull($miss['matched_pattern']);
        $this->assertSame('default', $miss['source']);
        $this->assertSame(7 * 86400, $miss['ttl_seconds']);
    }

    public function test_safe_cache_path_accepts_route_special_chars(): void
    {
        // Regression: P1 fix widened the regex. A route like /events/social-(2024)
        // should produce an inspectable cache_path.
        $service = new PrerenderService();
        $html = '<html><head><title>X</title></head></html>';
        $this->writeSnapshot('example.com/events/social-(2024)/index.html', $html);
        $rows = $service->inventory(null, false);
        $this->assertNotEmpty($rows);
        $rel = $rows[0]['cache_path'];
        $this->assertNotNull($service->inspect($rel), 'inspect must accept routes containing ( ) chars');
    }

    public function test_burst_backpressure_coalesces_to_tenant_wide(): void
    {
        // Seed a tenant row so resolveTenantHost works.
        $tenantId = (int) DB::table('tenants')->insertGetId([
            'name'      => 'Burst Test',
            'slug'      => 'burst-test-' . uniqid(),
            'domain'    => 'burst-test.example',
            'is_active' => 1,
            'created_at'=> date('Y-m-d H:i:s'),
            'updated_at'=> date('Y-m-d H:i:s'),
        ]);
        // Reset the burst counter just in case a prior test populated it.
        \Illuminate\Support\Facades\Cache::forget("prerender:burst:tenant:{$tenantId}");

        // First 50 invocations should keep their per-route precision.
        $service = new PrerenderService();
        for ($i = 0; $i < 50; $i++) {
            $slug = "burst-page-{$i}";
            DB::table('pages')->insert([
                'tenant_id' => $tenantId,
                'title' => "Burst page {$i}",
                'slug' => $slug,
                'content' => '<p>Burst page</p>',
                'is_published' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $abs = $this->tmpCache . "/burst-test.example/page/{$slug}/index.html";
            @mkdir(dirname($abs), 0777, true);
            file_put_contents($abs, '<html/>');
            $service->invalidateRoutes($tenantId, ["/page/{$slug}"]);
        }

        // The 51st should flip to a tenant-wide row (routes=null).
        DB::table('pages')->insert([
            'tenant_id' => $tenantId,
            'title' => 'Burst page 51',
            'slug' => 'burst-page-51',
            'content' => '<p>Burst page</p>',
            'is_published' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $abs = $this->tmpCache . '/burst-test.example/page/burst-page-51/index.html';
        @mkdir(dirname($abs), 0777, true);
        file_put_contents($abs, '<html/>');
        $service->invalidateRoutes($tenantId, ['/page/burst-page-51']);

        $tenantWide = DB::table('prerender_jobs')
            ->where('tenant_id', $tenantId)
            ->whereNull('routes')
            ->where('status', 'queued')
            ->exists();
        $this->assertTrue($tenantWide, 'burst above threshold must enqueue a tenant-wide row');
    }

    public function test_enqueue_rejects_global_cms_page_route(): void
    {
        $service = new PrerenderService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Route requires tenant scope');

        $service->enqueueJob(null, '/page/tenant-only-page', false, false, null);
    }

    public function test_enqueue_rejects_feature_gated_route_without_tenant_scope(): void
    {
        $service = new PrerenderService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Route requires tenant scope');

        $service->enqueueJob(null, '/jobs', false, false, null);
    }

    public function test_enqueue_rejects_private_or_tool_routes_inside_dynamic_prefixes(): void
    {
        [$tenantId] = $this->seedCmsTenantPair('private-route-owner', 'private-route-other');
        $service = new PrerenderService();

        foreach (['/listings/create', '/profile/settings'] as $route) {
            try {
                $service->enqueueJob($tenantId, $route, false, false, null);
                $this->fail("Route {$route} should not be accepted for prerender.");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('Route is not available for tenant', $e->getMessage());
            }
        }
    }

    public function test_tenant_scoped_cms_page_route_must_belong_to_that_tenant(): void
    {
        [$ownerId, $otherId, , , $pageSlug] = $this->seedCmsTenantPair('enqueue-owner', 'enqueue-other');

        $service = new PrerenderService();
        $jobId = $service->enqueueJob($ownerId, "/page/{$pageSlug}", false, false, null);
        $this->assertGreaterThan(0, $jobId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Route is not available for tenant');

        $service->enqueueJob($otherId, "/page/{$pageSlug}", false, false, null);
    }

    public function test_tenant_scoped_blog_route_must_belong_to_that_tenant(): void
    {
        [$ownerId, $otherId] = $this->seedCmsTenantPair('blog-owner', 'blog-other');
        $slug = $this->seedBlogPost($ownerId);

        $service = new PrerenderService();
        $jobId = $service->enqueueJob($ownerId, "/blog/{$slug}", false, false, null);
        $this->assertGreaterThan(0, $jobId);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Route is not available for tenant');

        $service->enqueueJob($otherId, "/blog/{$slug}", false, false, null);
    }

    public function test_invalidate_routes_ignores_wrong_tenant_blog_route(): void
    {
        [$ownerId, $otherId, , $otherSlug] = $this->seedCmsTenantPair('invalidate-blog-owner', 'invalidate-blog-other');
        $slug = $this->seedBlogPost($ownerId);
        $host = $this->frontendHost();
        $wrongPath = $this->writeSnapshot("{$host}/{$otherSlug}/blog/{$slug}/index.html", '<html><body>wrong tenant</body></html>');

        $service = new PrerenderService();
        $deleted = $service->invalidateRoutes($otherId, ["/blog/{$slug}"]);

        $this->assertSame(1, $deleted);
        $this->assertFileDoesNotExist($wrongPath);
        $this->assertFalse(
            DB::table('prerender_jobs')->where('tenant_id', $otherId)->where('routes', "/blog/{$slug}")->exists(),
            'Wrong-tenant invalidation may purge a stale snapshot, but must not enqueue a recache job.'
        );
    }

    public function test_invalidate_routes_enqueues_prerenderable_missing_snapshot(): void
    {
        [$tenantId] = $this->seedCmsTenantPair('missing-snapshot-owner', 'missing-snapshot-other');

        $service = new PrerenderService();
        $deleted = $service->invalidateRoutes($tenantId, ['/about']);

        $this->assertSame(0, $deleted);
        $this->assertTrue(
            DB::table('prerender_jobs')->where('tenant_id', $tenantId)->where('routes', '/about')->exists(),
            'A valid route without an existing snapshot should still enqueue recache work.'
        );
    }

    public function test_invalidate_routes_deletes_snapshot_bundle_for_hidden_dynamic_route(): void
    {
        [$tenantId, , $slug] = $this->seedCmsTenantPair('hidden-vol-owner', 'hidden-vol-other');
        $host = $this->frontendHost();
        $indexPath = $this->writeSnapshot("{$host}/{$slug}/volunteering/opportunities/987654/index.html", '<html><body>old</body></html>');
        file_put_contents(dirname($indexPath) . '/index.html.sha256', hash('sha256', 'old'));
        file_put_contents(dirname($indexPath) . '/_status', '200');
        file_put_contents(dirname($indexPath) . '/index.md', '# old');

        $service = new PrerenderService();
        $deleted = $service->invalidateRoutes($tenantId, ['/volunteering/opportunities/987654']);

        $this->assertSame(1, $deleted);
        $this->assertFileDoesNotExist($indexPath);
        $this->assertFileDoesNotExist(dirname($indexPath) . '/index.html.sha256');
        $this->assertFileDoesNotExist(dirname($indexPath) . '/_status');
        $this->assertFileDoesNotExist(dirname($indexPath) . '/index.md');
        $this->assertFalse(
            DB::table('prerender_jobs')->where('tenant_id', $tenantId)->where('routes', '/volunteering/opportunities/987654')->exists(),
            'Hidden/deleted dynamic routes should be purged without recache.'
        );
    }

    public function test_tenant_owned_organisation_route_uses_volunteer_organizations(): void
    {
        [$tenantId] = $this->seedCmsTenantPair('vol-org-owner', 'vol-org-other');
        [$orgId] = $this->seedVolunteerContent($tenantId, organizationStatus: 'active');
        [$pendingOrgId] = $this->seedVolunteerContent($tenantId, organizationStatus: 'pending');

        $service = new PrerenderService();

        $this->assertTrue($service->tenantOwnedRouteExistsForTenant($tenantId, "/organisations/{$orgId}"));
        $this->assertFalse($service->tenantOwnedRouteExistsForTenant($tenantId, "/organisations/{$pendingOrgId}"));
    }

    public function test_tenant_owned_volunteer_opportunity_requires_visible_opportunity_and_org(): void
    {
        [$tenantId] = $this->seedCmsTenantPair('vol-opp-owner', 'vol-opp-other');
        [, $activeOpportunityId] = $this->seedVolunteerContent($tenantId, organizationStatus: 'approved', opportunityStatus: 'active');
        [, $pendingOrgOpportunityId] = $this->seedVolunteerContent($tenantId, organizationStatus: 'pending', opportunityStatus: 'active');
        [, $closedOpportunityId] = $this->seedVolunteerContent($tenantId, organizationStatus: 'approved', opportunityStatus: 'closed');

        $service = new PrerenderService();

        $this->assertTrue($service->tenantOwnedRouteExistsForTenant($tenantId, "/volunteering/opportunities/{$activeOpportunityId}"));
        $this->assertFalse($service->tenantOwnedRouteExistsForTenant($tenantId, "/volunteering/opportunities/{$pendingOrgOpportunityId}"));
        $this->assertFalse($service->tenantOwnedRouteExistsForTenant($tenantId, "/volunteering/opportunities/{$closedOpportunityId}"));
    }

    public function test_inventory_marks_volunteering_snapshot_content_stale(): void
    {
        [$tenantId, , $slug] = $this->seedCmsTenantPair('vol-stale-owner', 'vol-stale-other');
        DB::table('tenants')->where('id', $tenantId)->update(['updated_at' => now()->subDays(2)]);
        $this->seedVolunteerContent($tenantId, organizationStatus: 'active', opportunityStatus: 'active');
        $host = $this->frontendHost();
        $indexPath = $this->writeSnapshot("{$host}/{$slug}/volunteering/index.html", '<html><body>old volunteering</body></html>');
        touch($indexPath, time() - 7200);

        $service = new PrerenderService();
        $rows = $service->inventory($slug, true);

        $row = $rows[0] ?? null;
        $this->assertNotNull($row);
        $this->assertSame('/volunteering', $row['tenant_route']);
        $this->assertTrue($row['content_stale']);
        $this->assertStringContainsString('/volunteering content updated', (string) $row['content_stale_reason']);
    }

    public function test_route_planner_lists_cms_page_only_for_owning_tenant(): void
    {
        [, , $ownerSlug, $otherSlug, $pageSlug] = $this->seedCmsTenantPair('plan-owner', 'plan-other');

        Artisan::call('prerender:plan-routes', [
            '--tenant' => $ownerSlug,
            '--include-static' => '0',
            '--include-sitemap' => '1',
        ]);
        $ownerPlan = json_decode(Artisan::output(), true);

        Artisan::call('prerender:plan-routes', [
            '--tenant' => $otherSlug,
            '--include-static' => '0',
            '--include-sitemap' => '1',
        ]);
        $otherPlan = json_decode(Artisan::output(), true);

        $ownerRoutes = $ownerPlan['tenants'][0]['routes'] ?? [];
        $otherRoutes = $otherPlan['tenants'][0]['routes'] ?? [];

        $this->assertContains("/page/{$pageSlug}", $ownerRoutes);
        $this->assertNotContains("/page/{$pageSlug}", $otherRoutes);
    }

    public function test_auto_recache_does_not_enqueue_wrong_tenant_cms_snapshot(): void
    {
        [$ownerId, $otherId, $ownerSlug, $otherSlug, $pageSlug] = $this->seedCmsTenantPair('recache-owner', 'recache-other');
        $host = $this->frontendHost();
        $old = time() - 90 * 86400;

        $ownerPath = $this->writeSnapshot("{$host}/{$ownerSlug}/page/{$pageSlug}/index.html", '<html><body>owner</body></html>');
        $otherPath = $this->writeSnapshot("{$host}/{$otherSlug}/page/{$pageSlug}/index.html", '<html><body>wrong tenant</body></html>');
        touch($ownerPath, $old);
        touch($otherPath, $old);

        Artisan::call('prerender:auto-recache', [
            '--min-stale-seconds' => '0',
            '--include-ttl' => '1',
            '--include-content' => '0',
            '--max-tenants' => '10',
            '--max-routes' => '10',
        ]);

        $ownerRoutes = (string) DB::table('prerender_jobs')->where('tenant_id', $ownerId)->value('routes');
        $otherRoutes = (string) DB::table('prerender_jobs')->where('tenant_id', $otherId)->value('routes');

        $this->assertStringContainsString("/page/{$pageSlug}", $ownerRoutes);
        $this->assertStringNotContainsString("/page/{$pageSlug}", $otherRoutes);
    }

    public function test_drift_detector_does_not_enqueue_wrong_tenant_cms_route(): void
    {
        [$ownerId, $otherId, , , $pageSlug] = $this->seedCmsTenantPair('drift-owner', 'drift-other');

        $fakeSitemap = new class($ownerId, $pageSlug) extends SitemapService {
            public function __construct(private readonly int $ownerId, private readonly string $pageSlug) {}

            public function generateForTenant(int $tenantId, ?string $baseUrl = null): string
            {
                $base = rtrim($baseUrl ?: 'https://app.project-nexus.ie/test', '/');
                $routes = $tenantId === $this->ownerId ? ["/page/{$this->pageSlug}"] : [];
                $xml = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
                foreach ($routes as $route) {
                    $xml .= '<url><loc>' . htmlspecialchars($base . $route, ENT_XML1) . '</loc><lastmod>' . date('c') . '</lastmod></url>';
                }
                return $xml . '</urlset>';
            }
        };
        app()->instance(SitemapService::class, $fakeSitemap);

        Artisan::call('prerender:detect-drift', [
            '--include-missing' => '1',
            '--min-drift-seconds' => '0',
            '--max-tenants' => '10',
            '--max-routes' => '10',
        ]);

        $ownerRoutes = (string) DB::table('prerender_jobs')->where('tenant_id', $ownerId)->value('routes');
        $otherRoutes = (string) DB::table('prerender_jobs')->where('tenant_id', $otherId)->value('routes');

        $this->assertStringContainsString("/page/{$pageSlug}", $ownerRoutes);
        $this->assertStringNotContainsString("/page/{$pageSlug}", $otherRoutes);
    }

    public function test_purge_unexpected_removes_cross_tenant_cms_page_snapshot(): void
    {
        [, , $ownerSlug, $otherSlug, $pageSlug] = $this->seedCmsTenantPair('cms-owner', 'cms-other');
        $host = $this->frontendHost();
        $this->writeSnapshot("{$host}/{$ownerSlug}/page/{$pageSlug}/index.html", '<html><body>owner</body></html>');
        $this->writeSnapshot("{$host}/{$otherSlug}/page/{$pageSlug}/index.html", '<html><body>wrong tenant</body></html>');

        $service = new PrerenderService();
        $dryRun = $service->purgeUnexpectedSnapshots(true);

        $this->assertContains("/{$otherSlug}/page/{$pageSlug}", $dryRun['by_tenant'][$otherSlug] ?? []);
        $this->assertNotContains("/{$ownerSlug}/page/{$pageSlug}", $dryRun['by_tenant'][$ownerSlug] ?? []);

        $result = $service->purgeUnexpectedSnapshots(false);

        $this->assertSame(1, $result['deleted_total']);
        $this->assertFileExists($this->tmpCache . "/{$host}/{$ownerSlug}/page/{$pageSlug}/index.html");
        $this->assertFileDoesNotExist($this->tmpCache . "/{$host}/{$otherSlug}/page/{$pageSlug}/index.html");
    }

    public function test_purge_unexpected_removes_cross_tenant_blog_snapshot(): void
    {
        [$ownerId, , $ownerSlug, $otherSlug] = $this->seedCmsTenantPair('blog-clean-owner', 'blog-clean-other');
        $slug = $this->seedBlogPost($ownerId);
        $host = $this->frontendHost();
        $this->writeSnapshot("{$host}/{$ownerSlug}/blog/{$slug}/index.html", '<html><body>owner</body></html>');
        $this->writeSnapshot("{$host}/{$otherSlug}/blog/{$slug}/index.html", '<html><body>wrong tenant</body></html>');

        $service = new PrerenderService();
        $dryRun = $service->purgeUnexpectedSnapshots(true);

        $this->assertContains("/{$otherSlug}/blog/{$slug}", $dryRun['by_tenant'][$otherSlug] ?? []);
        $this->assertNotContains("/{$ownerSlug}/blog/{$slug}", $dryRun['by_tenant'][$ownerSlug] ?? []);

        $result = $service->purgeUnexpectedSnapshots(false);

        $this->assertSame(1, $result['deleted_total']);
        $this->assertFileExists($this->tmpCache . "/{$host}/{$ownerSlug}/blog/{$slug}/index.html");
        $this->assertFileDoesNotExist($this->tmpCache . "/{$host}/{$otherSlug}/blog/{$slug}/index.html");
    }

    public function test_tenant_safety_report_flags_unexpected_cross_tenant_cms_page(): void
    {
        [, , $ownerSlug, $otherSlug, $pageSlug] = $this->seedCmsTenantPair('safety-owner', 'safety-other');
        $host = $this->frontendHost();
        $this->writeSnapshot("{$host}/{$ownerSlug}/page/{$pageSlug}/index.html", '<html><body>owner</body></html>');
        $this->writeSnapshot("{$host}/{$otherSlug}/page/{$pageSlug}/index.html", '<html><body>wrong tenant</body></html>');

        $service = new PrerenderService();
        $owner = $service->tenantSafetyReport($ownerSlug, ["/page/{$pageSlug}"]);
        $other = $service->tenantSafetyReport($otherSlug, []);

        $this->assertNotNull($owner);
        $this->assertNotNull($other);
        $this->assertSame(0, $owner['counts']['unexpected']);
        $this->assertSame(1, $other['counts']['unexpected']);
        $this->assertContains("/page/{$pageSlug}", $other['unexpected_routes']);
    }

    /**
     * @return array{0:int,1:int,2:string,3:string,4:string}
     */
    private function seedBlogPost(int $tenantId): string
    {
        $now = date('Y-m-d H:i:s');
        $authorId = (int) DB::table('users')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Prerender Blog Author',
            'email' => 'prerender-blog-' . uniqid() . '@example.test',
            'is_approved' => 1,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $slug = 'tenant-owned-post-' . uniqid();
        DB::table('posts')->insert([
            'tenant_id' => $tenantId,
            'author_id' => $authorId,
            'title' => 'Tenant owned post',
            'slug' => $slug,
            'excerpt' => 'Only one tenant owns this post.',
            'content' => 'Only one tenant owns this post.',
            'status' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $slug;
    }

    private function seedCmsTenantPair(string $ownerPrefix, string $otherPrefix): array
    {
        $ownerSlug = $ownerPrefix . '-' . uniqid();
        $otherSlug = $otherPrefix . '-' . uniqid();
        $pageSlug = 'tenant-owned-page-' . uniqid();
        $now = date('Y-m-d H:i:s');

        $ownerId = (int) DB::table('tenants')->insertGetId([
            'name' => 'CMS Owner',
            'slug' => $ownerSlug,
            'domain' => null,
            'is_active' => 1,
            'features' => '{}',
            'configuration' => '{"modules":{}}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $otherId = (int) DB::table('tenants')->insertGetId([
            'name' => 'CMS Other',
            'slug' => $otherSlug,
            'domain' => null,
            'is_active' => 1,
            'features' => '{}',
            'configuration' => '{"modules":{}}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('pages')->insert([
            'tenant_id' => $ownerId,
            'title' => 'Tenant owned page',
            'slug' => $pageSlug,
            'content' => '<p>Only the owner tenant should prerender this.</p>',
            'is_published' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$ownerId, $otherId, $ownerSlug, $otherSlug, $pageSlug];
    }

    private function frontendHost(): string
    {
        return parse_url((string) env('FRONTEND_URL', 'https://app.project-nexus.ie'), PHP_URL_HOST)
            ?: 'app.project-nexus.ie';
    }

    /**
     * @return array{0:int,1:int}
     */
    private function seedVolunteerContent(
        int $tenantId,
        string $organizationStatus = 'active',
        string $opportunityStatus = 'active'
    ): array {
        $now = date('Y-m-d H:i:s');
        $user = User::factory()->forTenant($tenantId)->create([
            'is_approved' => 1,
            'status' => 'active',
        ]);
        $suffix = uniqid();
        $orgId = (int) DB::table('vol_organizations')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'name' => 'Volunteer Org ' . $suffix,
            'slug' => 'vol-org-' . $suffix,
            'description' => 'A volunteer organisation for prerender regression tests.',
            'contact_email' => 'vol-org-' . $suffix . '@example.test',
            'status' => $organizationStatus,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $opportunityId = (int) DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $tenantId,
            'organization_id' => $orgId,
            'created_by' => $user->id,
            'title' => 'Volunteer Opportunity ' . $suffix,
            'description' => 'A volunteering opportunity for prerender regression tests.',
            'location' => 'Remote',
            'status' => $opportunityStatus,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [$orgId, $opportunityId];
    }

    private function writeSnapshot(string $rel, string $html): string
    {
        $path = $this->tmpCache . '/' . $rel;
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $html);

        return $path;
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir) ?: [];
        foreach ($items as $i) {
            if ($i === '.' || $i === '..') continue;
            $p = $dir . '/' . $i;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
