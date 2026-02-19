<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Models\User;

/**
 * Base Test Case for API Controllers
 *
 * Provides common utilities for testing API endpoints including
 * authentication, request simulation, and response validation.
 */
abstract class ApiTestCase extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?string $testUserEmail = null;
    protected static ?string $testAuthToken = null;

    /**
     * Set up test user and authentication before all tests
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Set default tenant
        self::$testTenantId = 1;
        TenantContext::setById(self::$testTenantId);

        // Create test user for API authentication
        self::createTestUser();
    }

    /**
     * Create a test user for API authentication
     */
    protected static function createTestUser(): void
    {
        $timestamp = time();
        self::$testUserEmail = "api_test_user_{$timestamp}@test.com";

        Database::query(
            "INSERT INTO users (tenant_id, email, username, name, first_name, last_name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                self::$testUserEmail,
                "api_test_user_{$timestamp}",
                'API TestUser',
                'API',
                'TestUser',
                100
            ]
        );

        self::$testUserId = (int)Database::getInstance()->lastInsertId();

        // Generate auth token (simulated)
        self::$testAuthToken = bin2hex(random_bytes(32));
    }

    /**
     * Clean up test user after all tests
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            // Clean up related data
            Database::query("DELETE FROM transactions WHERE sender_id = ? OR receiver_id = ?", [self::$testUserId, self::$testUserId]);
            Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]);
            Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUserId]);
            Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
        }

        parent::tearDownAfterClass();
    }

    /**
     * Simulate an authenticated API request
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $endpoint API endpoint path
     * @param array $data Request data
     * @param array $headers Additional headers
     * @return array Simulated response
     */
    protected function makeApiRequest(string $method, string $endpoint, array $data = [], array $headers = []): array
    {
        // Simulate authentication by setting session
        $_SESSION['user_id'] = self::$testUserId;
        $_SESSION['tenant_id'] = self::$testTenantId;

        // Merge default headers
        $headers = array_merge([
            'Authorization' => 'Bearer ' . self::$testAuthToken,
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string)self::$testTenantId,
        ], $headers);

        // Set request method and data
        $_SERVER['REQUEST_METHOD'] = $method;

        if ($method === 'GET') {
            $_GET = $data;
        } else {
            $_POST = $data;
        }

        return [
            'method' => $method,
            'endpoint' => $endpoint,
            'data' => $data,
            'headers' => $headers,
            'status' => 'simulated'
        ];
    }

    /**
     * Make a GET request to an API endpoint
     */
    protected function get(string $endpoint, array $params = [], array $headers = []): array
    {
        return $this->makeApiRequest('GET', $endpoint, $params, $headers);
    }

    /**
     * Make a POST request to an API endpoint
     */
    protected function post(string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->makeApiRequest('POST', $endpoint, $data, $headers);
    }

    /**
     * Make a PUT request to an API endpoint
     */
    protected function put(string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->makeApiRequest('PUT', $endpoint, $data, $headers);
    }

    /**
     * Make a DELETE request to an API endpoint
     */
    protected function delete(string $endpoint, array $data = [], array $headers = []): array
    {
        return $this->makeApiRequest('DELETE', $endpoint, $data, $headers);
    }

    /**
     * Assert that response has expected JSON structure
     */
    protected function assertJsonStructure(array $expected, array $actual, string $message = ''): void
    {
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $actual, $message ?: "JSON should have key: {$key}");
        }
    }

    /**
     * Assert that response indicates success
     */
    protected function assertSuccess(array $response, string $message = ''): void
    {
        if (isset($response['success'])) {
            $this->assertTrue($response['success'], $message ?: 'Response should indicate success');
        }
        if (isset($response['error'])) {
            $this->assertFalse($response['error'], $message ?: 'Response should not have error');
        }
    }

    /**
     * Assert that response indicates error
     */
    protected function assertError(array $response, string $message = ''): void
    {
        if (isset($response['success'])) {
            $this->assertFalse($response['success'], $message ?: 'Response should indicate failure');
        }
        if (isset($response['error'])) {
            $this->assertTrue($response['error'], $message ?: 'Response should have error');
        }
    }

    /**
     * Create additional test user
     */
    protected function createUser(array $attributes = []): array
    {
        $timestamp = time() . rand(1000, 9999);
        $defaults = [
            'tenant_id' => self::$testTenantId,
            'email' => "test_user_{$timestamp}@test.com",
            'username' => "test_user_{$timestamp}",
            'first_name' => 'Test',
            'last_name' => 'User',
            'balance' => 50,
            'is_approved' => 1
        ];

        $data = array_merge($defaults, $attributes);

        Database::query(
            "INSERT INTO users (tenant_id, email, username, name, first_name, last_name, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['tenant_id'],
                $data['email'],
                $data['username'],
                $data['first_name'] . ' ' . $data['last_name'],
                $data['first_name'],
                $data['last_name'],
                $data['balance'],
                $data['is_approved']
            ]
        );

        $data['id'] = (int)Database::getInstance()->lastInsertId();
        return $data;
    }

    /**
     * Clean up a created user and related data
     */
    protected function cleanupUser(int $userId): void
    {
        Database::query("DELETE FROM transactions WHERE sender_id = ? OR receiver_id = ?", [$userId, $userId]);
        Database::query("DELETE FROM notifications WHERE user_id = ?", [$userId]);
        Database::query("DELETE FROM activity_log WHERE user_id = ?", [$userId]);
        Database::query("DELETE FROM users WHERE id = ?", [$userId]);
    }

    /**
     * Assert that array contains specific keys
     */
    protected function assertArrayHasKeys(array $keys, array $array, string $message = ''): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, $message ?: "Array should have key: {$key}");
        }
    }
}
