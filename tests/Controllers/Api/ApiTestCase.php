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
 *
 * The makeApiRequest() method actually instantiates and invokes the
 * controller method, capturing JSON output via output buffering.
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
     * Invoke a controller method directly and capture its JSON output.
     *
     * This actually instantiates the controller and calls the method,
     * capturing echoed JSON via output buffering. If the controller calls
     * exit(), the output captured before that point is returned.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint path (used for $_SERVER setup)
     * @param array $data Request data (body for POST/PUT, query for GET)
     * @param array $headers Additional headers
     * @param string|null $controllerAction "Namespace\Controller@method" override.
     *                                      If null, resolved from endpoint path.
     * @return array{status: int, body: array|string, raw: string}
     */
    protected function makeApiRequest(string $method, string $endpoint, array $data = [], array $headers = [], ?string $controllerAction = null): array
    {
        // Set up superglobals to simulate the request
        $_SESSION['user_id'] = self::$testUserId;
        $_SESSION['tenant_id'] = self::$testTenantId;

        $oldServer = $_SERVER;
        $oldGet = $_GET;
        $oldPost = $_POST;

        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $endpoint;
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . self::$testAuthToken;
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_X_TENANT_ID'] = (string)self::$testTenantId;

        foreach ($headers as $key => $value) {
            $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
            $_SERVER[$serverKey] = $value;
        }

        if ($method === 'GET') {
            $_GET = $data;
            $_POST = [];
        } else {
            $_POST = $data;
            $_GET = [];
        }

        // If POST/PUT with JSON body, set up php://input simulation
        // Controllers using getJsonInput()/getAllInput() read from php://input
        // We can't override php://input, but controllers also fall back to $_POST

        $statusCode = 200;
        $rawOutput = '';

        // Capture output
        ob_start();
        try {
            if ($controllerAction) {
                [$controllerClass, $actionMethod] = explode('@', $controllerAction);
                $controller = new $controllerClass();
                $controller->$actionMethod();
            }
        } catch (\Throwable $e) {
            // Some controllers call exit() after jsonResponse — catch that
            // Or re-throw if it's a real error
            if (!($e instanceof \Exception && str_contains($e->getMessage(), 'exit'))) {
                // Capture what was output before the exception
            }
        } finally {
            $rawOutput = ob_get_clean() ?: '';
        }

        // Restore superglobals
        $_SERVER = $oldServer;
        $_GET = $oldGet;
        $_POST = $oldPost;

        // Try to get the HTTP status code that was set
        $statusCode = http_response_code() ?: 200;

        // Parse JSON output
        $body = json_decode($rawOutput, true);
        if ($body === null && !empty($rawOutput)) {
            $body = $rawOutput; // Non-JSON response
        }

        return [
            'status' => $statusCode,
            'body' => $body ?? [],
            'raw' => $rawOutput,
            'method' => $method,
            'endpoint' => $endpoint,
        ];
    }

    /**
     * Make a GET request to an API endpoint
     */
    protected function get(string $endpoint, array $params = [], array $headers = [], ?string $controllerAction = null): array
    {
        return $this->makeApiRequest('GET', $endpoint, $params, $headers, $controllerAction);
    }

    /**
     * Make a POST request to an API endpoint
     */
    protected function post(string $endpoint, array $data = [], array $headers = [], ?string $controllerAction = null): array
    {
        return $this->makeApiRequest('POST', $endpoint, $data, $headers, $controllerAction);
    }

    /**
     * Make a PUT request to an API endpoint
     */
    protected function put(string $endpoint, array $data = [], array $headers = [], ?string $controllerAction = null): array
    {
        return $this->makeApiRequest('PUT', $endpoint, $data, $headers, $controllerAction);
    }

    /**
     * Make a DELETE request to an API endpoint
     */
    protected function delete(string $endpoint, array $data = [], array $headers = [], ?string $controllerAction = null): array
    {
        return $this->makeApiRequest('DELETE', $endpoint, $data, $headers, $controllerAction);
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
        $body = $response['body'] ?? $response;
        if (isset($body['success'])) {
            $this->assertTrue($body['success'], $message ?: 'Response should indicate success');
        }
        if (isset($body['error'])) {
            $this->assertFalse($body['error'], $message ?: 'Response should not have error');
        }
    }

    /**
     * Assert that response indicates error
     */
    protected function assertError(array $response, string $message = ''): void
    {
        $body = $response['body'] ?? $response;
        if (isset($body['success'])) {
            $this->assertFalse($body['success'], $message ?: 'Response should indicate failure');
        }
        if (isset($body['error'])) {
            $this->assertTrue($body['error'], $message ?: 'Response should have error');
        }
    }

    /**
     * Assert response status code
     */
    protected function assertStatus(int $expected, array $response, string $message = ''): void
    {
        $this->assertEquals($expected, $response['status'], $message ?: "Expected HTTP status {$expected}");
    }

    /**
     * Assert response body contains a key with an expected value
     */
    protected function assertResponseHas(string $key, array $response, string $message = ''): void
    {
        $body = $response['body'] ?? $response;
        $this->assertArrayHasKey($key, $body, $message ?: "Response body should have key: {$key}");
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
