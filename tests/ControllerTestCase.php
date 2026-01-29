<?php

declare(strict_types=1);

namespace Nexus\Tests;

use Nexus\Core\Database;
use Nexus\Core\TenantContext;

/**
 * Controller Test Case
 *
 * Base class for testing controllers directly without HTTP overhead.
 * Uses output buffering to capture JSON responses from controllers.
 *
 * This is useful for:
 * - Unit testing controller logic without HTTP stack
 * - Testing internal methods and state
 * - Faster test execution compared to HTTP integration tests
 */
abstract class ControllerTestCase extends DatabaseTestCase
{
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?string $testUserEmail = null;

    /**
     * Captured output from controller
     */
    protected ?string $capturedOutput = null;

    /**
     * Captured HTTP status code
     */
    protected int $capturedStatusCode = 200;

    /**
     * Set up test context before all tests
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Set default tenant
        self::$testTenantId = 2; // hour-timebank tenant
        TenantContext::setById(self::$testTenantId);

        // Create test user
        self::createTestUser();
    }

    /**
     * Create a test user for controller tests
     */
    protected static function createTestUser(): void
    {
        $timestamp = time();
        self::$testUserEmail = "controller_test_user_{$timestamp}@test.com";

        Database::query(
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, password, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())",
            [
                self::$testTenantId,
                self::$testUserEmail,
                "controller_test_user_{$timestamp}",
                'Controller',
                'TestUser',
                password_hash('TestPassword123!', PASSWORD_DEFAULT),
                100
            ]
        );

        self::$testUserId = (int)Database::getInstance()->lastInsertId();
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
     * Set up session and superglobals before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Initialize session if not started
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Set up authenticated session
        $_SESSION['user_id'] = self::$testUserId;
        $_SESSION['tenant_id'] = self::$testTenantId;

        // Reset superglobals
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Reset captured output
        $this->capturedOutput = null;
        $this->capturedStatusCode = 200;
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        // Clear session data
        $_SESSION = [];

        // Clear superglobals
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];

        parent::tearDown();
    }

    /**
     * Simulate a GET request by setting up superglobals
     */
    protected function simulateGet(array $params = []): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $params;
        $_REQUEST = $params;
    }

    /**
     * Simulate a POST request by setting up superglobals
     */
    protected function simulatePost(array $data = []): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $data;
        $_REQUEST = $data;
    }

    /**
     * Simulate JSON POST body (for API controllers)
     */
    protected function simulateJsonPost(array $data = []): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        // Create a temporary stream with JSON data for php://input simulation
        // Controllers using file_get_contents('php://input') will need mocking
        $this->mockPhpInput(json_encode($data));
    }

    /**
     * Mock php://input stream
     * Note: This requires the controller to use a mockable input method
     */
    protected function mockPhpInput(string $content): void
    {
        // Store for controllers that accept input injection
        $_SERVER['_MOCK_PHP_INPUT'] = $content;
    }

    /**
     * Call a controller method and capture its output
     *
     * @param object $controller Controller instance
     * @param string $method Method name to call
     * @param array $args Arguments to pass to the method
     * @return mixed Method return value (if any)
     */
    protected function callController(object $controller, string $method, array $args = []): mixed
    {
        // Capture any output
        ob_start();

        try {
            $result = call_user_func_array([$controller, $method], $args);
            $this->capturedOutput = ob_get_clean();
            return $result;
        } catch (\Exception $e) {
            $this->capturedOutput = ob_get_clean();
            throw $e;
        }
    }

    /**
     * Call a controller method that exits (like JSON responses)
     * Uses output buffering and catches exit via register_shutdown_function workaround
     *
     * @param callable $callback Callable that invokes the controller
     * @return array Parsed JSON response or ['raw' => string] for non-JSON
     */
    protected function callControllerWithExit(callable $callback): array
    {
        ob_start();

        try {
            $callback();
            $output = ob_get_clean();
        } catch (\Exception $e) {
            $output = ob_get_clean();
            throw $e;
        }

        $this->capturedOutput = $output;

        // Try to parse as JSON
        $json = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return [
                'status' => $this->capturedStatusCode,
                'body' => $json,
                'raw' => $output,
            ];
        }

        return [
            'status' => $this->capturedStatusCode,
            'body' => null,
            'raw' => $output,
        ];
    }

    /**
     * Get the captured output from the last controller call
     */
    protected function getCapturedOutput(): ?string
    {
        return $this->capturedOutput;
    }

    /**
     * Get the captured output as parsed JSON
     */
    protected function getCapturedJson(): ?array
    {
        if ($this->capturedOutput === null) {
            return null;
        }

        $json = json_decode($this->capturedOutput, true);
        return json_last_error() === JSON_ERROR_NONE ? $json : null;
    }

    /**
     * Set authenticated user for the test
     */
    protected function actingAs(int $userId, ?int $tenantId = null): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['tenant_id'] = $tenantId ?? self::$testTenantId;
    }

    /**
     * Set admin user for the test
     */
    protected function actingAsAdmin(int $userId, ?int $tenantId = null): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['tenant_id'] = $tenantId ?? self::$testTenantId;
        $_SESSION['is_admin'] = true;
        $_SESSION['user_role'] = 'admin';
    }

    /**
     * Clear authentication (act as guest)
     */
    protected function actingAsGuest(): void
    {
        unset($_SESSION['user_id']);
        unset($_SESSION['tenant_id']);
        unset($_SESSION['is_admin']);
        unset($_SESSION['user_role']);
    }

    // ==========================================
    // Assertion Helpers
    // ==========================================

    /**
     * Assert that captured output is valid JSON
     */
    protected function assertJsonOutput(string $message = ''): void
    {
        $this->assertNotNull($this->capturedOutput, 'No output was captured');
        json_decode($this->capturedOutput);
        $this->assertEquals(
            JSON_ERROR_NONE,
            json_last_error(),
            $message ?: 'Output should be valid JSON: ' . json_last_error_msg()
        );
    }

    /**
     * Assert that captured JSON has specific keys
     */
    protected function assertJsonOutputHasKeys(array $keys, string $message = ''): void
    {
        $this->assertJsonOutput();
        $json = $this->getCapturedJson();

        foreach ($keys as $key) {
            $this->assertArrayHasKey(
                $key,
                $json,
                $message ?: "JSON output should have key: {$key}"
            );
        }
    }

    /**
     * Assert that captured JSON has specific value
     */
    protected function assertJsonOutputValue(string $key, $expected, string $message = ''): void
    {
        $this->assertJsonOutput();
        $json = $this->getCapturedJson();

        $this->assertArrayHasKey($key, $json, "JSON output should have key: {$key}");
        $this->assertEquals(
            $expected,
            $json[$key],
            $message ?: "JSON key '{$key}' should have expected value"
        );
    }

    /**
     * Assert that captured output contains string
     */
    protected function assertOutputContains(string $needle, string $message = ''): void
    {
        $this->assertNotNull($this->capturedOutput, 'No output was captured');
        $this->assertStringContainsString(
            $needle,
            $this->capturedOutput,
            $message ?: "Output should contain: {$needle}"
        );
    }

    /**
     * Assert that captured output does not contain string
     */
    protected function assertOutputNotContains(string $needle, string $message = ''): void
    {
        $this->assertNotNull($this->capturedOutput, 'No output was captured');
        $this->assertStringNotContainsString(
            $needle,
            $this->capturedOutput,
            $message ?: "Output should not contain: {$needle}"
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
            "INSERT INTO users (tenant_id, email, username, first_name, last_name, password, balance, is_approved, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $data['tenant_id'],
                $data['email'],
                $data['username'],
                $data['first_name'],
                $data['last_name'],
                $data['password'],
                $data['balance'],
                $data['is_approved']
            ]
        );

        $data['id'] = (int)Database::getInstance()->lastInsertId();
        return $data;
    }

    /**
     * Clean up a created user
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
}
