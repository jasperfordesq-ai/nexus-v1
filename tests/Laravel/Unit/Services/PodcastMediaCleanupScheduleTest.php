<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use PHPUnit\Framework\TestCase;

/** Ensure the durable podcast cleanup ledger always has a retry dispatcher. */
class PodcastMediaCleanupScheduleTest extends TestCase
{
    public function test_cleanup_dispatcher_has_cluster_safe_minute_schedule(): void
    {
        $source = file_get_contents(dirname(__DIR__, 4) . '/bootstrap/app.php');
        self::assertIsString($source);

        $start = strpos($source, 'podcasts:dispatch-media-cleanup --limit=100');
        self::assertNotFalse($start);
        $block = substr($source, $start, 320);

        self::assertStringContainsString('->everyMinute()', $block);
        self::assertStringContainsString('->withoutOverlapping(10)', $block);
        self::assertStringContainsString('->onOneServer()', $block);
        self::assertStringContainsString("->name('podcasts-dispatch-media-cleanup')", $block);
    }
}
