<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Config;

use Tests\Laravel\TestCase;

/**
 * Every queue name that any job or queued listener dispatches to MUST be
 * consumed by a Horizon supervisor — otherwise jobs sit in Redis forever.
 *
 * Regression: SyncUserSearchIndexJob queued to 'search', which no supervisor
 * consumed, so user search-index syncs accumulated in queues:search unprocessed
 * (found with 7 stranded jobs on production, 2026-06-12).
 */
class HorizonQueueCoverageTest extends TestCase
{
    public function testEveryDeclaredQueueIsConsumedByAHorizonSupervisor(): void
    {
        $consumed = [];
        foreach (config('horizon.defaults', []) as $supervisor) {
            foreach ((array) ($supervisor['queue'] ?? []) as $queue) {
                $consumed[] = $queue;
            }
        }
        $this->assertNotEmpty($consumed, 'No Horizon supervisor queues configured');

        $declared = $this->collectDeclaredQueueNames();
        $this->assertContains('search', $declared, 'Expected SyncUserSearchIndexJob to declare the search queue');

        $orphaned = array_values(array_diff($declared, $consumed));
        $this->assertSame(
            [],
            $orphaned,
            'Queue name(s) dispatched to but consumed by no Horizon supervisor (jobs would strand in Redis): '
                . implode(', ', $orphaned)
        );
    }

    /**
     * @return string[] every literal queue name set via `public string $queue = '...'`
     *                  or `$this->onQueue('...')` in app/Jobs and app/Listeners
     */
    private function collectDeclaredQueueNames(): array
    {
        $names = [];
        foreach (['Jobs', 'Listeners'] as $dir) {
            $path = app_path($dir);
            if (!is_dir($path)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $source = (string) file_get_contents($file->getPathname());
                if (preg_match_all('/public\s+string\s+\$queue\s*=\s*[\'"]([^\'"]+)[\'"]/', $source, $m)) {
                    array_push($names, ...$m[1]);
                }
                if (preg_match_all('/->onQueue\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $source, $m)) {
                    array_push($names, ...$m[1]);
                }
            }
        }

        return array_values(array_unique($names));
    }
}
