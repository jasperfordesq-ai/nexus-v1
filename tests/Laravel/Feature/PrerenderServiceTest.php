<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature;

use App\Console\Commands\PrerenderProcessQueue;
use App\Services\PrerenderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

    private string $tmpCache;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('prerender_jobs')) {
            $this->markTestSkipped('prerender_jobs table not present.');
        }
        $this->tmpCache = sys_get_temp_dir() . '/nexus-prerender-test-' . uniqid();
        mkdir($this->tmpCache, 0777, true);
        putenv('PRERENDER_CACHE_PATH=' . $this->tmpCache);
        putenv('PRERENDER_EVENT_LOG=' . $this->tmpCache . '/events.jsonl');
        putenv('PRERENDER_ASSETS_MANIFEST=' . $this->tmpCache . '/.assets-manifest.json');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpCache);
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
        $id2 = $service->enqueueJob(null, '/blog', false, false, null);

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
        $result = $svc->purgePattern('/blog/*', 'example.com', dryRun: true);

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
        $result = $svc->purgePattern('/blog/*', 'example.com', dryRun: false);
        $this->assertCount(1, $result['deleted']);
        $this->assertFileDoesNotExist($this->tmpCache . '/example.com/blog/post-1/index.html');
    }

    public function test_purge_pattern_recursive_double_star(): void
    {
        $this->writeSnapshot('example.com/blog/post-1/index.html', '<html><body>a</body></html>');
        $this->writeSnapshot('example.com/blog/category/foo/index.html', '<html><body>b</body></html>');
        $svc = new PrerenderService();
        $result = $svc->purgePattern('/blog/**', 'example.com', dryRun: true);
        $this->assertCount(2, $result['deleted']);
    }

    public function test_purge_pattern_scopes_to_host(): void
    {
        $this->writeSnapshot('a.com/blog/post/index.html', '<html><body>a</body></html>');
        $this->writeSnapshot('b.com/blog/post/index.html', '<html><body>b</body></html>');
        $svc = new PrerenderService();
        $result = $svc->purgePattern('/blog/*', 'a.com', dryRun: true);
        $this->assertCount(1, $result['deleted']);
        $this->assertSame('a.com/blog/post/index.html', $result['deleted'][0]);
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
        $id1 = $svc->enqueueJob(null, '/foo', false, false, null, PrerenderService::PRIORITY_LOW);
        // Same args + higher priority → should promote the existing row.
        $id2 = $svc->enqueueJob(null, '/foo', false, false, null, PrerenderService::PRIORITY_HIGH);
        $this->assertSame($id1, $id2);
        $row = DB::table('prerender_jobs')->where('id', $id1)->first();
        $this->assertSame(PrerenderService::PRIORITY_HIGH, (int) $row->priority);
    }

    public function test_claim_next_respects_priority(): void
    {
        $svc = new PrerenderService();
        // Older low-priority job comes in first.
        $oldLow = $svc->enqueueJob(null, '/old', false, false, null, PrerenderService::PRIORITY_LOW);
        // Newer high-priority job comes second.
        $newHigh = $svc->enqueueJob(null, '/new', false, false, null, PrerenderService::PRIORITY_HIGH);

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

    private function writeSnapshot(string $rel, string $html): void
    {
        $path = $this->tmpCache . '/' . $rel;
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $html);
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
