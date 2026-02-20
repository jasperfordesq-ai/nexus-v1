<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Models;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\NewsletterAnalytics;

/**
 * NewsletterAnalytics Model Tests
 *
 * Tests engagement analytics methods: engagement by hour/day,
 * activity counting, and heatmap generation.
 */
class NewsletterAnalyticsTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    public function testGetEngagementByHourReturns24Entries(): void
    {
        $data = NewsletterAnalytics::getEngagementByHour();
        $this->assertIsArray($data);
        $this->assertCount(24, $data);

        foreach ($data as $entry) {
            $this->assertArrayHasKey('hour', $entry);
            $this->assertArrayHasKey('opens', $entry);
            $this->assertArrayHasKey('clicks', $entry);
        }
    }

    public function testGetEngagementByDayReturns7Entries(): void
    {
        $data = NewsletterAnalytics::getEngagementByDay();
        $this->assertIsArray($data);
        $this->assertCount(7, $data);

        foreach ($data as $entry) {
            $this->assertArrayHasKey('day_num', $entry);
            $this->assertArrayHasKey('day_name', $entry);
            $this->assertArrayHasKey('opens', $entry);
            $this->assertArrayHasKey('clicks', $entry);
        }
    }

    public function testCountAllActivityReturnsNumeric(): void
    {
        $count = NewsletterAnalytics::countAllActivity(999999999);
        $this->assertIsNumeric($count);
    }

    public function testCountAllActivityByTypeReturnsNumeric(): void
    {
        $openCount = NewsletterAnalytics::countAllActivity(999999999, 'open');
        $this->assertIsNumeric($openCount);

        $clickCount = NewsletterAnalytics::countAllActivity(999999999, 'click');
        $this->assertIsNumeric($clickCount);
    }

    public function testGetAllActivityReturnsArray(): void
    {
        $activity = NewsletterAnalytics::getAllActivity(999999999);
        $this->assertIsArray($activity);
    }

    public function testGetSendTimeHeatmapReturnsStructure(): void
    {
        $heatmap = NewsletterAnalytics::getSendTimeHeatmap();
        $this->assertIsArray($heatmap);
        $this->assertArrayHasKey('heatmap', $heatmap);
        $this->assertArrayHasKey('max_value', $heatmap);
        $this->assertArrayHasKey('days', $heatmap);
        $this->assertCount(7, $heatmap['days']);
    }

    public function testGetOptimalSendTimesReturnsStructure(): void
    {
        $result = NewsletterAnalytics::getOptimalSendTimes();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('recommendations', $result);
    }

    public function testGetDetailsReturnsStructure(): void
    {
        $details = NewsletterAnalytics::getDetails(999999999);
        $this->assertIsArray($details);
        $this->assertArrayHasKey('opens_over_time', $details);
        $this->assertArrayHasKey('top_links', $details);
        $this->assertArrayHasKey('recent_activity', $details);
        $this->assertArrayHasKey('device_stats', $details);
    }

    public function testGetEmailClientsReturnsArray(): void
    {
        $clients = NewsletterAnalytics::getEmailClients(999999999);
        $this->assertIsArray($clients);
    }

    public function testGetOpenersReturnsArray(): void
    {
        $openers = NewsletterAnalytics::getOpeners(999999999);
        $this->assertIsArray($openers);
    }

    public function testGetClickersReturnsArray(): void
    {
        $clickers = NewsletterAnalytics::getClickers(999999999);
        $this->assertIsArray($clickers);
    }
}
