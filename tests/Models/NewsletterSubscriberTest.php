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
use Nexus\Models\NewsletterSubscriber;

/**
 * NewsletterSubscriber Model Tests
 *
 * Tests subscriber creation, lookup, confirmation, unsubscription,
 * counting, and stats.
 */
class NewsletterSubscriberTest extends DatabaseTestCase
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
            Database::query("DELETE FROM newsletter_subscribers WHERE tenant_id = ? AND email LIKE '%@subtest.com'", [2]);
        } catch (\Exception $e) {
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    public function testCountReturnsNumeric(): void
    {
        $count = NewsletterSubscriber::count();
        $this->assertIsNumeric($count);
    }

    public function testCountWithStatusReturnsNumeric(): void
    {
        $count = NewsletterSubscriber::count('active');
        $this->assertIsNumeric($count);
    }

    public function testGetStatsReturnsStructure(): void
    {
        $stats = NewsletterSubscriber::getStats();
        $this->assertNotFalse($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('unsubscribed', $stats);
    }

    public function testFindByEmailReturnsNullishForNonExistent(): void
    {
        $result = NewsletterSubscriber::findByEmail('nonexistent-xyz@subtest.com');
        $this->assertEmpty($result);
    }

    public function testFindByIdReturnsNullishForNonExistent(): void
    {
        $result = NewsletterSubscriber::findById(999999999);
        $this->assertEmpty($result);
    }

    public function testFindByConfirmationTokenReturnsNullishForBadToken(): void
    {
        $result = NewsletterSubscriber::findByConfirmationToken('nonexistent-token-xyz');
        $this->assertEmpty($result);
    }

    public function testConfirmReturnsFalseForBadToken(): void
    {
        $result = NewsletterSubscriber::confirm('bad-token-xyz');
        $this->assertFalse($result);
    }

    public function testUnsubscribeReturnsFalseForBadToken(): void
    {
        $result = NewsletterSubscriber::unsubscribe('bad-unsub-token-xyz');
        $this->assertFalse($result);
    }

    public function testGetActiveReturnsArray(): void
    {
        $active = NewsletterSubscriber::getActive();
        $this->assertIsArray($active);
    }

    public function testGetAllReturnsArray(): void
    {
        $all = NewsletterSubscriber::getAll();
        $this->assertIsArray($all);
    }

    public function testExportReturnsArray(): void
    {
        $exported = NewsletterSubscriber::export();
        $this->assertIsArray($exported);
    }

    public function testImportReturnsStats(): void
    {
        $result = NewsletterSubscriber::import([
            ['email' => 'invalid-email'],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('imported', $result);
        $this->assertArrayHasKey('skipped', $result);
        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(1, $result['skipped']);
    }
}
