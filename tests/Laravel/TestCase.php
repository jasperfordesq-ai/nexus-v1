<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel;

use App\Core\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base test case for all Laravel tests in the migration.
 *
 * Boots the Laravel application and sets up the tenant context
 * for multi-tenant testing (defaults to tenant 2 / hour-timebank).
 *
 * Existing legacy tests under tests/Unit, tests/Feature, etc.
 * are NOT affected — they continue to use the legacy bootstrap.
 */
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Default tenant ID for testing (hour-timebank).
     */
    protected int $testTenantId = 2;

    /**
     * Default tenant slug for testing.
     */
    protected string $testTenantSlug = 'hour-timebank';

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTenantContext();
    }

    /**
     * Initialize the tenant context for testing.
     *
     * Sets tenant_id=2 (hour-timebank) so that all tenant-scoped
     * queries and model operations work correctly during tests.
     */
    protected function setUpTenantContext(): void
    {
        TenantContext::setById($this->testTenantId);
    }

    /**
     * Override the test tenant for a specific test.
     */
    protected function withTenant(int $tenantId): static
    {
        $this->testTenantId = $tenantId;
        TenantContext::setById($tenantId);

        return $this;
    }

    /**
     * Add the X-Tenant-ID header to requests.
     *
     * The ResolveTenant middleware resolves tenant from this header,
     * so we include it on all API requests.
     */
    protected function withTenantHeader(array $headers = []): array
    {
        return array_merge($headers, [
            'X-Tenant-ID' => (string) $this->testTenantId,
            'Accept' => 'application/json',
        ]);
    }

    /**
     * Make a JSON API request with the tenant header included.
     */
    protected function apiGet(string $uri, array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api' . $uri, $this->withTenantHeader($headers));
    }

    /**
     * Make a JSON POST API request with the tenant header included.
     */
    protected function apiPost(string $uri, array $data = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api' . $uri, $data, $this->withTenantHeader($headers));
    }

    /**
     * Make a JSON PUT API request with the tenant header included.
     */
    protected function apiPut(string $uri, array $data = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->putJson('/api' . $uri, $data, $this->withTenantHeader($headers));
    }

    /**
     * Make a JSON DELETE API request with the tenant header included.
     */
    protected function apiDelete(string $uri, array $data = [], array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->deleteJson('/api' . $uri, $data, $this->withTenantHeader($headers));
    }
}
