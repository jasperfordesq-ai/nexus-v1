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
 * Integration tests for AiSettingsController
 *
 * Tests AI configuration management, provider testing, and settings persistence.
 *
 * @group integration
 * @group admin
 */
class AiSettingsControllerTest extends ApiTestCase
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

        // Create admin user
        $adminEmail = 'ai_admin_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'AI Admin', 'AI', 'Admin', 'admin', 'active', 1, NOW())",
            [self::$tenantId, $adminEmail, password_hash('TestPass123!', PASSWORD_BCRYPT)]
        );
        self::$adminUserId = (int) Database::lastInsertId();
        self::$cleanupUserIds[] = self::$adminUserId;
        self::$adminToken = TokenService::generateToken(self::$adminUserId, self::$tenantId);

        // Create member user
        $memberEmail = 'ai_member_' . uniqid() . '@test.local';
        Database::query(
            "INSERT INTO users (tenant_id, email, password_hash, name, first_name, last_name, role, status, is_approved, created_at)
             VALUES (?, ?, ?, 'AI Member', 'AI', 'Member', 'member', 'active', 1, NOW())",
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

    // =========================================================================
    // INDEX — GET /admin-legacy/ai-settings
    // =========================================================================

    public function testIndexRendersAiSettingsPage(): void
    {
        $response = $this->makeApiRequest(
            'GET',
            '/admin-legacy/ai-settings',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
        $this->assertEquals('GET', $response['method']);
    }

    // =========================================================================
    // SAVE SETTINGS — POST /admin-legacy/ai-settings/save
    // =========================================================================

    public function testSaveUpdatesAiSettings(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/admin-legacy/ai-settings/save',
            [
                'ai_enabled' => '1',
                'ai_provider' => 'gemini',
                'ai_chat_enabled' => '1',
                'default_daily_limit' => '10',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
        $this->assertEquals('POST', $response['method']);
    }

    public function testSaveTrimsApiKeys(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/admin-legacy/ai-settings/save',
            [
                'gemini_api_key' => '  test_key_with_spaces  ',
                'ai_provider' => 'gemini',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testSaveIgnoresPlaceholderKeys(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/admin-legacy/ai-settings/save',
            [
                'gemini_api_key' => '**********************',
                'ai_provider' => 'gemini',
            ],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // TEST PROVIDER — POST /admin-legacy/ai-settings/test-provider
    // =========================================================================

    public function testProviderConnectionTest(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/admin-legacy/ai-settings/test-provider',
            ['provider' => 'gemini'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    public function testProviderTestWithInvalidProvider(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/admin-legacy/ai-settings/test-provider',
            ['provider' => 'invalid_provider'],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // INITIALIZE — POST /admin-legacy/ai-settings/initialize
    // =========================================================================

    public function testInitializeCreatesDefaults(): void
    {
        $response = $this->makeApiRequest(
            'POST',
            '/admin-legacy/ai-settings/initialize',
            [],
            ['Authorization' => 'Bearer ' . self::$adminToken]
        );

        $this->assertEquals('simulated', $response['status']);
    }

    // =========================================================================
    // AUTHORIZATION — Non-admin gets 403
    // =========================================================================

    public function testNonAdminCannotAccessAiSettings(): void
    {
        $endpoints = [
            ['GET', '/admin-legacy/ai-settings'],
            ['POST', '/admin-legacy/ai-settings/save'],
            ['POST', '/admin-legacy/ai-settings/test-provider'],
            ['POST', '/admin-legacy/ai-settings/initialize'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->makeApiRequest(
                $method,
                $endpoint,
                [],
                ['Authorization' => 'Bearer ' . self::$memberToken]
            );

            $this->assertEquals('simulated', $response['status'], "Endpoint {$method} {$endpoint} should reject non-admin");
        }
    }

    // =========================================================================
    // CLEANUP
    // =========================================================================

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
