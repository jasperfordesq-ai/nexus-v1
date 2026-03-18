<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * Legacy Bridge Test Case
 *
 * Provides backward-compatible helper methods from the legacy ApiTestCase,
 * DatabaseTestCase, ControllerTestCase, and HttpIntegrationTestCase base
 * classes, but runs on top of the Laravel test infrastructure.
 *
 * Migration path: change `extends ApiTestCase` (or DatabaseTestCase, etc.)
 * to `extends LegacyBridgeTestCase`, update namespace imports, and the
 * test should run identically against the Laravel HTTP kernel.
 *
 * Method mapping:
 *   Legacy makeApiRequest() / get() / post() / put() / delete()
 *     -> Laravel $this->getJson() / postJson() / putJson() / deleteJson()
 *   Legacy assertSuccess() / assertError() / assertStatus()
 *     -> Wrappers that normalise both legacy array and TestResponse formats
 *   Legacy createUser() / cleanupUser() / insertTestData()
 *     -> Eloquent / DB facade equivalents
 *   Legacy actingAs()
 *     -> Sanctum::actingAs()
 */
abstract class LegacyBridgeTestCase extends TestCase
{
    // ---------------------------------------------------------------
    // Static properties kept for source-level compatibility with
    // legacy tests that reference self::$testUserId, etc.
    // ---------------------------------------------------------------
    protected static ?int $testTenantId = null;
    protected static ?int $testUserId = null;
    protected static ?string $testUserEmail = null;
    protected static ?string $testAuthToken = null;

    /**
     * The Eloquent User model for the authenticated test user.
     */
    protected ?User $testUser = null;

    // ---------------------------------------------------------------
    // Lifecycle
    // ---------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();

        // Default tenant — mirrors legacy ApiTestCase
        if (static::$testTenantId === null) {
            static::$testTenantId = $this->testTenantId; // from parent TestCase (2)
        }
        TenantContext::setById(static::$testTenantId);

        // Create an authenticated test user via Sanctum
        $this->setUpTestUser();
    }

    /**
     * Create and authenticate a test user via Sanctum.
     */
    protected function setUpTestUser(): void
    {
        $timestamp = time() . rand(1000, 9999);

        $this->testUser = User::create([
            'tenant_id'   => static::$testTenantId,
            'email'       => "bridge_test_{$timestamp}@test.com",
            'username'    => "bridge_test_{$timestamp}",
            'first_name'  => 'Bridge',
            'last_name'   => 'TestUser',
            'name'        => 'Bridge TestUser',
            'password'    => bcrypt('TestPassword123!'),
            'balance'     => 100,
            'is_approved' => 1,
        ]);

        static::$testUserId    = $this->testUser->id;
        static::$testUserEmail = $this->testUser->email;
        static::$testAuthToken = 'sanctum-managed';

        Sanctum::actingAs($this->testUser);
    }

    // ---------------------------------------------------------------
    // HTTP helpers — legacy signature -> Laravel TestResponse
    //
    // The legacy helpers returned an array with keys:
    //   status, body, raw, method, endpoint
    // We preserve that shape so existing assertions keep working.
    // ---------------------------------------------------------------

    /**
     * Make an API request and return a legacy-shaped array.
     *
     * @param string      $method           HTTP verb
     * @param string      $endpoint         URI (e.g. /api/v2/listings)
     * @param array       $data             Body / query params
     * @param array       $headers          Extra headers
     * @param string|null $controllerAction Ignored (legacy compat)
     * @return array{status: int, body: array|string, raw: string, method: string, endpoint: string, response: TestResponse}
     */
    protected function makeApiRequest(
        string $method,
        string $endpoint,
        array $data = [],
        array $headers = [],
        ?string $controllerAction = null,
    ): array {
        $headers = $this->withTenantHeader($headers);

        /** @var TestResponse $response */
        $response = match (strtoupper($method)) {
            'GET'    => $this->getJson($endpoint, $headers),
            'POST'   => $this->postJson($endpoint, $data, $headers),
            'PUT'    => $this->putJson($endpoint, $data, $headers),
            'PATCH'  => $this->patchJson($endpoint, $data, $headers),
            'DELETE' => $this->deleteJson($endpoint, $data, $headers),
            default  => $this->getJson($endpoint, $headers),
        };

        $raw  = $response->getContent() ?: '';
        $body = json_decode($raw, true);

        return [
            'status'   => $response->status(),
            'body'     => $body ?? $raw,
            'raw'      => $raw,
            'method'   => strtoupper($method),
            'endpoint' => $endpoint,
            'response' => $response,  // escape-hatch for Laravel-native assertions
        ];
    }

    /**
     * GET request (legacy signature).
     */
    protected function legacyGet(string $endpoint, array $params = [], array $headers = [], ?string $controllerAction = null): array
    {
        // Append query params to URL for GET
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }
        return $this->makeApiRequest('GET', $endpoint, [], $headers, $controllerAction);
    }

    /**
     * POST request (legacy signature).
     */
    protected function legacyPost(string $endpoint, array $data = [], array $headers = [], ?string $controllerAction = null): array
    {
        return $this->makeApiRequest('POST', $endpoint, $data, $headers, $controllerAction);
    }

    /**
     * PUT request (legacy signature).
     */
    protected function legacyPut(string $endpoint, array $data = [], array $headers = [], ?string $controllerAction = null): array
    {
        return $this->makeApiRequest('PUT', $endpoint, $data, $headers, $controllerAction);
    }

    /**
     * DELETE request (legacy signature).
     */
    protected function legacyDelete(string $endpoint, array $data = [], array $headers = [], ?string $controllerAction = null): array
    {
        return $this->makeApiRequest('DELETE', $endpoint, $data, $headers, $controllerAction);
    }

    // ---------------------------------------------------------------
    // Assertion helpers — backward-compatible with legacy array shape
    // ---------------------------------------------------------------

    /**
     * Assert response has expected JSON structure (list of top-level keys).
     */
    protected function assertJsonStructure(array $expected, array $actual, string $message = ''): void
    {
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $actual, $message ?: "JSON should have key: {$key}");
        }
    }

    /**
     * Assert that a legacy response array indicates success.
     */
    protected function assertSuccess(array $response, string $message = ''): void
    {
        $body = $response['body'] ?? $response;
        if (is_array($body)) {
            if (isset($body['success'])) {
                $this->assertTrue($body['success'], $message ?: 'Response should indicate success');
            }
            if (isset($body['error'])) {
                $this->assertFalse($body['error'], $message ?: 'Response should not have error');
            }
        }
    }

    /**
     * Assert that a legacy response array indicates an error.
     */
    protected function assertError(array $response, string $message = ''): void
    {
        $body = $response['body'] ?? $response;
        if (is_array($body)) {
            if (isset($body['success'])) {
                $this->assertFalse($body['success'], $message ?: 'Response should indicate failure');
            }
            if (isset($body['error'])) {
                $this->assertTrue($body['error'], $message ?: 'Response should have error');
            }
        }
    }

    /**
     * Assert HTTP status code on a legacy response array.
     */
    protected function assertLegacyStatus(int $expected, array $response, string $message = ''): void
    {
        $this->assertEquals($expected, $response['status'], $message ?: "Expected HTTP status {$expected}");
    }

    /**
     * Assert response body contains a key.
     */
    protected function assertResponseHas(string $key, array $response, string $message = ''): void
    {
        $body = $response['body'] ?? $response;
        $this->assertArrayHasKey($key, $body, $message ?: "Response body should have key: {$key}");
    }

    /**
     * Assert a 200 OK on a legacy response.
     */
    protected function assertOk(array $response, string $message = ''): void
    {
        $this->assertLegacyStatus(200, $response, $message);
    }

    /**
     * Assert 201 Created on a legacy response.
     */
    protected function assertCreated(array $response, string $message = ''): void
    {
        $this->assertLegacyStatus(201, $response, $message);
    }

    /**
     * Assert 401 Unauthorized on a legacy response.
     */
    protected function assertUnauthorized(array $response, string $message = ''): void
    {
        $this->assertLegacyStatus(401, $response, $message);
    }

    /**
     * Assert 403 Forbidden on a legacy response.
     */
    protected function assertForbidden(array $response, string $message = ''): void
    {
        $this->assertLegacyStatus(403, $response, $message);
    }

    /**
     * Assert 404 Not Found on a legacy response.
     */
    protected function assertNotFoundResponse(array $response, string $message = ''): void
    {
        $this->assertLegacyStatus(404, $response, $message);
    }

    /**
     * Assert 422 Unprocessable on a legacy response.
     */
    protected function assertUnprocessable(array $response, string $message = ''): void
    {
        $this->assertLegacyStatus(422, $response, $message);
    }

    /**
     * Assert successful 2xx status on a legacy response.
     */
    protected function assertSuccessful(array $response, string $message = ''): void
    {
        $this->assertTrue(
            $response['status'] >= 200 && $response['status'] < 300,
            $message ?: "Expected successful response, got status {$response['status']}"
        );
    }

    /**
     * Assert JSON response contains specific keys (HttpIntegrationTestCase compat).
     */
    protected function assertJsonHasKeys(array $keys, array $response, string $message = ''): void
    {
        $body = $response['body'] ?? $response;
        $this->assertIsArray($body, 'Response body should be a JSON array');
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $body, $message ?: "Response JSON should have key: {$key}");
        }
    }

    /**
     * Assert JSON response has a specific value (HttpIntegrationTestCase compat).
     */
    protected function assertJsonValue(string $key, mixed $expected, array $response, string $message = ''): void
    {
        $body = $response['body'] ?? $response;
        $this->assertIsArray($body, 'Response body should be a JSON array');
        $this->assertArrayHasKey($key, $body, "Response should have key: {$key}");
        $this->assertEquals($expected, $body[$key], $message ?: "Response key '{$key}' should equal expected value");
    }

    /**
     * Assert that a legacy JSON response indicates success (HttpIntegrationTestCase compat).
     */
    protected function assertJsonSuccess(array $response, string $message = ''): void
    {
        $this->assertSuccess($response, $message);
    }

    /**
     * Assert that a legacy JSON response indicates error (HttpIntegrationTestCase compat).
     */
    protected function assertJsonError(array $response, string $message = ''): void
    {
        $this->assertError($response, $message);
    }

    // ---------------------------------------------------------------
    // Database helpers — backward-compatible with DatabaseTestCase
    // ---------------------------------------------------------------

    /**
     * Insert test data into a table and return the ID.
     */
    protected function insertTestData(string $table, array $data): int
    {
        return DB::table($table)->insertGetId($data);
    }

    /**
     * Get test data from a table with optional conditions.
     */
    protected function getTestData(string $table, array $conditions = []): array
    {
        $query = DB::table($table);
        foreach ($conditions as $column => $value) {
            $query->where($column, $value);
        }
        return $query->get()->map(fn ($row) => (array) $row)->toArray();
    }

    /**
     * Truncate a table.
     */
    protected function truncateTable(string $table): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table($table)->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    // ---------------------------------------------------------------
    // User / auth helpers — backward-compatible with legacy test cases
    // ---------------------------------------------------------------

    /**
     * Create an additional test user (returns legacy array format).
     */
    protected function createUser(array $attributes = []): array
    {
        $timestamp = time() . rand(1000, 9999);
        $defaults = [
            'tenant_id'   => static::$testTenantId,
            'email'       => "test_user_{$timestamp}@test.com",
            'username'    => "test_user_{$timestamp}",
            'first_name'  => 'Test',
            'last_name'   => 'User',
            'name'        => 'Test User',
            'password'    => bcrypt('TestPassword123!'),
            'balance'     => 50,
            'is_approved' => 1,
        ];

        $data = array_merge($defaults, $attributes);
        $user = User::create($data);
        $data['id'] = $user->id;

        return $data;
    }

    /**
     * Clean up a created user and related data.
     */
    protected function cleanupUser(int $userId): void
    {
        try {
            DB::table('api_tokens')->where('user_id', $userId)->delete();
            DB::table('personal_access_tokens')->where('tokenable_id', $userId)->delete();
            DB::table('transactions')->where('sender_id', $userId)->orWhere('receiver_id', $userId)->delete();
            DB::table('notifications')->where('user_id', $userId)->delete();
            DB::table('activity_log')->where('user_id', $userId)->delete();
            DB::table('users')->where('id', $userId)->delete();
        } catch (\Exception $e) {
            // Ignore cleanup errors — table may not exist yet during migration
        }
    }

    /**
     * Authenticate as a specific user (Sanctum wrapper).
     *
     * Accepts either an Eloquent User or a user ID.
     */
    protected function actingAsUser(User|int $user, ?int $tenantId = null): static
    {
        if (is_int($user)) {
            $user = User::findOrFail($user);
        }

        if ($tenantId !== null) {
            TenantContext::setById($tenantId);
        }

        Sanctum::actingAs($user);

        return $this;
    }

    /**
     * Authenticate as an admin user (Sanctum wrapper).
     */
    protected function actingAsAdmin(User|int $user, ?int $tenantId = null): static
    {
        if (is_int($user)) {
            $user = User::findOrFail($user);
        }

        if ($tenantId !== null) {
            TenantContext::setById($tenantId);
        }

        Sanctum::actingAs($user, ['admin']);

        return $this;
    }

    /**
     * Act as an unauthenticated guest.
     */
    protected function actingAsGuest(): static
    {
        // Reset Sanctum auth by refreshing the app
        $this->app['auth']->forgetGuards();

        return $this;
    }

    // ---------------------------------------------------------------
    // Utility helpers from legacy TestCase / ControllerTestCase
    // ---------------------------------------------------------------

    /**
     * Assert that an array has ALL the specified keys.
     */
    protected function assertArrayHasKeys(array $keys, array $array, string $message = ''): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, $message ?: "Array should have key: {$key}");
        }
    }

    /**
     * Assert that a string contains all given substrings.
     */
    protected function assertStringContainsStrings(array $needles, string $haystack, string $message = ''): void
    {
        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $haystack, $message ?: "String does not contain: {$needle}");
        }
    }

    /**
     * Create a mock with pre-configured method return values.
     */
    protected function createMockWithMethods(string $class, array $methods = []): object
    {
        $mock = $this->createMock($class);
        foreach ($methods as $method => $return) {
            $mock->method($method)->willReturn($return);
        }
        return $mock;
    }

    /**
     * Get a private or protected property value via reflection.
     */
    protected function getPrivateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    /**
     * Set a private or protected property value via reflection.
     */
    protected function setPrivateProperty(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }

    /**
     * Call a private or protected method via reflection.
     */
    protected function callPrivateMethod(object $object, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionClass($object);
        $m = $reflection->getMethod($method);
        $m->setAccessible(true);
        return $m->invokeArgs($object, $args);
    }
}
