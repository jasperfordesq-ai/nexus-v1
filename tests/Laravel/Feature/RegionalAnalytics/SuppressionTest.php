<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\RegionalAnalytics;

use App\Services\RegionalAnalytics\RegionalAnalyticsService;
use Tests\Laravel\TestCase;

/**
 * AG59 — N<10 segment suppression.
 *
 * Segments with fewer than RegionalAnalyticsService::N_SUPPRESS members
 * MUST return null (rendered as "—" in the dashboard / PDF).
 */
class SuppressionTest extends TestCase
{
    public function test_count_below_threshold_is_suppressed(): void
    {
        $this->assertNull(RegionalAnalyticsService::bucketCount(0));
        $this->assertNull(RegionalAnalyticsService::bucketCount(1));
        $this->assertNull(RegionalAnalyticsService::bucketCount(9));
        $this->assertNull(RegionalAnalyticsService::bucketCount(null));
    }

    public function test_count_at_threshold_is_not_suppressed(): void
    {
        $this->assertNotNull(RegionalAnalyticsService::bucketCount(RegionalAnalyticsService::N_SUPPRESS));
        $this->assertSame('<50', RegionalAnalyticsService::bucketCount(10));
    }

    public function test_hours_with_small_sample_are_suppressed(): void
    {
        $this->assertNull(RegionalAnalyticsService::roundHours(123.4, 5));
    }

    public function test_hours_round_to_nearest_ten_when_sample_sufficient(): void
    {
        $this->assertSame(120, RegionalAnalyticsService::roundHours(123.4, 50));
        $this->assertSame(130, RegionalAnalyticsService::roundHours(127.0, 50));
    }
}
