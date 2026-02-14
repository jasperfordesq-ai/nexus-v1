<?php

declare(strict_types=1);

namespace Nexus\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * HTTP Integration Test Case
 *
 * Base class for tests that make real HTTP requests to the application.
 * Uses Guzzle HTTP client to make actual requests and capture responses.
 *
 * This replaces the simulated API testing approach with real integration tests
 * that exercise the full HTTP stack including routing, middleware, and controllers.
 */
abstract class HttpIntegrationTestCase extends DatabaseTestCase
{
    protected static ?Client $httpClient = null;
    protected static string $baseUrl = 'http://staging.timebank.local';
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?string $testUserEmail = null;
    protected static ?string $testAuthToken = null;
    protected static ?string $testTenantSlug = null;

    /**
     * Set up HTTP client and test user before all tests
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Initialize HTTP client
        self::$httpClient = new Client([
            'base_uri' => self::$baseUrl,
            'http_errors' => false, // Don't throw on 4xx/5xx
            'timeout' => 30,
            'cookies' => true, // Enable cookie jar for session persistence
            'allow_redirects' => [
                'max' => 5,
                'track_redirects' => true,
            ],
        ]);

        // Set default tenant
        self::$testTenantId = 2; // hour-timebank tenant
        self::$testTenantSlug = 'hour-timebank';
        TenantContext::setById(self::$testTenantId);

        // Create test user for API authentication
        self::createTestUser();
        self::generateAuthToken();
    }

    /**
     * Create a test user for API authentication
     */
    protected static function createTestUser(): void
    {
        $timestamp = time();
        self::$testUserEmail = "http_test_user_{$timestamp}@test.com";

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                self::$testUserEmail,
                "http_test_user_{$timestamp}",
                'HTTP',
                'TestUser',
                'HTTP TestUser',
                password_hash('TestPassword123!', PASSWORD_DEFAULT),
                100
            ]
        );

        self::$testUserId = (int)Database::getInstance()->lastInsertId();
    }

    /**
     * Generate a valid auth token for the test user
     */
    protected static function generateAuthToken(): void
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        Database::query(
            "INSERT INTO api_tokens (user_id, token, type, expires_at, created_at)
             VALUES (?, ?, 'access', ?, NOW())",
            [self::$testUserId, hash('sha256', $token), $expiresAt]
        );

        self::$testAuthToken = $token;
    }

    /**
     * Clean up test user after all tests
     */
    public static function tearDownAfterClass(): void
    {
        if (self::$testUserId) {
            try {
                Database::query("DELETE FROM api_tokens WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM transactions WHERE sender_id = ? OR receiver_id = ?", [self::$testUserId, self::$testUserId]);
                Database::query("DELETE FROM notifications WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM activity_log WHERE user_id = ?", [self::$testUserId]);
                Database::query("DELETE FROM users WHERE id = ?", [self::$testUserId]);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        parent::tearDownAfterClass();
    }

    /**
     * Make an authenticated HTTP request
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $uri Request URI (relative to base URL)
     * @param array $options Guzzle request options
     * @return array Response array with status, headers, and body
     */
    protected function request(string $method, string $uri, array $options = []): array
    {
        // Add tenant slug prefix if not present
        if (!str_starts_with($uri, '/' . self::$testTenantSlug)) {
            $uri = '/' . self::$testTenantSlug . $uri;
        }

        // Add default headers
        $options['headers'] = array_merge([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ], $options['headers'] ?? []);

        // Add auth token if available
        if (self::$testAuthToken && !isset($options['headers']['Authorization'])) {
            $options['headers']['Authorization'] = 'Bearer ' . self::$testAuthToken;
        }

        try {
            $response = self::$httpClient->request($method, $uri, $options);

            $body = $response->getBody()->getContents();
            $jsonBody = json_decode($body, true);

            return [
                'status' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'body' => $jsonBody ?? $body,
                'raw_body' => $body,
            ];
        } catch (GuzzleException $e) {
            return [
                'status' => 0,
                'headers' => [],
                'body' => null,
                'raw_body' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Make a GET request
     */
    protected function get(string $uri, array $query = [], array $headers = []): array
    {
        $options = [];
        if (!empty($query)) {
            $options['query'] = $query;
        }
        if (!empty($headers)) {
            $options['headers'] = $headers;
        }
        return $this->request('GET', $uri, $options);
    }

    /**
     * Make a POST request with JSON body
     */
    protected function post(string $uri, array $data = [], array $headers = []): array
    {
        $options = [];
        if (!empty($data)) {
            $options['json'] = $data;
        }
        if (!empty($headers)) {
            $options['headers'] = $headers;
        }
        return $this->request('POST', $uri, $options);
    }

    /**
     * Make a POST request with form data
     */
    protected function postForm(string $uri, array $data = [], array $headers = []): array
    {
        $options = [
            'form_params' => $data,
            'headers' => array_merge(['Content-Type' => 'application/x-www-form-urlencoded'], $headers),
        ];
        return $this->request('POST', $uri, $options);
    }

    /**
     * Make a PUT request
     */
    protected function put(string $uri, array $data = [], array $headers = []): array
    {
        $options = [];
        if (!empty($data)) {
            $options['json'] = $data;
        }
        if (!empty($headers)) {
            $options['headers'] = $headers;
        }
        return $this->request('PUT', $uri, $options);
    }

    /**
     * Make a PATCH request
     */
    protected function patch(string $uri, array $data = [], array $headers = []): array
    {
        $options = [];
        if (!empty($data)) {
            $options['json'] = $data;
        }
        if (!empty($headers)) {
            $options['headers'] = $headers;
        }
        return $this->request('PATCH', $uri, $options);
    }

    /**
     * Make a DELETE request
     */
    protected function delete(string $uri, array $data = [], array $headers = []): array
    {
        $options = [];
        if (!empty($data)) {
            $options['json'] = $data;
        }
        if (!empty($headers)) {
            $options['headers'] = $headers;
        }
        return $this->request('DELETE', $uri, $options);
    }

    /**
     * Make an unauthenticated request
     */
    protected function requestWithoutAuth(string $method, string $uri, array $options = []): array
    {
        $options['headers'] = array_merge(
            $options['headers'] ?? [],
            ['Authorization' => ''] // Clear auth header
        );
        unset($options['headers']['Authorization']);

        // Add tenant slug prefix if not present
        if (!str_starts_with($uri, '/' . self::$testTenantSlug)) {
            $uri = '/' . self::$testTenantSlug . $uri;
        }

        try {
            $response = self::$httpClient->request($method, $uri, $options);

            $body = $response->getBody()->getContents();
            $jsonBody = json_decode($body, true);

            return [
                'status' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'body' => $jsonBody ?? $body,
                'raw_body' => $body,
            ];
        } catch (GuzzleException $e) {
            return [
                'status' => 0,
                'headers' => [],
                'body' => null,
                'raw_body' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    // ==========================================
    // Assertion Helpers
    // ==========================================

    /**
     * Assert HTTP status code
     */
    protected function assertStatus(int $expected, array $response, string $message = ''): void
    {
        $this->assertEquals(
            $expected,
            $response['status'],
            $message ?: "Expected HTTP status {$expected}, got {$response['status']}"
        );
    }

    /**
     * Assert successful response (2xx status)
     */
    protected function assertSuccessful(array $response, string $message = ''): void
    {
        $this->assertTrue(
            $response['status'] >= 200 && $response['status'] < 300,
            $message ?: "Expected successful response, got status {$response['status']}"
        );
    }

    /**
     * Assert OK response (200)
     */
    protected function assertOk(array $response, string $message = ''): void
    {
        $this->assertStatus(200, $response, $message);
    }

    /**
     * Assert Created response (201)
     */
    protected function assertCreated(array $response, string $message = ''): void
    {
        $this->assertStatus(201, $response, $message);
    }

    /**
     * Assert No Content response (204)
     */
    protected function assertNoContent(array $response, string $message = ''): void
    {
        $this->assertStatus(204, $response, $message);
    }

    /**
     * Assert Bad Request response (400)
     */
    protected function assertBadRequest(array $response, string $message = ''): void
    {
        $this->assertStatus(400, $response, $message);
    }

    /**
     * Assert Unauthorized response (401)
     */
    protected function assertUnauthorized(array $response, string $message = ''): void
    {
        $this->assertStatus(401, $response, $message);
    }

    /**
     * Assert Forbidden response (403)
     */
    protected function assertForbidden(array $response, string $message = ''): void
    {
        $this->assertStatus(403, $response, $message);
    }

    /**
     * Assert Not Found response (404)
     */
    protected function assertNotFound(array $response, string $message = ''): void
    {
        $this->assertStatus(404, $response, $message);
    }

    /**
     * Assert Unprocessable Entity response (422)
     */
    protected function assertUnprocessable(array $response, string $message = ''): void
    {
        $this->assertStatus(422, $response, $message);
    }

    /**
     * Assert Server Error response (500)
     */
    protected function assertServerError(array $response, string $message = ''): void
    {
        $this->assertStatus(500, $response, $message);
    }

    /**
     * Assert JSON response contains specific keys
     */
    protected function assertJsonHasKeys(array $keys, array $response, string $message = ''): void
    {
        $this->assertIsArray($response['body'], 'Response body should be JSON array');
        foreach ($keys as $key) {
            $this->assertArrayHasKey(
                $key,
                $response['body'],
                $message ?: "Response JSON should have key: {$key}"
            );
        }
    }

    /**
     * Assert JSON response has specific value
     */
    protected function assertJsonValue(string $key, $expected, array $response, string $message = ''): void
    {
        $this->assertIsArray($response['body'], 'Response body should be JSON array');
        $this->assertArrayHasKey($key, $response['body'], "Response should have key: {$key}");
        $this->assertEquals(
            $expected,
            $response['body'][$key],
            $message ?: "Response key '{$key}' should equal expected value"
        );
    }

    /**
     * Assert JSON response indicates success
     */
    protected function assertJsonSuccess(array $response, string $message = ''): void
    {
        $this->assertIsArray($response['body'], 'Response body should be JSON array');
        if (isset($response['body']['success'])) {
            $this->assertTrue($response['body']['success'], $message ?: 'Response should indicate success');
        }
        if (isset($response['body']['error'])) {
            $this->assertFalse($response['body']['error'], $message ?: 'Response should not have error flag');
        }
    }

    /**
     * Assert JSON response indicates error
     */
    protected function assertJsonError(array $response, string $message = ''): void
    {
        $this->assertIsArray($response['body'], 'Response body should be JSON array');
        if (isset($response['body']['success'])) {
            $this->assertFalse($response['body']['success'], $message ?: 'Response should indicate failure');
        }
        if (isset($response['body']['error'])) {
            $this->assertTrue(
                $response['body']['error'] === true || !empty($response['body']['error']),
                $message ?: 'Response should have error'
            );
        }
    }

    /**
     * Assert response has specific header
     */
    protected function assertHasHeader(string $header, array $response, string $message = ''): void
    {
        $this->assertArrayHasKey(
            $header,
            $response['headers'],
            $message ?: "Response should have header: {$header}"
        );
    }

    /**
     * Assert response header has specific value
     */
    protected function assertHeaderValue(string $header, string $expected, array $response, string $message = ''): void
    {
        $this->assertHasHeader($header, $response);
        $value = is_array($response['headers'][$header])
            ? $response['headers'][$header][0]
            : $response['headers'][$header];
        $this->assertEquals(
            $expected,
            $value,
            $message ?: "Header '{$header}' should have expected value"
        );
    }

    // ==========================================
    // Test Data Helpers
    // ==========================================

    /**
     * Create an additional test user
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
            'password' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'balance' => 50,
            'is_approved' => 1
        ];

        $data = array_merge($defaults, $attributes);

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, name, password, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['tenant_id'],
                $data['email'],
                $data['username'],
                $data['first_name'],
                $data['last_name'],
                $data['first_name'] . ' ' . $data['last_name'],
                $data['password'],
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
        try {
            Database::query("DELETE FROM api_tokens WHERE user_id = ?", [$userId]);
            Database::query("DELETE FROM transactions WHERE sender_id = ? OR receiver_id = ?", [$userId, $userId]);
            Database::query("DELETE FROM notifications WHERE user_id = ?", [$userId]);
            Database::query("DELETE FROM activity_log WHERE user_id = ?", [$userId]);
            Database::query("DELETE FROM users WHERE id = ?", [$userId]);
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }

    /**
     * Create a test listing
     */
    protected function createListing(array $attributes = []): array
    {
        $timestamp = time();
        $defaults = [
            'tenant_id' => self::$testTenantId,
            'user_id' => self::$testUserId,
            'title' => "Test Listing {$timestamp}",
            'description' => 'Test listing description for integration tests',
            'type' => 'offer',
            'category' => 'general',
            'time_credits' => 1,
            'status' => 'active',
        ];

        $data = array_merge($defaults, $attributes);

        Database::query(
            "INSERT INTO listings (tenant_id, user_id, title, description, type, category, time_credits, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['tenant_id'],
                $data['user_id'],
                $data['title'],
                $data['description'],
                $data['type'],
                $data['category'],
                $data['time_credits'],
                $data['status']
            ]
        );

        $data['id'] = (int)Database::getInstance()->lastInsertId();
        return $data;
    }

    /**
     * Clean up a created listing
     */
    protected function cleanupListing(int $listingId): void
    {
        try {
            Database::query("DELETE FROM listings WHERE id = ?", [$listingId]);
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }
}
