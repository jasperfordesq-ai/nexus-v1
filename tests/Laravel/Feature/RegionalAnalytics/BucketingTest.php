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
 * AG59 — Verifies privacy bucketing.
 *
 * A small tenant with N>=10 active members must return the bucket label
 * ("<50") rather than the raw count.
 */
class BucketingTest extends TestCase
{
    public function test_small_count_returns_under_50_bucket(): void
    {
        $this->assertSame('<50', RegionalAnalyticsService::bucketCount(15));
        $this->assertSame('<50', RegionalAnalyticsService::bucketCount(49));
    }

    public function test_mid_count_returns_50_to_200_bucket(): void
    {
        $this->assertSame('50-200', RegionalAnalyticsService::bucketCount(50));
        $this->assertSame('50-200', RegionalAnalyticsService::bucketCount(199));
    }

    public function test_large_count_returns_over_1000_bucket(): void
    {
        $this->assertSame('>1000', RegionalAnalyticsService::bucketCount(5000));
    }

    public function test_bucket_is_never_a_raw_number(): void
    {
        // Bucket strings must never look like the raw input.
        foreach ([10, 11, 25, 49, 50, 199, 200, 999, 1000, 12345] as $n) {
            $b = RegionalAnalyticsService::bucketCount($n);
            $this->assertNotNull($b);
            $this->assertNotSame((string) $n, $b, "Bucket for $n must not equal raw count");
        }
    }
}
