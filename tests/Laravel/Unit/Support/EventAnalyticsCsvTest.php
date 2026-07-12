<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Support;

use App\Support\Events\EventAnalyticsCsv;
use PHPUnit\Framework\TestCase;

final class EventAnalyticsCsvTest extends TestCase
{
    public function test_formula_like_values_are_escaped_and_privacy_records_remain_explicit(): void
    {
        $rows = EventAnalyticsCsv::rows([
            'event_title' => '=HYPERLINK("https://example.invalid")',
            'safe_title' => 'Community event',
            'leading_formula' => '  @SUM(1,1)',
            'enabled' => true,
            'optional' => [
                'value' => null,
                'suppressed' => true,
            ],
        ]);

        self::assertContains([
            'event_title',
            "'=HYPERLINK(\"https://example.invalid\")",
            '0',
        ], $rows);
        self::assertContains(['safe_title', 'Community event', '0'], $rows);
        self::assertContains(['leading_formula', "'  @SUM(1,1)", '0'], $rows);
        self::assertContains(['enabled', '1', '0'], $rows);
        self::assertContains(['optional', '', '1'], $rows);
    }
}
