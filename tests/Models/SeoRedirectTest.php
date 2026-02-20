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
use Nexus\Models\SeoRedirect;

/**
 * SeoRedirect Model Tests
 *
 * Tests redirect CRUD, source URL lookup, hit tracking,
 * and redirect checking.
 */
class SeoRedirectTest extends DatabaseTestCase
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
            Database::query("DELETE FROM seo_redirects WHERE tenant_id = ? AND source_url LIKE '/test-redirect-%'", [2]);
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
    // Create Tests
    // ==========================================

    public function testCreateReturnsId(): void
    {
        $id = SeoRedirect::create('/test-redirect-create-' . time(), '/destination');
        $this->assertIsNumeric($id);
    }

    // ==========================================
    // All Tests
    // ==========================================

    public function testAllReturnsArray(): void
    {
        $all = SeoRedirect::all();
        $this->assertIsArray($all);
    }

    // ==========================================
    // FindBySource Tests
    // ==========================================

    public function testFindBySourceReturnsRedirect(): void
    {
        $source = '/test-redirect-find-' . time();
        SeoRedirect::create($source, '/destination-found');

        $redirect = SeoRedirect::findBySource($source);
        $this->assertNotNull($redirect);
        $this->assertEquals($source, $redirect['source_url']);
        $this->assertEquals('/destination-found', $redirect['destination_url']);
    }

    public function testFindBySourceReturnsNullForNonExistent(): void
    {
        $redirect = SeoRedirect::findBySource('/nonexistent-redirect-' . time());
        $this->assertNull($redirect);
    }

    // ==========================================
    // Delete Tests
    // ==========================================

    public function testDeleteRemovesRedirect(): void
    {
        $source = '/test-redirect-delete-' . time();
        $id = SeoRedirect::create($source, '/destination-delete');

        SeoRedirect::delete($id);

        $redirect = SeoRedirect::findBySource($source);
        $this->assertNull($redirect);
    }

    // ==========================================
    // IncrementHits Tests
    // ==========================================

    public function testIncrementHitsIncreasesCounter(): void
    {
        $source = '/test-redirect-hits-' . time();
        $id = SeoRedirect::create($source, '/destination-hits');

        $before = SeoRedirect::findBySource($source);
        $beforeHits = (int)$before['hits'];

        SeoRedirect::incrementHits($id);

        $after = SeoRedirect::findBySource($source);
        $this->assertEquals($beforeHits + 1, (int)$after['hits']);
    }

    // ==========================================
    // CheckRedirect Tests
    // ==========================================

    public function testCheckRedirectReturnsDestination(): void
    {
        $source = '/test-redirect-check-' . time();
        SeoRedirect::create($source, '/destination-check');

        $destination = SeoRedirect::checkRedirect($source);
        $this->assertEquals('/destination-check', $destination);
    }

    public function testCheckRedirectReturnsNullForNonExistent(): void
    {
        $destination = SeoRedirect::checkRedirect('/nonexistent-check-' . time());
        $this->assertNull($destination);
    }

    public function testCheckRedirectIncrementsHits(): void
    {
        $source = '/test-redirect-checkhits-' . time();
        SeoRedirect::create($source, '/destination-checkhits');

        // First check
        SeoRedirect::checkRedirect($source);
        $redirect = SeoRedirect::findBySource($source);
        $this->assertEquals(1, (int)$redirect['hits']);

        // Second check
        SeoRedirect::checkRedirect($source);
        $redirect = SeoRedirect::findBySource($source);
        $this->assertEquals(2, (int)$redirect['hits']);
    }
}
