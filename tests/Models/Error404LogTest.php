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
use Nexus\Models\Error404Log;

/**
 * Error404Log Model Tests
 *
 * Tests 404 error logging, retrieval, search, stats,
 * resolve/unresolve, and cleanup.
 */
class Error404LogTest extends DatabaseTestCase
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
            Database::query("DELETE FROM error_404_log WHERE url LIKE '/test-404-%'");
        } catch (\Exception $e) {
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$testTenantId);
    }

    // ==========================================
    // Log Tests
    // ==========================================

    public function testLogCreatesNewEntry(): void
    {
        $url = '/test-404-' . time() . '/page';
        Error404Log::log($url, 'https://example.com', 'TestAgent', '127.0.0.1');

        $results = Error404Log::search(basename($url));
        $this->assertNotEmpty($results);
    }

    public function testLogIncrementsHitCountForExisting(): void
    {
        $url = '/test-404-increment-' . time();
        Error404Log::log($url);
        Error404Log::log($url);

        $results = Error404Log::search(basename($url));
        $found = false;
        foreach ($results as $r) {
            if ($r['url'] === $url) {
                $this->assertGreaterThanOrEqual(2, (int)$r['hit_count']);
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should find the logged URL');
    }

    // ==========================================
    // GetById Tests
    // ==========================================

    public function testGetByIdReturnsEntry(): void
    {
        $url = '/test-404-getbyid-' . time();
        Error404Log::log($url);

        $results = Error404Log::search(basename($url));
        if (!empty($results)) {
            $entry = Error404Log::getById($results[0]['id']);
            $this->assertNotFalse($entry);
            $this->assertEquals($url, $entry['url']);
        }
    }

    public function testGetByIdReturnsFalseForNonExistent(): void
    {
        $entry = Error404Log::getById(999999999);
        $this->assertFalse($entry);
    }

    // ==========================================
    // GetAll Tests
    // ==========================================

    public function testGetAllReturnsStructure(): void
    {
        $result = Error404Log::getAll();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('page', $result);
        $this->assertArrayHasKey('per_page', $result);
        $this->assertArrayHasKey('total_pages', $result);
    }

    // ==========================================
    // GetTopErrors Tests
    // ==========================================

    public function testGetTopErrorsReturnsArray(): void
    {
        $top = Error404Log::getTopErrors();
        $this->assertIsArray($top);
    }

    // ==========================================
    // MarkResolved / MarkUnresolved Tests
    // ==========================================

    public function testMarkResolvedAndUnresolved(): void
    {
        $url = '/test-404-resolve-' . time();
        Error404Log::log($url);

        $results = Error404Log::search(basename($url));
        if (!empty($results)) {
            $id = $results[0]['id'];

            Error404Log::markResolved($id, null, 'Test resolution');
            $entry = Error404Log::getById($id);
            $this->assertEquals(1, (int)$entry['resolved']);

            Error404Log::markUnresolved($id);
            $entry = Error404Log::getById($id);
            $this->assertEquals(0, (int)$entry['resolved']);
        }
    }

    // ==========================================
    // GetStats Tests
    // ==========================================

    public function testGetStatsReturnsStructure(): void
    {
        $stats = Error404Log::getStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('unresolved', $stats);
        $this->assertArrayHasKey('resolved', $stats);
        $this->assertArrayHasKey('total_hits', $stats);
        $this->assertArrayHasKey('recent_24h', $stats);
    }

    // ==========================================
    // Search Tests
    // ==========================================

    public function testSearchReturnsArray(): void
    {
        $results = Error404Log::search('nonexistent-xyz');
        $this->assertIsArray($results);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesEntry(): void
    {
        $url = '/test-404-delete-' . time();
        Error404Log::log($url);

        $results = Error404Log::search(basename($url));
        if (!empty($results)) {
            $id = $results[0]['id'];
            Error404Log::delete($id);

            $entry = Error404Log::getById($id);
            $this->assertFalse($entry);
        }
    }

    // ==========================================
    // CleanOldResolved Tests
    // ==========================================

    public function testCleanOldResolvedReturnsInt(): void
    {
        $deleted = Error404Log::cleanOldResolved(9999);
        $this->assertIsInt($deleted);
    }
}
