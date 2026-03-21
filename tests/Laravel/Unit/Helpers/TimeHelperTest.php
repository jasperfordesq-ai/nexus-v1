<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Helpers;

use App\Helpers\TimeHelper;
use PHPUnit\Framework\TestCase;

class TimeHelperTest extends TestCase
{
    // -------------------------------------------------------
    // timeAgo()
    // -------------------------------------------------------

    public function test_timeAgo_just_now_for_recent(): void
    {
        $result = TimeHelper::timeAgo(time());
        $this->assertSame('Just now', $result);
    }

    public function test_timeAgo_minutes_ago(): void
    {
        $result = TimeHelper::timeAgo(time() - 120);
        $this->assertSame('2 minutes ago', $result);
    }

    public function test_timeAgo_one_minute_ago(): void
    {
        $result = TimeHelper::timeAgo(time() - 60);
        $this->assertSame('1 minute ago', $result);
    }

    public function test_timeAgo_hours_ago(): void
    {
        $result = TimeHelper::timeAgo(time() - 7200);
        $this->assertSame('2 hours ago', $result);
    }

    public function test_timeAgo_one_hour_ago(): void
    {
        $result = TimeHelper::timeAgo(time() - 3600);
        $this->assertSame('1 hour ago', $result);
    }

    public function test_timeAgo_days_ago(): void
    {
        $result = TimeHelper::timeAgo(time() - 86400 * 3);
        $this->assertSame('3 days ago', $result);
    }

    public function test_timeAgo_one_day_ago(): void
    {
        $result = TimeHelper::timeAgo(time() - 86400);
        $this->assertSame('1 day ago', $result);
    }

    public function test_timeAgo_weeks_ago(): void
    {
        $result = TimeHelper::timeAgo(time() - 604800 * 2);
        $this->assertSame('2 weeks ago', $result);
    }

    public function test_timeAgo_months_ago(): void
    {
        $result = TimeHelper::timeAgo(time() - 2592000 * 3);
        $this->assertSame('3 months ago', $result);
    }

    public function test_timeAgo_years_ago(): void
    {
        $result = TimeHelper::timeAgo(time() - 31536000 * 2);
        $this->assertSame('2 years ago', $result);
    }

    public function test_timeAgo_with_datetime_string(): void
    {
        $result = TimeHelper::timeAgo(date('Y-m-d H:i:s', time() - 3600));
        $this->assertSame('1 hour ago', $result);
    }

    public function test_timeAgo_with_invalid_datetime_returns_unknown(): void
    {
        $result = TimeHelper::timeAgo('not-a-date');
        $this->assertSame('Unknown', $result);
    }

    public function test_timeAgo_future_datetime_returns_just_now(): void
    {
        $result = TimeHelper::timeAgo(time() + 3600);
        $this->assertSame('Just now', $result);
    }

    // -------------------------------------------------------
    // format()
    // -------------------------------------------------------

    public function test_format_with_default_format(): void
    {
        $result = TimeHelper::format('2026-01-15 10:30:00');
        $this->assertSame('Jan 15, 2026', $result);
    }

    public function test_format_with_custom_format(): void
    {
        $result = TimeHelper::format('2026-01-15 10:30:00', 'Y-m-d');
        $this->assertSame('2026-01-15', $result);
    }

    public function test_format_with_unix_timestamp(): void
    {
        $ts = mktime(10, 30, 0, 1, 15, 2026);
        $result = TimeHelper::format($ts);
        $this->assertSame('Jan 15, 2026', $result);
    }

    public function test_format_with_invalid_datetime_returns_unknown(): void
    {
        $result = TimeHelper::format('not-a-date');
        $this->assertSame('Unknown', $result);
    }

    // -------------------------------------------------------
    // formatWithTime()
    // -------------------------------------------------------

    public function test_formatWithTime_returns_date_and_time(): void
    {
        $result = TimeHelper::formatWithTime('2026-01-15 15:30:00');
        $this->assertStringContainsString('Jan 15, 2026', $result);
        $this->assertStringContainsString('at', $result);
        $this->assertStringContainsString('3:30 PM', $result);
    }

    public function test_formatWithTime_with_unix_timestamp(): void
    {
        $ts = mktime(9, 0, 0, 6, 1, 2026);
        $result = TimeHelper::formatWithTime($ts);
        $this->assertStringContainsString('Jun 1, 2026', $result);
        $this->assertStringContainsString('9:00 AM', $result);
    }

    public function test_formatWithTime_with_invalid_datetime_returns_unknown(): void
    {
        $result = TimeHelper::formatWithTime('garbage');
        $this->assertSame('Unknown', $result);
    }
}
