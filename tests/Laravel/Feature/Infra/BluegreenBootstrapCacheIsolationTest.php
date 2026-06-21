<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Infra;

use PHPUnit\Framework\TestCase;

/**
 * Regression lock for the bootstrap/cache cross-container TOCTOU
 * (Sentry NEXUS-PHP-10, 2026-06-21).
 *
 * ROOT CAUSE: compose.bluegreen.yml mounted a single, NON-color-namespaced
 * external volume (`nexus-php-bootstrap-cache`) over /var/www/html/bootstrap/cache
 * in BOTH the queue and scheduler services — and the same external volume name
 * is referenced by both the blue and green stacks, so all four of
 * {blue,green}×{queue,scheduler} shared ONE cache directory.
 *
 * The queue and scheduler each run `php artisan optimize` at startup, which calls
 * route:cache / event:cache — these DELETE routes-v7.php / events.php and then
 * regenerate them. A crash-looping queue (Horizon OOM at 512M → 1825 restarts on
 * the inactive color) re-ran `optimize` on every restart, deleting those cache
 * files out from under the *active* scheduler's per-minute `artisan` children as
 * they booted (RouteServiceProvider::boot → require(routes-v7.php)). Result:
 * 2,000+ "Failed to open stream: No such file or directory" fatals and a steady
 * trickle of "Scheduled command ... failed with exit code" — all CLI, users=0.
 *
 * FIX: the image already bakes a self-sufficient bootstrap/cache (the Dockerfile
 * runs `package:discover` so packages.php is present). Each container must use its
 * own image-baked, writable cache — never a shared/persistent volume mounted over
 * bootstrap/cache. This test fails if any service in the live blue-green stack
 * re-introduces such a mount.
 */
final class BluegreenBootstrapCacheIsolationTest extends TestCase
{
    private function composePath(): string
    {
        return dirname(__DIR__, 4) . '/compose.bluegreen.yml';
    }

    public function test_compose_file_exists(): void
    {
        $this->assertFileExists(
            $this->composePath(),
            'compose.bluegreen.yml not found — the live blue-green stack definition moved; update this guard.'
        );
    }

    public function test_no_service_mounts_a_volume_over_bootstrap_cache(): void
    {
        $contents = file_get_contents($this->composePath());
        $this->assertNotFalse($contents, 'Could not read compose.bluegreen.yml');

        $offenders = [];
        foreach (preg_split('/\R/', $contents) as $i => $line) {
            // Matches a YAML volume-mount entry binding any named/external volume
            // (or host path) over the container's bootstrap/cache directory, e.g.
            //   - nexus-php-bootstrap-cache:/var/www/html/bootstrap/cache
            // Prose/comments that merely mention the path do NOT match.
            if (preg_match('#^\s*-\s+\S+:/var/www/html/bootstrap/cache(:.*)?\s*$#', $line)) {
                $offenders[] = sprintf('  line %d: %s', $i + 1, trim($line));
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "compose.bluegreen.yml mounts a shared/persistent volume over bootstrap/cache.\n"
            . "This re-introduces the cross-container `optimize` TOCTOU (Sentry NEXUS-PHP-10).\n"
            . "Each container must use its own image-baked cache. Offending mount(s):\n"
            . implode("\n", $offenders)
        );
    }
}
