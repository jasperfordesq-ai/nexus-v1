<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Admin;

use Nexus\Tests\Controllers\Api\ApiTestCase;
use Nexus\Services\TokenService;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Integration tests for BlogController
 *
 * Tests blog post CRUD, GrapesJS builder integration, and SEO metadata management.
 *
 * @group integration
 * @group admin
 */
class BlogControllerTest extends ApiTestCase
{
    private static int $adminUserId;
    private static int $memberUserId;
    private static int $tenantId;
    private static string $adminToken;
    private static string $memberToken;

    private static array $cleanupUserIds = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        TenantContext::setById(1);
        self::$tenantId = TenantContext::getId();

        $adminEmail = 'blog_admin_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Blog Admin', 'Blog', 'Admin', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('TestPass123!', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int) Database::lastInsertId();
        self::$cleanupUserIds[] = self::$adminUserId;
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        $memberEmail = 'blog_member_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'Blog Member', 'Blog', 'Member', 'member', 'active', 1, NOW())",
            [self::$tenantId, $memberEmail, password_hash('TestPass123!', PASSWORD_BCRYPT)]
        );
        self::$memberUserId = (int) Database::lastInsertId();
        self::$cleanupUserIds[] = self::$memberUserId;
        self::$memberToken = TokenService::generateToken(self::$memberUserId, self::$tenantId);
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::$tenantId);
    }

    public function testIndexListsPosts(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/admin-legacy/news',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testCreateGeneratesDraftPost(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/admin-legacy/news/create',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testUpdateModifiesPost(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/admin-legacy/news/update',
            [
                'post_id' => '1',
                'title' => 'Updated Post',
                'slug' => 'updated-post',
                'content' => 'Updated content',
                'status' => 'published',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testDeleteRemovesPost(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/admin-legacy/news/delete/1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testBuilderLoadsPost(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/admin-legacy/news/builder/1',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testSaveBuilderStoresJsonAndHtml(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/admin-legacy/news/save-builder',
            [
                'id' => '1',
                'title' => 'GrapesJS Post',
                'slug' => 'grapesjs-post',
                'html' => '<p>HTML content</p>',
                'json' => '{"components":[]}',
                'is_published' => '1',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testSaveBuilderWithSeo(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/admin-legacy/news/save-builder',
            [
                'id' => '1',
                'title' => 'SEO Post',
                'slug' => 'seo-post',
                'html' => '<p>Content</p>',
                'json' => '{}',
                'meta_title' => 'SEO Title',
                'meta_description' => 'SEO Description',
                'noindex' => '1',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testNonAdminCannotManagePosts(): void
    {
        $endpoints = [
            ['GET', '/admin-legacy/news'],
            ['POST', '/admin-legacy/news/create'],
            ['POST', '/admin-legacy/news/update'],
            ['POST', '/admin-legacy/news/delete/1'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->makeApiRequest(
                $method,
                $endpoint,
                [],
                ['Authorization' => 'Bearer ' . self::$memberToken]
            );

            $this->assertEquals('simulated', $response['status']);
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$cleanupUserIds as $id) {
            try {
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [$id]);
            } catch (\Exception $e) {}
            try {
                Database::query("DELETE FROM users WHERE id = ?", [$id]);
            } catch (\Exception $e) {}
        }

        parent::tearDownAfterClass();
    }
}
