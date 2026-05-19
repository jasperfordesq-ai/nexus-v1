<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * PrerenderService — inspects the pre-render snapshot cache and the job queue.
 *
 * Read paths (filesystem):
 *   - The Playwright worker writes snapshots into the named volume
 *     `nexus-php-prerendered`, mounted RO into this container at
 *     PRERENDER_CACHE_PATH (default /var/www/html/storage/prerendered).
 *     Layout: {host}/{route}/index.html, plus `.last-run.json`,
 *     `.last-manifest.json`, `.failures.tsv`.
 *
 *   - The deploy phase writes a JSONL event log to PRERENDER_EVENT_LOG
 *     (default /var/www/html/storage/logs/host-logs/prerender-events.jsonl).
 *
 *   - Frontend build manifest under PRERENDER_ASSETS_PATH lists the
 *     currently-valid /assets/*.js|css filenames; used to flag snapshots
 *     that reference dead assets.
 *
 * Write paths (database):
 *   - prerender_jobs — queue + history. Rows here are picked up by the
 *     PrerenderProcessQueue artisan command which runs prerender-tenants.sh
 *     with the captured flags and writes results back.
 *
 * Realtime:
 *   - State changes on prerender_jobs broadcast via RealtimeService on
 *     channel `private-admin-prerender`, event `job.updated`. UI subscribes
 *     to replace polling.
 *
 * All filesystem operations are read-only; nothing here mutates rendered HTML.
 */
class PrerenderService
{
    /**
     * @deprecated Use routesForTenant($tenant) — it filters by feature/module
     * flags per tenant. This flat list renders every feature-gated route for
     * every tenant, generating 404s for tenants that don't have e.g. marketplace,
     * jobs, or events enabled. Retained only for legacy Coverage callers that
     * compare against a global expected set; new code should always go through
     * the tenant-aware resolver.
     */
    public const EXPECTED_ROUTES = [
        '/', '/about', '/faq', '/contact', '/help', '/explore', '/listings',
        '/blog', '/terms', '/privacy', '/accessibility', '/cookies',
        '/community-guidelines', '/trust-and-safety', '/acceptable-use',
        '/legal', '/terms/versions', '/privacy/versions', '/accessibility/versions',
        '/cookies/versions', '/community-guidelines/versions', '/acceptable-use/versions',
        '/timebanking-guide', '/regional-analytics', '/platform/terms', '/platform/privacy',
        '/platform/disclaimer', '/resources', '/kb', '/features', '/changelog',
        '/events', '/groups', '/jobs', '/coupons', '/marketplace', '/volunteering',
        '/pilot-inquiry', '/pilot-apply', '/developers', '/developers/auth',
        '/developers/endpoints', '/developers/webhooks',
        '/partner', '/social-prescribing', '/impact-report',
        '/impact-summary', '/strategic-plan',
    ];

    /**
     * Always-public routes that exist for every tenant regardless of feature
     * flags. These cover the platform skeleton (homepage, contact, legal,
     * pilot funnel, etc.) and never produce a 404 when prerendered.
     *
     * Routes NOT in this list and NOT covered by routesForTenant()'s feature
     * gates should not be prerendered — the React 404 page will respond and
     * the worker will capture a `_status=404` sidecar, which is a waste of
     * CPU and clutters the inventory.
     */
    private const ALWAYS_PUBLIC_ROUTES = [
        '/', '/about', '/faq', '/contact', '/help', '/explore',
        '/terms', '/privacy', '/accessibility', '/cookies',
        '/community-guidelines', '/trust-and-safety', '/acceptable-use',
        '/legal', '/terms/versions', '/privacy/versions', '/accessibility/versions',
        '/cookies/versions', '/community-guidelines/versions', '/acceptable-use/versions',
        '/timebanking-guide', '/regional-analytics',
        '/platform/terms', '/platform/privacy', '/platform/disclaimer',
        '/features', '/changelog', '/developers', '/developers/auth',
        '/developers/endpoints', '/developers/webhooks', '/development-status',
        '/pilot-inquiry', '/pilot-apply',
        // Tenant-gated marketing pages — the React app's TenantSlugGate
        // decides whether to render the actual content or a fallback for the
        // wrong tenant; either way the response is 200, so it's safe to
        // prerender for every tenant.
        '/partner', '/social-prescribing',
        '/impact-report', '/impact-summary', '/strategic-plan',
    ];

    /**
     * Feature-gated static routes — included only when the named feature is
     * enabled on the tenant. Mirrors SitemapService::getStaticPageUrls() gating
     * so static + sitemap stay consistent.
     *
     * Format: feature_name => [route, route, ...]
     */
    private const FEATURE_GATED_ROUTES = [
        'blog'                => ['/blog'],
        'events'              => ['/events'],
        'groups'              => ['/groups'],
        'job_vacancies'       => ['/jobs'],
        'merchant_coupons'    => ['/coupons'],
        'volunteering'        => ['/volunteering'],
        'ideation_challenges' => ['/ideation'],
        'resources'           => ['/resources', '/kb'],
        'organisations'       => ['/organisations'],
        'marketplace'         => ['/marketplace', '/marketplace/free', '/marketplace/map'],
    ];

    /**
     * Module-gated static routes (modules live in tenants.configuration.modules,
     * features in tenants.features — different storage, same idea).
     */
    private const MODULE_GATED_ROUTES = [
        'listings' => ['/listings'],
    ];

    public const STALE_AGE_SECONDS = 14 * 24 * 3600;
    public const WARN_AGE_SECONDS  = 7  * 24 * 3600;

    public const REALTIME_CHANNEL = 'private-admin-prerender';
    public const REALTIME_EVENT   = 'job.updated';

    private string $cachePath;
    private string $eventLogPath;
    private string $assetsPath;

    public function __construct()
    {
        $this->cachePath = rtrim((string) env(
            'PRERENDER_CACHE_PATH',
            '/var/www/html/storage/prerendered'
        ), '/');
        $this->eventLogPath = (string) env(
            'PRERENDER_EVENT_LOG',
            '/var/www/html/storage/logs/host-logs/prerender-events.jsonl'
        );
        // Path to the live frontend asset directory inside the React container.
        // We never read it directly (different container) — instead the deploy
        // writes the active asset manifest into the snapshot dir as
        // `.assets-manifest.json` for cross-container introspection.
        $this->assetsPath = (string) env(
            'PRERENDER_ASSETS_MANIFEST',
            $this->cachePath . '/.assets-manifest.json'
        );
    }

    public function cachePath(): string    { return $this->cachePath; }
    public function eventLogPath(): string { return $this->eventLogPath; }
    public function cacheReadable(): bool  { return is_dir($this->cachePath) && is_readable($this->cachePath); }

    // -------------------------------------------------------------------------
    // Summary / health
    // -------------------------------------------------------------------------

    /**
     * High-level health snapshot for the overview dashboard.
     *
     * @return array
     */
    public function summary(): array
    {
        $tenants = $this->loadTenantTargets();
        // Deep inventory so content_stale_count and asset_invalid_count are
        // truthful. Cached briefly to keep the dashboard cheap.
        $inventory = Cache::remember(
            'prerender:summary:inventory',
            60,
            fn () => $this->inventory(null, true)
        );
        $expected = $this->expectedSnapshotCount($tenants);
        $present = count($inventory);

        $oldest = null; $newest = null; $stale = 0; $warn = 0; $totalSize = 0;
        foreach ($inventory as $row) {
            $age = $row['age_s'];
            $totalSize += $row['size_bytes'];
            if ($oldest === null || $age > $oldest) $oldest = $age;
            if ($newest === null || $age < $newest) $newest = $age;
            if ($age >= self::STALE_AGE_SECONDS) $stale++;
            elseif ($age >= self::WARN_AGE_SECONDS) $warn++;
        }

        $lastRun = $this->readJsonFile($this->cachePath . '/.last-run.json');
        $failures = $this->readFailures();
        $events = $this->tailEvents(1);
        $contentStale = $this->contentStalenessCounts($inventory);

        // Tolerate the prerender_jobs table being absent — the migration may
        // not have run yet (e.g. fresh deploys). Treat as "no jobs" instead of
        // hard-500'ing the admin dashboard.
        $activeJobs = 0;
        $queuedJobs = 0;
        if (\Illuminate\Support\Facades\Schema::hasTable('prerender_jobs')) {
            $activeJobs = (int) DB::table('prerender_jobs')->whereIn('status', ['claimed', 'running'])->count();
            $queuedJobs = (int) DB::table('prerender_jobs')->where('status', 'queued')->count();
        }

        return [
            'cache_readable'        => $this->cacheReadable(),
            'cache_path'            => $this->cachePath,
            'total_snapshots'       => $present,
            'total_size_bytes'      => $totalSize,
            'oldest_age_s'          => $oldest,
            'newest_age_s'          => $newest,
            'stale_count'           => $stale,
            'warn_count'            => $warn,
            'missing_count'         => max(0, $expected - $present),
            'expected_count'        => $expected,
            'coverage_pct'          => $expected > 0 ? round(($present / $expected) * 100, 1) : 0.0,
            'last_run'              => $lastRun,
            'recent_failures'       => count($failures),
            'active_jobs'           => $activeJobs,
            'queued_jobs'           => $queuedJobs,
            'last_event_at'         => $events[0]['ts'] ?? null,
            'build_commit'          => $events[0]['commit'] ?? null,
            'expected_routes'       => self::EXPECTED_ROUTES,
            'tenant_count'          => count($tenants),
            'content_stale_count'   => $contentStale['content_stale'],
            'asset_invalid_count'   => $this->countAssetInvalid($inventory),
            'realtime_channel'      => self::REALTIME_CHANNEL,
            'realtime_event'        => self::REALTIME_EVENT,
        ];
    }

    // -------------------------------------------------------------------------
    // Inventory
    // -------------------------------------------------------------------------

    /**
     * Walk the snapshot cache. Two modes:
     *   $deep=false: cheap — sizes, mtimes, age, age-staleness only
     *   $deep=true:  parses each file's asset refs and validates against the
     *                live asset manifest. Adds content-staleness check.
     */
    /**
     * Safety cap to prevent a misbehaving Playwright run that wrote thousands
     * of files into one directory from hanging the admin summary. When hit,
     * `inventory()` returns a `__truncated => true` sentinel row at the front
     * of the array; UI displays a banner.
     */
    public const INVENTORY_HARD_CAP = 50_000;

    public function inventory(?string $tenantSlug = null, bool $deep = true): array
    {
        if (!$this->cacheReadable()) return [];

        $hostFilter = null;
        if ($tenantSlug !== null && $tenantSlug !== '') {
            $hostFilter = $this->resolveTenantHost($tenantSlug);
        }

        $now = time();
        $validAssets = $deep ? $this->loadValidAssets() : [];
        $tenantUpdated = $deep ? $this->loadTenantUpdatedAt() : [];
        $contentUpdated = $deep ? $this->loadContentUpdatedAt() : [];

        $rows = [];
        $truncated = false;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $this->cachePath,
                \FilesystemIterator::SKIP_DOTS
            )
        );

        foreach ($it as $file) {
            if (count($rows) >= self::INVENTORY_HARD_CAP) {
                $truncated = true;
                break;
            }
            if (!$file->isFile() || $file->getFilename() !== 'index.html') continue;
            $absPath = $file->getPathname();
            $rel = str_replace('\\', '/', ltrim(substr($absPath, strlen($this->cachePath)), '/\\'));

            $firstSlash = strpos($rel, '/');
            if ($firstSlash === false) continue;
            $host = substr($rel, 0, $firstSlash);
            $remainder = substr($rel, $firstSlash);
            $route = preg_replace('#/index\.html$#', '', $remainder) ?: '/';

            if ($hostFilter !== null && $host !== $hostFilter) continue;

            $mtime = $file->getMTime();
            $age = $now - $mtime;
            $ageStaleness = $age >= self::STALE_AGE_SECONDS ? 'stale'
                          : ($age >= self::WARN_AGE_SECONDS ? 'warn' : 'fresh');

            $assetRefs = [];
            $assetIssues = [];
            $contentStale = false;
            $contentStaleReason = null;

            if ($deep) {
                [$assetRefs, $assetIssues] = $this->parseAssetRefs($absPath, $validAssets);
                [$contentStale, $contentStaleReason] = $this->checkContentStaleness(
                    $host, $route, $mtime, $tenantUpdated, $contentUpdated
                );
            }

            // Combined staleness — content beats age beats fresh.
            $staleness = $ageStaleness;
            if (!empty($assetIssues)) $staleness = 'stale';
            if ($contentStale && $staleness === 'fresh') $staleness = 'warn';
            if ($contentStale && $staleness === 'warn') $staleness = 'stale';

            $statusCode = $this->readStatusSidecar(dirname($absPath));

            $rows[] = [
                'host'             => $host,
                'route'            => $route,
                'cache_path'       => $rel,
                'size_bytes'       => $file->getSize(),
                'mtime'            => $mtime,
                'age_s'            => $age,
                'staleness'        => $staleness,
                'age_staleness'    => $ageStaleness,
                'asset_refs'       => $assetRefs,
                'asset_issues'     => $assetIssues,
                'content_stale'    => $contentStale,
                'content_stale_reason' => $contentStaleReason,
                'http_status'      => $statusCode,
            ];
        }

        usort($rows, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        if ($truncated) {
            array_unshift($rows, [
                '__truncated' => true,
                'cap'         => self::INVENTORY_HARD_CAP,
                'host'        => '',
                'route'       => '',
                'cache_path'  => '',
            ]);
        }
        return $rows;
    }

    /**
     * Deep inspection of a single snapshot. Uses DOMDocument for structured
     * parsing — not regex — so JSON-LD validity, canonical correctness, and
     * meta-tag presence are reliable.
     */
    public function inspect(string $cachePath): ?array
    {
        if (!$this->cacheReadable()) return null;
        $safe = $this->safeCachePath($cachePath);
        if ($safe === null) return null;

        $abs = $this->cachePath . '/' . $safe;
        if (!is_file($abs) || !is_readable($abs)) return null;

        $html = (string) file_get_contents($abs);
        $mtime = (int) filemtime($abs);
        $size  = (int) filesize($abs);

        $dom = new \DOMDocument();
        // Suppress libxml warnings — bot snapshots may have minor HTML quirks.
        $previous = libxml_use_internal_errors(true);
        $loaded = @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        $libxmlErrors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xp = $loaded ? new \DOMXPath($dom) : null;

        $title = $loaded
            ? trim($this->firstNodeValue($xp, '//title') ?? '')
            : '';

        $metaDescription = $loaded
            ? $this->firstAttr($xp, "//meta[@name='description']", 'content')
            : null;

        $canonical = $loaded
            ? $this->firstAttr($xp, "//link[@rel='canonical']", 'href')
            : null;

        $ogTags = [];
        if ($loaded) {
            foreach ($xp->query("//meta[starts-with(@property,'og:')]") as $node) {
                /** @var \DOMElement $node */
                $ogTags[$node->getAttribute('property')] = $node->getAttribute('content');
            }
        }

        $jsonLdBlocks = [];
        $jsonLdValid = true;
        if ($loaded) {
            foreach ($xp->query("//script[@type='application/ld+json']") as $node) {
                $raw = trim($node->textContent ?? '');
                $parsed = json_decode($raw, true);
                $valid = is_array($parsed) || $raw === '';
                if (!$valid) $jsonLdValid = false;
                $jsonLdBlocks[] = [
                    'valid' => $valid,
                    'size'  => strlen($raw),
                    'types' => is_array($parsed) ? $this->extractSchemaTypes($parsed) : [],
                ];
            }
        }

        $h1Texts = [];
        if ($loaded) {
            foreach ($xp->query('//h1') as $node) {
                $h1Texts[] = trim($node->textContent ?? '');
            }
        }

        $assetRefs = [];
        if ($loaded) {
            foreach ($xp->query("//script[@src] | //link[@rel='stylesheet']") as $node) {
                /** @var \DOMElement $node */
                $src = $node->getAttribute('src') ?: $node->getAttribute('href');
                if ($src && str_starts_with($src, '/assets/')) $assetRefs[] = $src;
            }
        }
        $validAssets = $this->loadValidAssets();
        $assetIssues = [];
        if (!empty($validAssets)) {
            foreach ($assetRefs as $ref) {
                $bare = explode('?', explode('#', $ref)[0])[0];
                if (!isset($validAssets[$bare])) $assetIssues[] = $bare;
            }
        }

        // Strip script bodies for the preview.
        $preview = substr(
            preg_replace('#<script\b[^>]*>.*?</script>#is', '<script>…</script>', $html) ?? $html,
            0, 16384
        );

        $hasNoscript = (bool) ($xp && $xp->query('//noscript')->length > 0);

        $statusCode = $this->readStatusSidecar(dirname($abs));
        $integrity = $this->verifyIntegrity($abs, $html);

        $result = [
            'cache_path'  => $safe,
            'size_bytes'  => $size,
            'mtime'       => $mtime,
            'age_s'       => time() - $mtime,
            'http_status' => $statusCode,
            'integrity'   => $integrity,
            'title'       => $title,
            'meta_description' => $metaDescription,
            'canonical'   => $canonical,
            'og_tags'     => $ogTags,
            'h1_texts'    => $h1Texts,
            'json_ld'     => [
                'blocks_count' => count($jsonLdBlocks),
                'all_valid'    => $jsonLdValid,
                'blocks'       => $jsonLdBlocks,
            ],
            'asset_refs'   => array_values(array_unique($assetRefs)),
            'asset_issues' => array_values(array_unique($assetIssues)),
            'flags' => [
                'parses'         => $loaded !== false,
                'has_h1'         => count($h1Texts) > 0,
                'multiple_h1'    => count($h1Texts) > 1,
                'has_meta_desc'  => $metaDescription !== null && $metaDescription !== '',
                'has_og'         => !empty($ogTags),
                'has_canonical'  => $canonical !== null && $canonical !== '',
                'has_jsonld'     => count($jsonLdBlocks) > 0,
                'jsonld_valid'   => $jsonLdValid,
                'has_noscript'   => $hasNoscript,
            ],
            'parse_warnings' => array_map(
                fn($e) => trim($e->message),
                array_slice($libxmlErrors, 0, 5)
            ),
            'preview' => $preview,
        ];
        $result['seo'] = $this->seoScore($result);
        return $result;
    }

    // -------------------------------------------------------------------------
    // Coverage
    // -------------------------------------------------------------------------

    public function coverage(): array
    {
        $tenants = $this->loadTenantTargets();
        if (empty($tenants)) return [];
        $inventory = $this->inventory(null, true);

        $byHost = [];
        foreach ($inventory as $row) {
            $byHost[$row['host']][$row['route']] = $row;
        }

        $rows = [];
        foreach ($tenants as $t) {
            $host = $t['host'];
            $prefix = $t['prefix'];
            // Resolve THIS tenant's expected static route set — features they
            // don't have aren't expected to be rendered.
            $tObj = (object) ['features' => $t['features'], 'configuration' => $t['configuration']];
            $tenantRoutes = $this->routesForTenant($tObj);

            $rendered = 0; $missing = []; $stale = []; $invalidAssets = [];
            foreach ($tenantRoutes as $route) {
                $expectedRoute = $prefix . $route;
                $found = $byHost[$host][$expectedRoute] ?? null;
                if ($found === null) { $missing[] = $expectedRoute; continue; }
                $rendered++;
                if ($found['staleness'] !== 'fresh') $stale[] = $expectedRoute;
                if (!empty($found['asset_issues'])) $invalidAssets[] = $expectedRoute;
            }
            $rows[] = [
                'tenant_id'      => $t['tenant_id'],
                'slug'           => $t['slug'],
                'host'           => $host,
                'expected'       => count($tenantRoutes),
                'rendered'       => $rendered,
                'missing'        => count($missing),
                'missing_routes' => $missing,
                'stale_routes'   => $stale,
                'asset_invalid_routes' => $invalidAssets,
            ];
        }
        usort($rows, fn($a, $b) => strcmp($a['slug'], $b['slug']));
        return $rows;
    }

    // -------------------------------------------------------------------------
    // Events / failures
    // -------------------------------------------------------------------------

    public function tailEvents(int $limit = 200): array
    {
        if (!is_readable($this->eventLogPath)) return [];
        $limit = max(1, min(2000, $limit));
        $lines = @file($this->eventLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) return [];
        $tail = array_slice($lines, -$limit);
        $out = [];
        foreach (array_reverse($tail) as $line) {
            $row = json_decode($line, true);
            if (is_array($row)) $out[] = $row;
        }
        return $out;
    }

    public function readFailures(): array
    {
        $path = $this->cachePath . '/.failures.tsv';
        if (!is_readable($path)) return [];
        $now = time();
        $out = [];
        $fh = @fopen($path, 'r');
        if (!$fh) return [];
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = explode("\t", $line);
            if (count($parts) < 2) continue;
            $out[] = [
                'cache_path' => $parts[1],
                'failed_at'  => (int) $parts[0],
                'age_s'      => $now - (int) $parts[0],
            ];
        }
        fclose($fh);
        usort($out, fn($a, $b) => $b['failed_at'] <=> $a['failed_at']);
        return $out;
    }

    // -------------------------------------------------------------------------
    // Crawler analytics (Phase 3.2)
    // -------------------------------------------------------------------------

    /**
     * Aggregate the bot-only JSONL access log written by nginx
     * (see nginx.bluegreen.conf — log_format prerender_bot_jsonl).
     *
     * Returns:
     *   total_hits, hits_by_status, hits_by_crawler, hits_by_host, top_uris,
     *   recent (last 100 rows).
     *
     * The log is line-bounded to the last MAX_BYTES from the tail to keep
     * memory predictable; rotation is the caller's responsibility (logrotate
     * or a cron tail-and-truncate).
     */
    public function crawlerAnalytics(?string $sinceIso = null, int $limit = 200): array
    {
        $logPath = $this->cachePath . '/.bot-access.jsonl';
        $empty = [
            'total_hits'      => 0,
            'window_started_at' => $sinceIso,
            'hits_by_status'  => [],
            'hits_by_crawler' => [],
            'hits_by_host'    => [],
            'top_uris'        => [],
            'recent'          => [],
            'log_path'        => $logPath,
            'log_size_bytes'  => is_file($logPath) ? @filesize($logPath) : 0,
        ];
        if (!is_readable($logPath)) return $empty;

        $sinceTs = $sinceIso ? @strtotime($sinceIso) : (time() - 7 * 24 * 3600);
        if (!$sinceTs) $sinceTs = time() - 7 * 24 * 3600;

        // Tail up to 8 MB of log — typical bot traffic is well under that for
        // a week; if higher, increase or move to a rotated archive.
        $maxBytes = 8 * 1024 * 1024;
        $size = (int) @filesize($logPath);
        $offset = $size > $maxBytes ? $size - $maxBytes : 0;

        $byStatus = [];
        $byCrawler = [];
        $byHost = [];
        $uriCounts = [];
        $recent = [];
        $total = 0;
        $verifiedCount = 0;
        $spoofedByCrawler = [];

        $fh = @fopen($logPath, 'r');
        if (!$fh) return $empty;
        if ($offset > 0) @fseek($fh, $offset);
        // Discard the (likely partial) first line after a seek.
        if ($offset > 0) fgets($fh);

        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') continue;
            $row = json_decode($line, true);
            if (!is_array($row)) continue;
            $ts = isset($row['ts']) ? (int) strtotime((string) $row['ts']) : 0;
            if ($ts < $sinceTs) continue;

            $total++;
            $status = (int) ($row['status'] ?? 0);
            $crawler = (string) ($row['crawler'] ?? 'other');
            $host = (string) ($row['host'] ?? '');
            $uri = (string) ($row['uri'] ?? '');

            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $byCrawler[$crawler] = ($byCrawler[$crawler] ?? 0) + 1;
            if ($host !== '') $byHost[$host] = ($byHost[$host] ?? 0) + 1;
            $verified = (string) ($row['verified'] ?? '');
            if ($verified === '1') {
                $verifiedCount++;
            } else {
                // Only crawlers that SHOULD verify (i.e. major search engines).
                // Social unfurlers don't publish IP ranges so we don't expect
                // them to be in the trusted list.
                if (in_array($crawler, ['googlebot', 'bingbot', 'duckduckbot', 'applebot', 'google-extended'], true)) {
                    $spoofedByCrawler[$crawler] = ($spoofedByCrawler[$crawler] ?? 0) + 1;
                }
            }
            if ($uri !== '') {
                $key = $host . $uri;
                $uriCounts[$key] = ($uriCounts[$key] ?? 0) + 1;
            }

            if (count($recent) < $limit) {
                $recent[] = [
                    'ts'      => $row['ts'] ?? null,
                    'host'    => $host,
                    'uri'     => $uri,
                    'status'  => $status,
                    'crawler' => $crawler,
                    'ua'      => $row['ua'] ?? '',
                    'ip'      => $row['ip'] ?? '',
                ];
            }
        }
        fclose($fh);

        arsort($byStatus);
        arsort($byCrawler);
        arsort($byHost);
        arsort($uriCounts);

        // Keep recent in newest-first order.
        $recent = array_reverse($recent);

        $topUris = [];
        $i = 0;
        foreach ($uriCounts as $k => $n) {
            $topUris[] = ['url' => $k, 'hits' => $n];
            if (++$i >= 50) break;
        }

        return [
            'total_hits'      => $total,
            'verified_hits'   => $verifiedCount,
            'spoofed_by_crawler' => $spoofedByCrawler,
            'window_started_at' => date('c', $sinceTs),
            'hits_by_status'  => $byStatus,
            'hits_by_crawler' => $byCrawler,
            'hits_by_host'    => $byHost,
            'top_uris'        => $topUris,
            'recent'          => $recent,
            'log_path'        => $logPath,
            'log_size_bytes'  => $size,
        ];
    }

    // -------------------------------------------------------------------------
    // Content-change invalidation (Phase 2.3)
    // -------------------------------------------------------------------------

    /**
     * Invalidate (delete + enqueue recache) the snapshot for a specific route
     * across all tenants the route belongs to. Safe to call from model
     * observers in response to save/delete events.
     *
     * @param int $tenantId         The tenant whose snapshot should be touched.
     * @param array<int,string> $routes  Route paths to invalidate, e.g. ['/blog/foo', '/blog'].
     * @param bool $enqueueRecache  If true, also enqueue a low-priority recache job.
     */
    public function invalidateRoutes(int $tenantId, array $routes, bool $enqueueRecache = true): int
    {
        $routes = array_values(array_unique(array_filter(
            $routes,
            fn($r) => is_string($r)
                && $r !== ''
                && $r[0] === '/'
                && !$this->isUnsupportedPublicRoute($r)
        )));
        if (empty($routes)) return 0;

        // Resolve tenant host + prefix.
        $row = DB::table('tenants')
            ->where('id', $tenantId)
            ->where('is_active', 1)
            ->select('slug', DB::raw("COALESCE(domain, '') as domain"))
            ->first();
        if (!$row) return 0;

        $appHost = $this->frontendHost();
        $domain = trim((string) $row->domain);
        $host = $domain !== '' ? $domain : $appHost;
        $prefix = $domain !== '' ? '' : '/' . $row->slug;

        $deleted = 0;
        foreach ($routes as $route) {
            $outRoute = $prefix . $route;
            $rel = $route === '/' ? $host . '/index.html' : $host . $outRoute . '/index.html';
            $abs = $this->cachePath . '/' . $rel;
            if (is_file($abs)) {
                @unlink($abs);
                @unlink(dirname($abs) . '/_status');
                $deleted++;
            }
        }

        if ($enqueueRecache && $deleted > 0) {
            // Backpressure: bulk imports (e.g. seeding 5,000 blog posts) would
            // otherwise enqueue 5,000 distinct queued rows (each with a unique
            // per-post route, so the dedup key differs and they don't coalesce).
            // If we've fired more than the burst threshold for this tenant in
            // the last minute, drop the per-route precision and enqueue a
            // single tenant-wide row instead — the dedup probe in enqueueJob
            // then collapses subsequent bursts onto that one row.
            $burstKey   = "prerender:burst:tenant:{$tenantId}";
            $burstCount = (int) \Illuminate\Support\Facades\Cache::increment($burstKey);
            if ($burstCount === 1) {
                \Illuminate\Support\Facades\Cache::put($burstKey, 1, 60);
            }
            $routesArg = $burstCount > 50 ? null : implode(',', $routes);

            // NORMAL priority: a content save is a user-initiated event with a
            // human waiting for the public page to update. Background sweeps
            // run at LOW; observer-triggered work belongs ahead of them.
            $this->enqueueJob(
                $tenantId,
                $routesArg,
                false, // force (snapshots are gone — they'll be re-rendered)
                false,
                null,
                self::PRIORITY_NORMAL
            );
        }
        return $deleted;
    }

    // -------------------------------------------------------------------------
    // Cache purge (wildcard)
    // -------------------------------------------------------------------------

    /**
     * Delete snapshot files whose route matches a glob pattern.
     *
     * Pattern uses fnmatch semantics:
     *   /blog/*           — every direct child of /blog
     *   /blog/**          — every descendant of /blog
     *   /listings/*       — direct children only (no nested)
     *   /                 — homepage only
     *
     * Optional $hostFilter scopes the purge to a single host (tenant domain or
     * app-host slug prefix). NULL = every tenant.
     *
     * Returns the list of cache_paths deleted. The caller is responsible for
     * enqueueing recache jobs if it wants the routes re-rendered.
     *
     * @return array{deleted:list<string>, dry_run:bool, pattern:string, host:?string}
     */
    public function purgePattern(string $pattern, ?string $hostFilter = null, bool $dryRun = false): array
    {
        $pattern = trim($pattern);
        if ($pattern === '' || $pattern[0] !== '/') {
            return ['deleted' => [], 'dry_run' => $dryRun, 'pattern' => $pattern, 'host' => $hostFilter];
        }

        $allowDoubleStar = str_contains($pattern, '**');
        $globRegex = $this->globToRegex($pattern, $allowDoubleStar);
        $deleted = [];

        foreach ($this->inventory(null, false) as $row) {
            if ($hostFilter !== null && $row['host'] !== $hostFilter) continue;
            if (!preg_match($globRegex, $row['route'])) continue;

            $abs = $this->cachePath . '/' . $row['cache_path'];
            if (!$dryRun && is_file($abs)) {
                @unlink($abs);
                // Best effort: clean up the now-empty directory.
                @rmdir(dirname($abs));
                // Drop status sidecar if present (see Phase 1.2).
                @unlink(dirname($abs) . '/_status');
            }
            $deleted[] = $row['cache_path'];
        }

        return [
            'deleted'  => $deleted,
            'dry_run'  => $dryRun,
            'pattern'  => $pattern,
            'host'     => $hostFilter,
        ];
    }

    private function globToRegex(string $glob, bool $allowDoubleStar): string
    {
        // Escape regex metacharacters except the glob wildcards we honour.
        $out = '';
        $i = 0;
        $len = strlen($glob);
        while ($i < $len) {
            $c = $glob[$i];
            if ($allowDoubleStar && $c === '*' && $i + 1 < $len && $glob[$i + 1] === '*') {
                $out .= '.*';
                $i += 2;
                continue;
            }
            if ($c === '*')      { $out .= '[^/]*'; }
            elseif ($c === '?')  { $out .= '[^/]';  }
            else                  { $out .= preg_quote($c, '#'); }
            $i++;
        }
        return '#^' . $out . '$#';
    }

    // -------------------------------------------------------------------------
    // Job queue
    // -------------------------------------------------------------------------

    /** Priority constants. Lower number wins. See migration for full table. */
    public const PRIORITY_HIGH   = 3;
    public const PRIORITY_NORMAL = 5;
    public const PRIORITY_LOW    = 7;

    /**
     * Route regex used everywhere (controller, webhook, observer-injected
     * routes). Routes flow through to a bash shell `eval` in the host job
     * processor, so input validation is a defence-in-depth requirement, not
     * a UX nicety.
     */
    public const ROUTE_REGEX = '#^/[A-Za-z0-9._~/%:@!$()*+,;=\-]*$#';

    public function enqueueJob(
        ?int $tenantId,
        ?string $routes,
        bool $force,
        bool $dryRun,
        ?int $requestedBy,
        int $priority = self::PRIORITY_NORMAL
    ): int {
        $priority = max(1, min(9, $priority));
        if ($routes !== null) {
            $routes = substr(trim($routes), 0, 2000);
            if ($routes === '') {
                $routes = null;
            } else {
                // Defence in depth: every route token must match ROUTE_REGEX.
                // Observer-injected routes already go through routesFor() and
                // are static strings, but a future contributor adding a
                // dynamic routesFor() could land unsafe characters in a shell
                // eval. Reject those here.
                $tokens = array_filter(array_map('trim', explode(',', $routes)));
                foreach ($tokens as $tok) {
                    if (!preg_match(self::ROUTE_REGEX, $tok)) {
                        throw new \InvalidArgumentException("Invalid route in enqueueJob: {$tok}");
                    }
                }
                $routes = implode(',', $tokens);
                if ($routes === '') $routes = null;
            }
        }

        // Transactional dedup. Two concurrent observers firing on the same
        // model save can both see "no queued row" and both insert; wrapping
        // the probe+insert in a transaction with lockForUpdate serialises
        // them through MariaDB row locks.
        return DB::transaction(function () use ($tenantId, $routes, $force, $dryRun, $requestedBy, $priority): int {
            $existing = DB::table('prerender_jobs')
                ->where('status', 'queued')
                ->where('tenant_id', $tenantId)
                ->where('routes', $routes)
                ->where('force_render', $force ? 1 : 0)
                ->where('dry_run', $dryRun ? 1 : 0)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            if ($existing) {
                // If a higher-priority caller is enqueueing a dup, promote it.
                if ($priority < (int) ($existing->priority ?? self::PRIORITY_NORMAL)) {
                    DB::table('prerender_jobs')->where('id', $existing->id)->update(['priority' => $priority]);
                    $this->broadcastJob((int) $existing->id);
                }
                return (int) $existing->id;
            }

            $id = (int) DB::table('prerender_jobs')->insertGetId([
                'requested_by'  => $requestedBy,
                'tenant_id'     => $tenantId,
                'routes'        => $routes,
                'force_render'  => $force ? 1 : 0,
                'dry_run'       => $dryRun ? 1 : 0,
                'priority'      => $priority,
                'status'        => 'queued',
                'queued_at'     => date('Y-m-d H:i:s'),
            ]);

            $this->broadcastJob($id);
            return $id;
        });
    }

    public function cancelJob(int $id): bool
    {
        $rows = DB::table('prerender_jobs')
            ->where('id', $id)
            ->where('status', 'queued')
            ->update([
                'status'        => 'cancelled',
                'finished_at'   => date('Y-m-d H:i:s'),
                'error_message' => 'cancelled by admin',
            ]);
        if ($rows > 0) $this->broadcastJob($id);
        return $rows > 0;
    }

    public function listJobs(int $limit = 50, ?string $status = null): array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('prerender_jobs')) {
            return [];
        }
        $q = DB::table('prerender_jobs as j')
            ->leftJoin('users as u', 'u.id', '=', 'j.requested_by')
            ->leftJoin('tenants as t', 't.id', '=', 'j.tenant_id')
            ->select(
                'j.*',
                'u.first_name as user_first',
                'u.last_name as user_last',
                'u.email as user_email',
                't.slug as tenant_slug'
            )
            ->orderByDesc('j.id')
            ->limit(max(1, min(500, $limit)));
        if ($status !== null && $status !== '') $q->where('j.status', $status);
        return $q->get()->map(fn($r) => $this->normaliseJob((array) $r))->toArray();
    }

    public function getJob(int $id): ?array
    {
        $r = DB::table('prerender_jobs as j')
            ->leftJoin('users as u', 'u.id', '=', 'j.requested_by')
            ->leftJoin('tenants as t', 't.id', '=', 'j.tenant_id')
            ->where('j.id', $id)
            ->select(
                'j.*',
                'u.first_name as user_first',
                'u.last_name as user_last',
                'u.email as user_email',
                't.slug as tenant_slug'
            )
            ->first();
        if (!$r) return null;
        return $this->normaliseJob((array) $r);
    }

    // -------------------------------------------------------------------------
    // Circuit breaker
    // -------------------------------------------------------------------------
    //
    // If the worker fails N times in a row inside a short window, something is
    // wrong on the host (build broken, disk full, Playwright wedged) and
    // running more jobs just adds noise + uses CPU. We pause the queue for a
    // cooldown — claimNextJob returns null even when rows exist — and let
    // operators investigate. Auto-resumes when the cooldown elapses.

    public const BREAKER_FAILURE_THRESHOLD = 5;   // consecutive failures
    public const BREAKER_WINDOW_SECONDS    = 600; // within 10 minutes
    public const BREAKER_COOLDOWN_SECONDS  = 900; // pause queue 15 minutes
    public const BREAKER_CACHE_KEY         = 'prerender:breaker:tripped_until';

    public function breakerTrippedUntil(): ?int
    {
        $ts = (int) (\Illuminate\Support\Facades\Cache::get(self::BREAKER_CACHE_KEY) ?? 0);
        return $ts > time() ? $ts : null;
    }

    public function tripBreaker(?int $cooldownSeconds = null): int
    {
        $until = time() + ($cooldownSeconds ?? self::BREAKER_COOLDOWN_SECONDS);
        \Illuminate\Support\Facades\Cache::put(self::BREAKER_CACHE_KEY, $until, $until - time() + 60);
        Log::warning('Prerender breaker tripped', ['until' => date('c', $until)]);
        return $until;
    }

    public function resetBreaker(): void
    {
        \Illuminate\Support\Facades\Cache::forget(self::BREAKER_CACHE_KEY);
    }

    /**
     * Max concurrent running jobs per tenant. Keeps a single misbehaving
     * tenant (one with a slow homepage, say) from starving every other one.
     * Global concurrency is bounded by the host cron (1 tick = 1 job).
     */
    public const PER_TENANT_RUNNING_CAP = 1;

    /**
     * Atomically claim the next eligible queued job. Honours:
     *   - circuit breaker (returns null if tripped)
     *   - per-tenant concurrency cap (skips rows whose tenant already has
     *     a running/claimed sibling)
     *   - priority + FIFO order
     */
    public function claimNextJob(string $claimedBy): ?array
    {
        if ($this->breakerTrippedUntil() !== null) {
            return null;
        }

        return DB::transaction(function () use ($claimedBy): ?array {
            // Tenants that already have a job in flight. Concurrency cap.
            $busy = DB::table('prerender_jobs')
                ->whereIn('status', ['claimed', 'running'])
                ->whereNotNull('tenant_id')
                ->groupBy('tenant_id')
                ->havingRaw('COUNT(*) >= ?', [self::PER_TENANT_RUNNING_CAP])
                ->pluck('tenant_id')
                ->all();

            $q = DB::table('prerender_jobs')
                ->where('status', 'queued')
                ->orderBy('priority')
                ->orderBy('queued_at')
                ->orderBy('id')
                ->lockForUpdate();

            if (!empty($busy)) {
                // Skip rows whose tenant is over the cap. NULL tenant_id
                // (all-tenants jobs) still claimable — those serialise via
                // the host worker lock.
                $q->where(function ($w) use ($busy) {
                    $w->whereNull('tenant_id')->orWhereNotIn('tenant_id', $busy);
                });
            }
            $row = $q->first();
            if (!$row) return null;

            DB::table('prerender_jobs')
                ->where('id', $row->id)
                ->update([
                    'status'     => 'claimed',
                    'claimed_at' => date('Y-m-d H:i:s'),
                    'claimed_by' => substr($claimedBy, 0, 128),
                ]);

            $this->broadcastJob((int) $row->id);
            return (array) $row;
        });
    }

    /**
     * Transition a claimed job to running. Used by the artisan command before
     * invoking the underlying prerender script.
     */
    public function markRunning(int $id): void
    {
        DB::table('prerender_jobs')->where('id', $id)->update([
            'status'     => 'running',
            'started_at' => date('Y-m-d H:i:s'),
        ]);
        $this->broadcastJob($id);
    }

    /**
     * Finalise a job from the artisan command. $status must be one of:
     * succeeded | partial | failed.
     */
    public function finaliseJob(
        int $id,
        string $status,
        ?int $planned,
        ?int $rendered,
        ?int $invalid,
        ?int $exitCode,
        int $durationS,
        ?string $logExcerpt,
        ?string $errorMessage = null
    ): void {
        $logExcerpt = $logExcerpt !== null ? substr($logExcerpt, -262_144) : null;
        DB::table('prerender_jobs')->where('id', $id)->update([
            'status'         => $status,
            'planned_count'  => $planned,
            'rendered_count' => $rendered,
            'invalid_count'  => $invalid ?? 0,
            'duration_s'     => $durationS,
            'exit_code'      => $exitCode,
            'log_excerpt'    => $logExcerpt,
            'error_message'  => $errorMessage,
            'finished_at'    => date('Y-m-d H:i:s'),
        ]);
        $this->broadcastJob($id);

        // Circuit breaker. Count recent failures inside the window; trip if
        // we cross the threshold. A succeeded/partial job resets the streak.
        if ($status === 'failed') {
            $recentFails = DB::table('prerender_jobs')
                ->where('status', 'failed')
                ->where('finished_at', '>=', date('Y-m-d H:i:s', time() - self::BREAKER_WINDOW_SECONDS))
                ->count();
            if ($recentFails >= self::BREAKER_FAILURE_THRESHOLD) {
                $this->tripBreaker();
            }
        }
    }

    // -------------------------------------------------------------------------
    // Prometheus metrics
    // -------------------------------------------------------------------------

    /**
     * Render Prometheus text-format metrics. Pull this from a scrape job or
     * grafana dashboard for long-term trend analysis.
     */
    public function prometheusMetrics(): string
    {
        $s = $this->summary();
        $lines = [];

        $g = fn(string $name, $value, string $help, string $type = 'gauge') => array_push(
            $lines,
            "# HELP {$name} {$help}",
            "# TYPE {$name} {$type}",
            sprintf('%s %s', $name, is_bool($value) ? ($value ? 1 : 0) : $value)
        );

        $g('nexus_prerender_cache_readable',     $s['cache_readable'], 'Cache mount reachable from app');
        $g('nexus_prerender_snapshots_total',    $s['total_snapshots'], 'Snapshot files on disk');
        $g('nexus_prerender_snapshots_expected', $s['expected_count'], 'Tenant_count * route_count');
        $g('nexus_prerender_snapshots_missing',  $s['missing_count'], 'Routes without a snapshot');
        $g('nexus_prerender_snapshots_stale',    $s['stale_count'], 'Snapshots older than stale threshold');
        $g('nexus_prerender_snapshots_aging',    $s['warn_count'], 'Snapshots older than warn threshold');
        $g('nexus_prerender_content_stale_total',$s['content_stale_count'], 'Snapshots older than their source content');
        $g('nexus_prerender_asset_invalid_total',$s['asset_invalid_count'], 'Snapshots referencing dead assets');
        $g('nexus_prerender_cache_bytes',        $s['total_size_bytes'], 'Total snapshot bytes on disk');
        $g('nexus_prerender_jobs_queued',        $s['queued_jobs'], 'Jobs awaiting processor');
        $g('nexus_prerender_jobs_active',        $s['active_jobs'], 'Jobs claimed or running');
        $g('nexus_prerender_failures_recent',    $s['recent_failures'], 'Cache paths inside failure-backoff window');
        $g('nexus_prerender_coverage_ratio',     $s['expected_count'] > 0 ? round($s['total_snapshots'] / $s['expected_count'], 4) : 0,
           'Snapshots present / expected (0..1)');

        // Per-status job counts (counters reflect lifetime, not since-reboot).
        $statusCounts = DB::table('prerender_jobs')
            ->select('status', DB::raw('COUNT(*) as n'))
            ->groupBy('status')
            ->pluck('n', 'status')
            ->toArray();
        $lines[] = '# HELP nexus_prerender_jobs_total Lifetime job counts by status';
        $lines[] = '# TYPE nexus_prerender_jobs_total counter';
        foreach (['queued','claimed','running','succeeded','partial','failed','cancelled'] as $st) {
            $n = (int) ($statusCounts[$st] ?? 0);
            $lines[] = sprintf('nexus_prerender_jobs_total{status="%s"} %d', $st, $n);
        }

        // Per-tenant coverage gauges
        $coverage = $this->coverage();
        $lines[] = '# HELP nexus_prerender_tenant_rendered Snapshots present per tenant';
        $lines[] = '# TYPE nexus_prerender_tenant_rendered gauge';
        foreach ($coverage as $row) {
            $lines[] = sprintf(
                'nexus_prerender_tenant_rendered{tenant_id="%d",slug="%s"} %d',
                $row['tenant_id'], $this->escapePromLabel($row['slug']), $row['rendered']
            );
        }
        $lines[] = '# HELP nexus_prerender_tenant_missing Routes missing a snapshot per tenant';
        $lines[] = '# TYPE nexus_prerender_tenant_missing gauge';
        foreach ($coverage as $row) {
            $lines[] = sprintf(
                'nexus_prerender_tenant_missing{tenant_id="%d",slug="%s"} %d',
                $row['tenant_id'], $this->escapePromLabel($row['slug']), $row['missing']
            );
        }

        // Last run duration
        $lastRun = $this->readJsonFile($this->cachePath . '/.last-run.json');
        if (is_array($lastRun) && isset($lastRun['duration_s'])) {
            $lines[] = '# HELP nexus_prerender_last_run_duration_seconds Duration of the most recent prerender run';
            $lines[] = '# TYPE nexus_prerender_last_run_duration_seconds gauge';
            $lines[] = sprintf('nexus_prerender_last_run_duration_seconds %d', (int) $lastRun['duration_s']);
        }

        // Round 2 metrics — circuit breaker + queue age + health rollup.
        $breakerUntil = $this->breakerTrippedUntil();
        $g('nexus_prerender_breaker_tripped', $breakerUntil !== null ? 1 : 0, 'Circuit breaker tripped (1) or closed (0)');
        $g('nexus_prerender_breaker_until_seconds', $breakerUntil ?? 0, 'Unix ts when breaker auto-resumes (0 = closed)');

        // Oldest queued job age in seconds — the most useful queue-health number.
        $oldestQueuedRaw = DB::table('prerender_jobs')->where('status', 'queued')->min('queued_at');
        $oldestQueuedAge = $oldestQueuedRaw ? max(0, time() - strtotime($oldestQueuedRaw)) : 0;
        $g('nexus_prerender_queue_oldest_age_seconds', $oldestQueuedAge, 'Age of the oldest queued job (0 if queue empty)');

        // Health rollup as a numeric so Grafana/alertmanager can use thresholds.
        $h = $this->health();
        $hVal = $h['status'] === 'green' ? 0 : ($h['status'] === 'yellow' ? 1 : 2);
        $g('nexus_prerender_health_status', $hVal, 'Engine health: 0=green, 1=yellow, 2=red');

        return implode("\n", $lines) . "\n";
    }

    // -------------------------------------------------------------------------
    // Realtime
    // -------------------------------------------------------------------------

    /**
     * Broadcast a job's current state on the admin realtime channel. Used at
     * every lifecycle transition. Failures are swallowed (broadcasting is best
     * effort; UI polls as fallback).
     */
    public function broadcastJob(int $id): void
    {
        try {
            $row = $this->getJob($id);
            if (!$row) return;
            // Pusher's HTTP API caps event payloads at 10 KiB. Drift-detector
            // jobs can carry 80+ routes plus 250 KiB log excerpts — well over
            // the limit. Strip / truncate fields the realtime UI doesn't need
            // for a status update; the inspect modal fetches the full row via
            // a separate REST call when the user clicks in.
            $broadcastRow = $row;
            unset($broadcastRow['log_excerpt']);
            if (isset($broadcastRow['routes']) && is_string($broadcastRow['routes']) && strlen($broadcastRow['routes']) > 400) {
                $broadcastRow['routes'] = substr($broadcastRow['routes'], 0, 400) . '…';
                $broadcastRow['routes_truncated'] = true;
            }
            $rt = app(RealtimeService::class);
            $rt->broadcast(self::REALTIME_CHANNEL, self::REALTIME_EVENT, [
                'job' => $broadcastRow,
                'ts'  => time(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('PrerenderService::broadcastJob failed', ['id' => $id, 'error' => $e->getMessage()]);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the per-tenant static-route floor based on tenants.features and
     * tenants.configuration.modules. Mirrors SitemapService gating so the
     * prerender engine and the published sitemap agree on what exists.
     *
     * Accepts either a stdClass row (with `features` + `configuration` JSON
     * strings) or a tenant_id + lazy fetch.
     *
     * @return list<string>
     */
    public function routesForTenant(int|object $tenant): array
    {
        if (is_int($tenant)) {
            $row = DB::table('tenants')
                ->where('id', $tenant)
                ->where('is_active', 1)
                ->select('id', 'features', 'configuration')
                ->first();
            if (!$row) return [];
            $tenant = $row;
        }

        $features = $this->decodeJsonColumn($tenant->features ?? null);
        $configuration = $this->decodeJsonColumn($tenant->configuration ?? null);
        $modules = is_array($configuration['modules'] ?? null) ? $configuration['modules'] : [];

        $routes = self::ALWAYS_PUBLIC_ROUTES;

        // Features default to TRUE per TenantFeatureConfig::FEATURE_DEFAULTS,
        // so an unset feature key means "on". Modules also default on.
        foreach (self::FEATURE_GATED_ROUTES as $feature => $featureRoutes) {
            if (($features[$feature] ?? true) === true || ($features[$feature] ?? true) === 1) {
                foreach ($featureRoutes as $r) $routes[] = $r;
            }
        }
        foreach (self::MODULE_GATED_ROUTES as $module => $moduleRoutes) {
            if (($modules[$module] ?? true) === true || ($modules[$module] ?? true) === 1) {
                foreach ($moduleRoutes as $r) $routes[] = $r;
            }
        }

        return array_values(array_unique($routes));
    }

    private function decodeJsonColumn(?string $raw): array
    {
        if (!is_string($raw) || $raw === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return list<array{tenant_id:int, slug:string, host:string, prefix:string}>
     */
    public function loadTenantTargets(): array
    {
        $appHost = $this->frontendHost();
        $rows = DB::table('tenants')
            ->where('is_active', 1)
            ->where('id', '<>', 1)
            ->select('id', 'slug', DB::raw("COALESCE(domain, '') as domain"), 'features', 'configuration')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $domain = trim((string) $r->domain);
            $host = $domain !== '' ? $domain : $appHost;
            $prefix = $domain !== '' ? '' : '/' . $r->slug;
            $out[] = [
                'tenant_id'      => (int) $r->id,
                'slug'           => (string) $r->slug,
                'host'           => $host,
                'prefix'         => $prefix,
                // Pass the raw JSON through so callers can use routesForTenant
                // without a second DB query.
                'features'       => $r->features ?? null,
                'configuration'  => $r->configuration ?? null,
            ];
        }
        return $out;
    }

    /**
     * Sweep the snapshot cache for rows whose route is NOT in the tenant's
     * current expected set — typically 404 leftovers from before this code
     * went tenant-aware, or from a feature being turned off on a tenant.
     *
     * Returns the per-tenant list of deleted cache paths. Caller decides
     * whether to also enqueue recaches (none needed — the deleted snapshots
     * shouldn't exist for these tenants in the first place).
     *
     * @return array{deleted_total:int, by_tenant:array<string,list<string>>, dry_run:bool}
     */
    public function purgeUnexpectedSnapshots(bool $dryRun = false): array
    {
        $tenants = $this->loadTenantTargets();
        // Build a (host, route)-keyed expected set. Inventory rows store the
        // route WITH the tenant prefix for app-host tenants (e.g.
        // /partner-demo/about), so we must apply the prefix here too or the
        // purge would shred every legitimate prefixed snapshot.
        // Multiple tenants can share a host (the app host), so we union the
        // expected sets across tenants per host.
        $expectedByHost = [];
        $slugByHostRoute = []; // For reporting only — which tenant "owned" the missing route.
        // List of known tenant prefixes per host. Used to strip the tenant
        // prefix off an inventory route BEFORE the dynamic-content check —
        // otherwise an inventory like "/partner-demo/blog/foo" looks like a
        // static route, not a dynamic blog post.
        $prefixesByHost = [];
        foreach ($tenants as $t) {
            $tObj = (object) ['features' => $t['features'], 'configuration' => $t['configuration']];
            $tenantRoutes = $this->routesForTenant($tObj);
            $prefix = $t['prefix']; // '' for custom-domain tenants, '/slug' for app-host
            if ($prefix !== '') {
                $prefixesByHost[$t['host']][] = $prefix;
            }
            foreach ($tenantRoutes as $r) {
                $prefixed = $prefix . $r;
                // Normalise the homepage entry: inventory drops the trailing
                // slash from "/partner-demo/index.html" → "/partner-demo",
                // while $prefix . '/' produces "/partner-demo/". Treat both
                // forms as the homepage so the comparison hits.
                if ($r === '/' && $prefix !== '') {
                    $expectedByHost[$t['host']][$prefix] = true;
                    $slugByHostRoute[$t['host']][$prefix] = $t['slug'];
                } else {
                    $expectedByHost[$t['host']][$prefixed] = true;
                    $slugByHostRoute[$t['host']][$prefixed] = $t['slug'];
                }
            }
        }

        $byTenant = [];
        $deletedTotal = 0;
        foreach ($this->inventory(null, false) as $row) {
            $hostExpected = $expectedByHost[$row['host']] ?? null;
            if ($hostExpected === null) continue;

            // Strip tenant prefix before the dynamic check. Match against the
            // KNOWN tenant prefixes for this host (collected above) — never
            // guess from a regex, because routes like /marketplace/free would
            // otherwise be misread as a tenant prefix.
            $tenantLocalRoute = $row['route'];
            foreach ($prefixesByHost[$row['host']] ?? [] as $tenantPrefix) {
                if (str_starts_with($tenantLocalRoute, $tenantPrefix . '/')) {
                    $tenantLocalRoute = substr($tenantLocalRoute, strlen($tenantPrefix));
                    break;
                }
                if ($tenantLocalRoute === $tenantPrefix) {
                    $tenantLocalRoute = '/';
                    break;
                }
            }

            // Skip dynamic-content routes — they're not in the static
            // expected set but they ARE legitimate snapshots. The drift
            // detector keeps them fresh from DB state.
            if ($this->isDynamicContentRoute($tenantLocalRoute)) continue;

            if (isset($hostExpected[$row['route']])) continue;

            // Resolve which tenant this prefixed orphan most likely belonged
            // to. For app-host tenants the prefix is the slug; for
            // custom-domain tenants there's only one tenant per host so use
            // any expected entry.
            $slug = '(unknown)';
            $firstSeg = '';
            if (preg_match('#^/([A-Za-z0-9_-]+)#', $row['route'], $m)) $firstSeg = $m[1];
            if ($firstSeg !== '') {
                // Look up any prefix match in the host's expected map.
                foreach ($slugByHostRoute[$row['host']] ?? [] as $eRoute => $eSlug) {
                    if (str_starts_with($eRoute, '/' . $firstSeg . '/') || $eRoute === '/' . $firstSeg) {
                        $slug = $eSlug;
                        break;
                    }
                }
            }
            if ($slug === '(unknown)') {
                // Fall back to whatever tenant is on this host (single-tenant case).
                $slug = reset($slugByHostRoute[$row['host']]) ?: '(unknown)';
            }

            // This static route isn't expected for this tenant — purge it.
            $byTenant[$slug][] = $row['route'];
            $deletedTotal++;

            if (!$dryRun) {
                $abs = $this->cachePath . '/' . $row['cache_path'];
                @unlink($abs);
                @unlink(dirname($abs) . '/_status');
                @unlink(dirname($abs) . '/index.md');
                @rmdir(dirname($abs));
            }
        }

        return [
            'deleted_total' => $deletedTotal,
            'by_tenant'     => $byTenant,
            'dry_run'       => $dryRun,
        ];
    }

    /**
     * Returns true for routes that represent dynamic, content-table-backed
     * URLs (blog posts, listings, events, etc). Used by purgeUnexpectedSnapshots
     * to avoid deleting legitimately-rendered detail pages just because they
     * aren't in the static floor.
     */
    private function isDynamicContentRoute(string $route): bool
    {
        static $prefixes = [
            '/blog/', '/listings/', '/events/', '/jobs/', '/marketplace/',
            '/marketplace/category/', '/groups/', '/volunteering/',
            '/volunteering/opportunities/', '/organisations/',
            '/ideation/', '/kb/', '/page/',
        ];
        foreach ($prefixes as $p) {
            if (str_starts_with($route, $p) && strlen($route) > strlen($p)) {
                return true;
            }
        }
        return false;
    }

    private function isUnsupportedPublicRoute(string $route): bool
    {
        // React has a public /resources listing and /resources/{id}/download API,
        // but no visible /resources/{id} page. Keep resource changes scoped to
        // the listing snapshot instead of queuing snapshots that can only 404.
        return (bool) preg_match('#^/resources/[^/]+$#', $route);
    }

    private function frontendHost(): string
    {
        $url = (string) env('FRONTEND_URL', 'https://app.project-nexus.ie');
        $parts = parse_url($url);
        return $parts['host'] ?? 'app.project-nexus.ie';
    }

    private function resolveTenantHost(string $slug): ?string
    {
        $row = DB::table('tenants')
            ->where('is_active', 1)
            ->where('slug', $slug)
            ->select('slug', DB::raw("COALESCE(domain, '') as domain"))
            ->first();
        if (!$row) return null;
        $domain = trim((string) $row->domain);
        return $domain !== '' ? $domain : $this->frontendHost();
    }

    private function expectedSnapshotCount(array $tenants): int
    {
        // Sum each tenant's per-tenant expected route count rather than
        // multiplying by a global constant. Tenants with features disabled
        // legitimately have fewer expected static snapshots.
        $total = 0;
        foreach ($tenants as $t) {
            $tObj = (object) [
                'features' => $t['features'] ?? null,
                'configuration' => $t['configuration'] ?? null,
            ];
            $total += count($this->routesForTenant($tObj));
        }
        return $total;
    }

    private function safeCachePath(string $rel): ?string
    {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        if ($rel === '' || str_contains($rel, '..')) return null;
        // Match the route regex used elsewhere so legitimate routes containing
        // : @ ~ ( ) + , ; = ! $ * don't 404 the inspect drawer. The .. check
        // above plus the /index.html suffix guarantee path safety.
        if (!preg_match('#^[A-Za-z0-9._~/%:@!$()+,;=\-]+/index\.html$#', $rel)) return null;
        return $rel;
    }

    /**
     * Look up the TTL (seconds) for a route based on config/prerender.php
     * patterns. Most specific match wins; falls back to `default`.
     *
     * Glob semantics:
     *   `/`          — exact match only
     *   `/blog/*`    — direct children
     *   `/blog/**`   — every descendant
     */
    public function ttlForRoute(string $route): int
    {
        return $this->describeTtlForRoute($route)['ttl_seconds'];
    }

    /**
     * Same TTL resolution as ttlForRoute but returns full diagnostics — the
     * matched pattern, every candidate pattern that also matched, and the
     * winning specificity score. Powers the admin TTL inspector.
     *
     * @return array{route:string, ttl_seconds:int, matched_pattern:?string,
     *               source:string, all_matches:list<array{pattern:string,ttl:int,specificity:int}>}
     */
    public function describeTtlForRoute(string $route): array
    {
        $patterns = config('prerender.ttl', []);
        $defaultTtl = is_array($patterns) ? (int) ($patterns['default'] ?? 7 * 24 * 3600) : 7 * 24 * 3600;
        if (!is_array($patterns)) {
            return [
                'route'          => $route,
                'ttl_seconds'    => $defaultTtl,
                'matched_pattern'=> null,
                'source'         => 'default',
                'all_matches'    => [],
            ];
        }

        $best = null;
        $bestPat = null;
        $bestSpecificity = -1;
        $allMatches = [];
        foreach ($patterns as $pat => $ttl) {
            if ($pat === 'default') continue;
            if (!$this->routeMatchesPattern($route, $pat)) continue;
            $literal = strpos($pat, '*');
            $specificity = $literal === false ? strlen($pat) * 100 : $literal * 10 - substr_count($pat, '*');
            $allMatches[] = ['pattern' => $pat, 'ttl' => (int) $ttl, 'specificity' => $specificity];
            if ($specificity > $bestSpecificity) {
                $bestSpecificity = $specificity;
                $best = (int) $ttl;
                $bestPat = $pat;
            }
        }

        // Stable order for the UI.
        usort($allMatches, fn($a, $b) => $b['specificity'] <=> $a['specificity']);

        return [
            'route'          => $route,
            'ttl_seconds'    => $best ?? $defaultTtl,
            'matched_pattern'=> $bestPat,
            'source'         => $bestPat !== null ? 'pattern' : 'default',
            'all_matches'    => $allMatches,
        ];
    }

    private function routeMatchesPattern(string $route, string $pattern): bool
    {
        if ($route === $pattern) return true;
        $allowDoubleStar = str_contains($pattern, '**');
        $re = $this->globToRegex($pattern, $allowDoubleStar);
        return (bool) preg_match($re, $route);
    }

    /**
     * Verify the snapshot's content matches its `.sha256` sidecar (written by
     * the worker at render time).
     *
     * @return array{status: 'ok'|'missing'|'mismatch'|'unreadable', expected:?string, actual:?string}
     *   status = 'missing'   when no sidecar exists (older snapshots predate this feature)
     *   status = 'unreadable' when the sidecar is malformed
     *   status = 'mismatch'  when bytes have changed (corruption / tamper / partial write)
     *   status = 'ok'        when bytes match the recorded sha256
     */
    public function verifyIntegrity(string $absHtmlPath, ?string $htmlBytes = null): array
    {
        $sidecar = $absHtmlPath . '.sha256';
        if (!is_file($sidecar) || !is_readable($sidecar)) {
            return ['status' => 'missing', 'expected' => null, 'actual' => null];
        }
        $raw = trim((string) @file_get_contents($sidecar));
        // Format: "<hex>  <bytecount>". Be tolerant — accept hex alone too.
        if (!preg_match('/^([a-f0-9]{64})/i', $raw, $m)) {
            return ['status' => 'unreadable', 'expected' => null, 'actual' => null];
        }
        $expected = strtolower($m[1]);
        $actual = strtolower(hash('sha256', $htmlBytes ?? (string) @file_get_contents($absHtmlPath)));
        return [
            'status'   => hash_equals($expected, $actual) ? 'ok' : 'mismatch',
            'expected' => $expected,
            'actual'   => $actual,
        ];
    }

    /**
     * Read the worker's `_status` sidecar for a snapshot directory. Returns
     * 200 if absent (the implicit default).
     */
    private function readStatusSidecar(string $dir): int
    {
        $path = $dir . '/_status';
        if (!is_readable($path)) return 200;
        $raw = trim((string) @file_get_contents($path, false, null, 0, 8));
        $n = (int) $raw;
        return ($n >= 100 && $n < 600) ? $n : 200;
    }

    private function readJsonFile(string $path): ?array
    {
        if (!is_readable($path)) return null;
        $raw = @file_get_contents($path);
        if ($raw === false) return null;
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Load the active frontend asset manifest as ['/assets/index-HASH.js' => true].
     * Written by the deploy script alongside snapshots.
     */
    private function loadValidAssets(): array
    {
        $data = $this->readJsonFile($this->assetsPath);
        if (!is_array($data)) return [];
        $out = [];
        foreach ($data as $name) {
            if (!is_string($name)) continue;
            $bare = explode('?', explode('#', $name)[0])[0];
            $out[$bare] = true;
        }
        return $out;
    }

    private function parseAssetRefs(string $absPath, array $validAssets): array
    {
        $html = (string) @file_get_contents($absPath, false, null, 0, 524_288);
        preg_match_all('#/assets/[A-Za-z0-9._/\-]+\.(?:js|css)#', $html, $m);
        $refs = array_values(array_unique($m[0] ?? []));
        $issues = [];
        if (!empty($validAssets)) {
            foreach ($refs as $r) {
                if (!isset($validAssets[$r])) $issues[] = $r;
            }
        }
        return [$refs, $issues];
    }

    /**
     * Snapshot of tenants.updated_at to detect content drift. NULL → epoch.
     *
     * @return array<int,int> host => unix ts
     */
    private function loadTenantUpdatedAt(): array
    {
        $appHost = $this->frontendHost();
        $rows = DB::table('tenants')
            ->where('is_active', 1)
            ->select('id', 'slug', 'updated_at', DB::raw("COALESCE(domain, '') as domain"))
            ->get();
        $out = [];
        foreach ($rows as $r) {
            $host = trim((string) $r->domain) !== '' ? $r->domain : $appHost;
            $ts = $r->updated_at ? strtotime((string) $r->updated_at) : 0;
            $out[$host] = max($out[$host] ?? 0, (int) $ts);
        }
        return $out;
    }

    /**
     * Latest content update timestamp per (host, route). Coarse, but good
     * enough to flag snapshots that pre-date their underlying content.
     *
     * Routes are matched by route-prefix:
     *   /blog       — newest posts.updated_at across the tenant
     *   /events     — newest events.updated_at
     *   /listings   — newest listings.updated_at
     *
     * @return array<string, array<string,int>>  host => route => unix ts
     */
    private function loadContentUpdatedAt(): array
    {
        $appHost = $this->frontendHost();
        $tenants = DB::table('tenants')
            ->where('is_active', 1)
            ->select('id', 'slug', DB::raw("COALESCE(domain, '') as domain"))
            ->get()
            ->mapWithKeys(function ($r) use ($appHost) {
                $host = trim((string) $r->domain) !== '' ? $r->domain : $appHost;
                return [(int) $r->id => $host];
            })
            ->toArray();

        $out = [];

        $queries = [
            '/blog'     => ['table' => 'posts',    'col' => 'updated_at'],
            '/events'   => ['table' => 'events',   'col' => 'updated_at'],
            '/listings' => ['table' => 'listings', 'col' => 'updated_at'],
            '/groups'   => ['table' => 'groups',   'col' => 'updated_at'],
        ];

        foreach ($queries as $route => $q) {
            if (!Schema::hasTable($q['table'])) continue;
            if (!Schema::hasColumn($q['table'], $q['col'])) continue;
            $rows = DB::table($q['table'])
                ->select('tenant_id', DB::raw("MAX({$q['col']}) as ts"))
                ->groupBy('tenant_id')
                ->get();
            foreach ($rows as $row) {
                $host = $tenants[(int) $row->tenant_id] ?? null;
                if (!$host) continue;
                $ts = $row->ts ? strtotime((string) $row->ts) : 0;
                $out[$host][$route] = max($out[$host][$route] ?? 0, (int) $ts);
            }
        }

        return $out;
    }

    private function checkContentStaleness(
        string $host,
        string $route,
        int $snapshotMtime,
        array $tenantUpdated,
        array $contentUpdated
    ): array {
        // Tenant-level changes (logo, meta description, h1, etc) invalidate
        // every route on that host.
        $tenantTs = $tenantUpdated[$host] ?? 0;
        if ($tenantTs > $snapshotMtime + 60) {
            return [true, 'tenant settings updated ' . $this->ago($tenantTs) . ' (snapshot older)'];
        }

        // Route-specific content. Match by prefix so "/blog" covers
        // "/blog/post-1" too.
        foreach ($contentUpdated[$host] ?? [] as $contentRoute => $ts) {
            if ($ts > $snapshotMtime + 60 && (str_starts_with($route, $contentRoute) || $route === $contentRoute)) {
                return [true, "{$contentRoute} content updated " . $this->ago($ts)];
            }
        }
        return [false, null];
    }

    private function ago(int $ts): string
    {
        $sec = max(0, time() - $ts);
        if ($sec < 3600)   return floor($sec / 60) . 'm ago';
        if ($sec < 86400)  return floor($sec / 3600) . 'h ago';
        return floor($sec / 86400) . 'd ago';
    }

    private function contentStalenessCounts(array $inventory): array
    {
        $contentStale = 0;
        foreach ($inventory as $row) {
            if (!empty($row['content_stale'])) $contentStale++;
        }
        return ['content_stale' => $contentStale];
    }

    private function countAssetInvalid(array $inventory): int
    {
        $n = 0;
        foreach ($inventory as $row) {
            if (!empty($row['asset_issues'])) $n++;
        }
        return $n;
    }

    private function normaliseJob(array $r): array
    {
        $user = null;
        if (!empty($r['requested_by'])) {
            $user = [
                'id'    => (int) $r['requested_by'],
                'name'  => trim(($r['user_first'] ?? '') . ' ' . ($r['user_last'] ?? '')),
                'email' => $r['user_email'] ?? null,
            ];
        }
        return [
            'id'             => (int) $r['id'],
            'status'         => (string) $r['status'],
            'tenant_id'      => $r['tenant_id'] !== null ? (int) $r['tenant_id'] : null,
            'tenant_slug'    => $r['tenant_slug'] ?? null,
            'routes'         => $r['routes'] ?? null,
            'force'          => (bool) $r['force_render'],
            'dry_run'        => (bool) $r['dry_run'],
            'priority'       => isset($r['priority']) ? (int) $r['priority'] : self::PRIORITY_NORMAL,
            'planned_count'  => $r['planned_count'] !== null ? (int) $r['planned_count'] : null,
            'rendered_count' => $r['rendered_count'] !== null ? (int) $r['rendered_count'] : null,
            'invalid_count'  => $r['invalid_count'] !== null ? (int) $r['invalid_count'] : null,
            'duration_s'     => $r['duration_s'] !== null ? (int) $r['duration_s'] : null,
            'exit_code'      => $r['exit_code'] !== null ? (int) $r['exit_code'] : null,
            'log_excerpt'    => $r['log_excerpt'] ?? null,
            'error_message'  => $r['error_message'] ?? null,
            'claimed_by'     => $r['claimed_by'] ?? null,
            'queued_at'      => $r['queued_at'] ?? null,
            'claimed_at'     => $r['claimed_at'] ?? null,
            'started_at'     => $r['started_at'] ?? null,
            'finished_at'    => $r['finished_at'] ?? null,
            'requested_by'   => $user,
        ];
    }

    private function firstNodeValue(?\DOMXPath $xp, string $expr): ?string
    {
        if (!$xp) return null;
        $nodes = $xp->query($expr);
        if (!$nodes || $nodes->length === 0) return null;
        return $nodes->item(0)->textContent;
    }

    private function firstAttr(?\DOMXPath $xp, string $expr, string $attr): ?string
    {
        if (!$xp) return null;
        $nodes = $xp->query($expr);
        if (!$nodes || $nodes->length === 0) return null;
        /** @var \DOMElement $node */
        $node = $nodes->item(0);
        $v = $node->getAttribute($attr);
        return $v === '' ? null : $v;
    }

    /**
     * Compute a 0-100 SEO score for a snapshot from its parsed flags. The
     * weights are deliberately simple — this is a hygiene signal, not a
     * comprehensive audit. Heavy SEO work belongs in a dedicated audit tool.
     *
     * Scoring rubric (max 100):
     *    title present + length 10–70    : 15
     *    meta description 50–160 chars   : 10
     *    canonical present + absolute    : 10
     *    og:title + og:description       : 10
     *    og:image                        :  5
     *    exactly one h1                  : 10
     *    JSON-LD present + valid         : 15
     *    no asset issues                 : 10
     *    has noscript fallback           :  5
     *    title doesn't repeat across site:  (deferred — needs corpus)
     *    body text >= 800 chars          : 10
     *
     * @param array $insp Result of inspect()
     */
    public function seoScore(array $insp): array
    {
        $score = 0;
        $issues = [];
        $tips = [];

        $title = (string) ($insp['title'] ?? '');
        $titleLen = mb_strlen($title);
        if ($titleLen >= 10 && $titleLen <= 70) {
            $score += 15;
        } elseif ($titleLen > 0) {
            $score += 5;
            $tips[] = $titleLen < 10 ? 'Title too short (<10 chars)' : 'Title too long (>70 chars)';
        } else {
            $issues[] = 'Missing <title>';
        }

        $desc = (string) ($insp['meta_description'] ?? '');
        $descLen = mb_strlen($desc);
        if ($descLen >= 50 && $descLen <= 160) {
            $score += 10;
        } elseif ($descLen > 0) {
            $score += 4;
            $tips[] = $descLen < 50 ? 'Meta description too short (<50 chars)' : 'Meta description too long (>160 chars)';
        } else {
            $issues[] = 'Missing meta description';
        }

        $canonical = (string) ($insp['canonical'] ?? '');
        if ($canonical !== '' && (str_starts_with($canonical, 'http://') || str_starts_with($canonical, 'https://'))) {
            $score += 10;
        } elseif ($canonical !== '') {
            $score += 3;
            $tips[] = 'Canonical should be an absolute URL';
        } else {
            $issues[] = 'Missing canonical link';
        }

        $og = $insp['og_tags'] ?? [];
        $hasOgTitle = isset($og['og:title']) && $og['og:title'] !== '';
        $hasOgDesc  = isset($og['og:description']) && $og['og:description'] !== '';
        $hasOgImage = isset($og['og:image']) && $og['og:image'] !== '';
        if ($hasOgTitle && $hasOgDesc) $score += 10;
        else $issues[] = 'Open Graph title/description incomplete';
        if ($hasOgImage) $score += 5;
        else $tips[] = 'Add og:image for richer social cards';

        $h1Count = is_array($insp['h1_texts'] ?? null) ? count($insp['h1_texts']) : 0;
        if ($h1Count === 1) $score += 10;
        elseif ($h1Count === 0) $issues[] = 'No <h1> on page';
        else { $score += 4; $tips[] = "Multiple <h1> tags ({$h1Count}) — use exactly one"; }

        $jsonLd = $insp['json_ld'] ?? [];
        $blocks = (int) ($jsonLd['blocks_count'] ?? 0);
        $allValid = (bool) ($jsonLd['all_valid'] ?? true);
        if ($blocks > 0 && $allValid) $score += 15;
        elseif ($blocks > 0) { $score += 5; $issues[] = 'Invalid JSON-LD block'; }
        else $tips[] = 'No structured data — consider adding JSON-LD';

        $assetIssues = is_array($insp['asset_issues'] ?? null) ? count($insp['asset_issues']) : 0;
        if ($assetIssues === 0) $score += 10;
        else $issues[] = "{$assetIssues} dead asset reference(s)";

        if (!empty($insp['flags']['has_noscript'])) $score += 5;
        else $tips[] = 'No <noscript> fallback';

        // Rough body-content signal from the preview snippet.
        $previewText = strip_tags((string) ($insp['preview'] ?? ''));
        if (mb_strlen($previewText) >= 800) $score += 10;
        elseif (mb_strlen($previewText) >= 300) $score += 4;
        else $issues[] = 'Body content very thin (<300 chars of text)';

        $score = max(0, min(100, $score));

        $grade = $score >= 90 ? 'A'
               : ($score >= 80 ? 'B'
               : ($score >= 65 ? 'C'
               : ($score >= 50 ? 'D' : 'F')));

        return [
            'score'  => $score,
            'grade'  => $grade,
            'issues' => array_values($issues),
            'tips'   => array_values($tips),
        ];
    }

    private function extractSchemaTypes(array $json): array
    {
        $types = [];
        $walk = function ($node) use (&$walk, &$types) {
            if (!is_array($node)) return;
            if (isset($node['@type'])) {
                $t = $node['@type'];
                if (is_string($t)) $types[] = $t;
                elseif (is_array($t)) foreach ($t as $tt) if (is_string($tt)) $types[] = $tt;
            }
            foreach ($node as $v) if (is_array($v)) $walk($v);
        };
        $walk($json);
        return array_values(array_unique($types));
    }

    private function escapePromLabel(string $s): string
    {
        return str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $s);
    }

    // -------------------------------------------------------------------------
    // Audit log
    // -------------------------------------------------------------------------

    /**
     * Persist a single audit entry. Keep details to 8 KiB — anything more
     * goes in log_excerpt on the job row instead.
     */
    public function audit(
        string $action,
        ?int $actorUserId,
        ?int $tenantId = null,
        ?int $jobId = null,
        string $outcome = 'ok',
        ?array $details = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): void {
        try {
            $detailsJson = $details === null ? null : json_encode(
                $this->sanitiseAuditDetails($details),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
            if (is_string($detailsJson) && strlen($detailsJson) > 8192) {
                $detailsJson = substr($detailsJson, 0, 8192);
            }
            DB::table('prerender_audit_log')->insert([
                'actor_user_id' => $actorUserId,
                'action'        => substr($action, 0, 64),
                'tenant_id'     => $tenantId,
                'job_id'        => $jobId,
                'outcome'       => in_array($outcome, ['ok', 'denied', 'error'], true) ? $outcome : 'ok',
                'details'       => $detailsJson,
                'ip'            => $ip !== null ? substr($ip, 0, 64) : null,
                'user_agent'    => $userAgent !== null ? substr($userAgent, 0, 255) : null,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Auditing failures must NEVER block the underlying operation.
            Log::warning('Prerender audit insert failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Drop any obvious secrets from the audit body. Belt-and-braces — the
     * controller already validates payloads but a future endpoint could
     * forward sensitive fields.
     */
    private function sanitiseAuditDetails(array $details): array
    {
        $forbidden = ['password', 'token', 'secret', 'api_key', 'authorization', 'bearer'];
        foreach ($details as $k => $v) {
            $lower = strtolower((string) $k);
            foreach ($forbidden as $needle) {
                if (str_contains($lower, $needle)) {
                    $details[$k] = '[REDACTED]';
                    continue 2;
                }
            }
            if (is_array($v)) {
                $details[$k] = $this->sanitiseAuditDetails($v);
            }
        }
        return $details;
    }

    /**
     * Recent audit entries for the admin UI.
     *
     * @return list<array<string,mixed>>
     */
    public function recentAudit(int $limit = 100, ?string $action = null): array
    {
        $q = DB::table('prerender_audit_log as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_user_id')
            ->leftJoin('tenants as t', 't.id', '=', 'a.tenant_id')
            ->select(
                'a.*',
                'u.first_name as actor_first',
                'u.last_name as actor_last',
                'u.email as actor_email',
                't.slug as tenant_slug'
            )
            ->orderByDesc('a.id')
            ->limit(max(1, min(500, $limit)));
        if (is_string($action) && $action !== '') $q->where('a.action', $action);
        return $q->get()->map(function ($r) {
            $r = (array) $r;
            if (!empty($r['details']) && is_string($r['details'])) {
                $decoded = json_decode($r['details'], true);
                if (is_array($decoded)) $r['details'] = $decoded;
            }
            return $r;
        })->all();
    }

    // -------------------------------------------------------------------------
    // Health endpoint
    // -------------------------------------------------------------------------

    /**
     * Traffic-light health for the prerender engine.
     *
     * Status:
     *   green  — queue draining, no breaker, all checks pass
     *   yellow — degraded (stale queue, recent failures, missing dirs)
     *   red    — broken (cache unreachable, breaker tripped, queue jammed)
     *
     * Each component lists exactly what's wrong + an actionable suggestion
     * so operators don't have to dig through metrics to decide what to do.
     *
     * @return array{status:string, checks: list<array<string,mixed>>}
     */
    public function health(): array
    {
        $checks = [];
        $worst = 'green';
        $bump = function (string $s) use (&$worst) {
            $rank = ['green' => 0, 'yellow' => 1, 'red' => 2];
            if ($rank[$s] > $rank[$worst]) $worst = $s;
        };

        // 1. Cache filesystem.
        if ($this->cacheReadable()) {
            $checks[] = ['name' => 'cache_filesystem', 'status' => 'green', 'detail' => $this->cachePath];
        } else {
            $checks[] = [
                'name'   => 'cache_filesystem',
                'status' => 'red',
                'detail' => 'Snapshot cache directory not readable',
                'action' => "ls -la {$this->cachePath} on the host; check the nexus-php-prerendered volume mount",
            ];
            $bump('red');
        }

        // 2. Circuit breaker.
        $breakerUntil = $this->breakerTrippedUntil();
        if ($breakerUntil !== null) {
            $checks[] = [
                'name'   => 'circuit_breaker',
                'status' => 'red',
                'detail' => 'Breaker tripped until ' . date('c', $breakerUntil),
                'action' => 'POST /api/v2/admin/prerender/reset-breaker after fixing the root cause (check the latest failed jobs)',
            ];
            $bump('red');
        } else {
            $checks[] = ['name' => 'circuit_breaker', 'status' => 'green', 'detail' => 'Closed (normal)'];
        }

        // 3. Queue draining. Oldest queued row should be < 5 minutes old.
        $oldestQueued = DB::table('prerender_jobs')
            ->where('status', 'queued')
            ->min('queued_at');
        if ($oldestQueued !== null) {
            $ageS = max(0, time() - strtotime($oldestQueued));
            if ($ageS > 600) {
                $checks[] = [
                    'name'   => 'queue_age',
                    'status' => 'red',
                    'detail' => "Oldest queued job is {$ageS}s old (>10m)",
                    'action' => 'Check the host cron is running: sudo systemctl status cron && cat /etc/cron.d/nexus-prerender-processor',
                ];
                $bump('red');
            } elseif ($ageS > 120) {
                $checks[] = [
                    'name'   => 'queue_age',
                    'status' => 'yellow',
                    'detail' => "Oldest queued job is {$ageS}s old (>2m)",
                    'action' => 'Investigate if this persists; the worker may be slow or backed up',
                ];
                $bump('yellow');
            } else {
                $checks[] = ['name' => 'queue_age', 'status' => 'green', 'detail' => "Oldest queued: {$ageS}s"];
            }
        } else {
            $checks[] = ['name' => 'queue_age', 'status' => 'green', 'detail' => 'Queue empty'];
        }

        // 4. Recent failure rate.
        $cutoff = date('Y-m-d H:i:s', time() - self::BREAKER_WINDOW_SECONDS);
        $recentFails = DB::table('prerender_jobs')
            ->where('status', 'failed')
            ->where('finished_at', '>=', $cutoff)
            ->count();
        if ($recentFails >= self::BREAKER_FAILURE_THRESHOLD) {
            $checks[] = [
                'name'   => 'recent_failures',
                'status' => 'red',
                'detail' => "{$recentFails} failed jobs in last 10m",
                'action' => 'Inspect the latest failed job for root cause',
            ];
            $bump('red');
        } elseif ($recentFails > 0) {
            $checks[] = [
                'name'   => 'recent_failures',
                'status' => 'yellow',
                'detail' => "{$recentFails} failed jobs in last 10m",
                'action' => 'One-off or transient; tolerate up to ' . self::BREAKER_FAILURE_THRESHOLD,
            ];
            $bump('yellow');
        } else {
            $checks[] = ['name' => 'recent_failures', 'status' => 'green', 'detail' => 'No recent failures'];
        }

        // 5. Stuck claimed/running rows.
        $stuckCutoff = date('Y-m-d H:i:s', time() - 1800);
        $stuck = DB::table('prerender_jobs')
            ->whereIn('status', ['claimed', 'running'])
            ->where('claimed_at', '<', $stuckCutoff)
            ->count();
        if ($stuck > 0) {
            $checks[] = [
                'name'   => 'stuck_jobs',
                'status' => 'yellow',
                'detail' => "{$stuck} jobs claimed >30m without finishing",
                'action' => 'Run: docker exec nexus-php-app php artisan prerender:reap-stale',
            ];
            $bump('yellow');
        } else {
            $checks[] = ['name' => 'stuck_jobs', 'status' => 'green', 'detail' => 'No stuck jobs'];
        }

        // 6. Scheduler liveness. Each prerender:* cron stamps a cache key on
        // success; if the gap exceeds 3× the expected interval, the scheduler
        // itself is probably wedged (Laravel queue worker / supervisord
        // problem) and the engine is degrading silently.
        $expectations = [
            'prerender-detect-drift'  => 120,    // every 2 min  → alert at 6 min
            'prerender-auto-recache'  => 1200,   // every 20 min → alert at 60 min
            'prerender-reap-stale'    => 300,    // every 5 min  → alert at 15 min
        ];
        foreach ($expectations as $name => $interval) {
            $lastOk = (int) \Illuminate\Support\Facades\Cache::get('prerender:sched:' . $name . ':last_ok_at', 0);
            if ($lastOk === 0) {
                $checks[] = [
                    'name'   => 'sched_' . $name,
                    'status' => 'yellow',
                    'detail' => 'No successful run recorded yet',
                    'action' => 'Normal during the first few minutes after a deploy or cache flush',
                ];
                $bump('yellow');
                continue;
            }
            $ageS = time() - $lastOk;
            if ($ageS > $interval * 3) {
                $checks[] = [
                    'name'   => 'sched_' . $name,
                    'status' => 'red',
                    'detail' => "Last successful run {$ageS}s ago (expected every {$interval}s)",
                    'action' => 'Verify the Laravel scheduler is running (supervisord nexus-scheduler unit)',
                ];
                $bump('red');
            } elseif ($ageS > $interval * 2) {
                $checks[] = [
                    'name'   => 'sched_' . $name,
                    'status' => 'yellow',
                    'detail' => "Last successful run {$ageS}s ago (expected every {$interval}s)",
                ];
                $bump('yellow');
            } else {
                $checks[] = [
                    'name'   => 'sched_' . $name,
                    'status' => 'green',
                    'detail' => "Last successful run {$ageS}s ago",
                ];
            }
        }

        return [
            'status'       => $worst,
            'checked_at'   => date('c'),
            'breaker_until'=> $breakerUntil,
            'checks'       => $checks,
        ];
    }
}
