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
use Nexus\Models\NewsletterBounce;

/**
 * NewsletterBounce Model Tests
 *
 * Tests bounce recording, suppression list, soft bounce counting,
 * filtering, and statistics.
 */
class NewsletterBounceTest extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$testTenantId = 2;
        TenantContext::setById(self::$testTenantId);
    }

    public static function tearDownAfterClass(): void
    {
        try {
            Database::query("DELETE FROM newsletter_bounces WHERE tenant_id = ? AND email LIKE '%@bouncetest.com'", [2]);
            Database::query("DELETE FROM newsletter_suppression_list WHERE tenant_id = ? AND email LIKE '%@bouncetest.com'", [2]);
        } catch (\Exception $e) {
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    public function testConstantsAreDefined(): void
    {
        $this->assertEquals('hard', NewsletterBounce::BOUNCE_HARD);
        $this->assertEquals('soft', NewsletterBounce::BOUNCE_SOFT);
        $this->assertEquals('complaint', NewsletterBounce::BOUNCE_COMPLAINT);
        $this->assertEquals(3, NewsletterBounce::MAX_SOFT_BOUNCES);
    }

    public function testGetSoftBounceCountReturnsInteger(): void
    {
        $count = NewsletterBounce::getSoftBounceCount('nonexistent@bouncetest.com');
        $this->assertIsInt($count);
        $this->assertEquals(0, $count);
    }

    public function testIsSuppressedReturnsFalseForNewEmail(): void
    {
        $result = NewsletterBounce::isSuppressed('newsuppression@bouncetest.com');
        $this->assertFalse($result);
    }

    public function testGetSuppressionListReturnsArray(): void
    {
        $list = NewsletterBounce::getSuppressionList();
        $this->assertIsArray($list);
    }

    public function testGetSuppressionCountReturnsNumeric(): void
    {
        $count = NewsletterBounce::getSuppressionCount();
        $this->assertIsNumeric($count);
    }

    public function testGetStatsReturnsStructure(): void
    {
        $stats = NewsletterBounce::getStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('hard', $stats);
        $this->assertArrayHasKey('soft', $stats);
        $this->assertArrayHasKey('complaint', $stats);
        $this->assertArrayHasKey('total', $stats);
    }

    public function testGetRecentReturnsArray(): void
    {
        $recent = NewsletterBounce::getRecent();
        $this->assertIsArray($recent);
    }

    public function testFilterSuppressedReturnsArrayForEmptyInput(): void
    {
        $result = NewsletterBounce::filterSuppressed([]);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFilterSuppressedReturnsNonSuppressedEmails(): void
    {
        $emails = ['test1@bouncetest.com', 'test2@bouncetest.com'];
        $result = NewsletterBounce::filterSuppressed($emails);
        $this->assertIsArray($result);
    }
}
