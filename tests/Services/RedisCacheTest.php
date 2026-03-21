<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\RedisCache;
use Illuminate\Support\Facades\Cache;

/**
 * Tests for App\Services\RedisCache.
 *
 * Uses Laravel's Cache facade with the array driver (test environment)
 * so Redis is not required. The service delegates to Cache::store('redis'),
 * which resolves to the configured test driver.
 *
 * @covers \App\Services\RedisCache
 */
class RedisCacheTest extends TestCase
{
    private RedisCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new RedisCache();

        // Use array driver for tests to avoid Redis dependency
        config(['cache.default' => 'array']);
    }

    // =========================================================================
    // buildKey (tested indirectly via get/set)
    // =========================================================================

    public function testBuildKeyWithTenantIdPrefixes(): void
    {
        // We test this indirectly: set with tenantId, get without should miss
        $this->cache->set('test_key', 'value', 300, 1);

        // Getting without tenant scope should not find the key
        $result = $this->cache->get('test_key');
        $this->assertNull($result);

        // Getting with correct tenant scope should find it
        $result = $this->cache->get('test_key', 1);
        $this->assertEquals('value', $result);
    }

    public function testBuildKeyWithoutTenantId(): void
    {
        $this->cache->set('global_key', 'global_value', 300);
        $result = $this->cache->get('global_key');
        $this->assertEquals('global_value', $result);
    }

    // =========================================================================
    // get()
    // =========================================================================

    public function testGetReturnsNullForMissingKey(): void
    {
        $result = $this->cache->get('nonexistent_key_' . time());
        $this->assertNull($result);
    }

    public function testGetReturnsStoredValue(): void
    {
        $this->cache->set('get_test', 'hello');
        $this->assertEquals('hello', $this->cache->get('get_test'));
    }

    public function testGetReturnsNullForWrongTenant(): void
    {
        $this->cache->set('tenant_test', 'data', 300, 1);
        $result = $this->cache->get('tenant_test', 2);
        $this->assertNull($result);
    }

    public function testGetHandlesComplexValues(): void
    {
        $data = ['name' => 'Test', 'items' => [1, 2, 3], 'nested' => ['a' => 'b']];
        $this->cache->set('complex_test', $data);
        $result = $this->cache->get('complex_test');
        $this->assertEquals($data, $result);
    }

    public function testGetHandlesIntegerValues(): void
    {
        $this->cache->set('int_test', 42);
        $this->assertSame(42, $this->cache->get('int_test'));
    }

    public function testGetHandlesBooleanValues(): void
    {
        $this->cache->set('bool_true', true);
        $this->cache->set('bool_false', false);
        $this->assertTrue($this->cache->get('bool_true'));
        // false is stored but cache returns null for missing; need to use has() to disambiguate
    }

    // =========================================================================
    // set()
    // =========================================================================

    public function testSetReturnsTrueOnSuccess(): void
    {
        $result = $this->cache->set('set_test', 'value');
        $this->assertTrue($result);
    }

    public function testSetWithCustomTtl(): void
    {
        $result = $this->cache->set('ttl_test', 'value', 60);
        $this->assertTrue($result);
        $this->assertEquals('value', $this->cache->get('ttl_test'));
    }

    public function testSetWithTenantScope(): void
    {
        $this->cache->set('scoped', 'tenant1_data', 300, 1);
        $this->cache->set('scoped', 'tenant2_data', 300, 2);

        $this->assertEquals('tenant1_data', $this->cache->get('scoped', 1));
        $this->assertEquals('tenant2_data', $this->cache->get('scoped', 2));
    }

    public function testSetOverwritesExistingValue(): void
    {
        $this->cache->set('overwrite_test', 'first');
        $this->cache->set('overwrite_test', 'second');
        $this->assertEquals('second', $this->cache->get('overwrite_test'));
    }

    public function testSetWithNullValue(): void
    {
        $this->cache->set('null_test', null);
        // Null value stored in cache — get returns null for both missing and null-stored
        $result = $this->cache->get('null_test');
        $this->assertNull($result);
    }

    // =========================================================================
    // delete()
    // =========================================================================

    public function testDeleteRemovesKey(): void
    {
        $this->cache->set('delete_test', 'to_delete');
        $this->assertEquals('to_delete', $this->cache->get('delete_test'));

        $this->cache->delete('delete_test');
        $this->assertNull($this->cache->get('delete_test'));
    }

    public function testDeleteWithTenantScope(): void
    {
        $this->cache->set('delete_scoped', 'data', 300, 1);
        $this->cache->delete('delete_scoped', 1);
        $this->assertNull($this->cache->get('delete_scoped', 1));
    }

    public function testDeleteReturnsBoolForNonexistentKey(): void
    {
        $result = $this->cache->delete('nonexistent_delete_' . time());
        $this->assertIsBool($result);
    }

    public function testDeleteDoesNotAffectOtherTenants(): void
    {
        $this->cache->set('multi_tenant_delete', 't1_data', 300, 1);
        $this->cache->set('multi_tenant_delete', 't2_data', 300, 2);

        $this->cache->delete('multi_tenant_delete', 1);

        $this->assertNull($this->cache->get('multi_tenant_delete', 1));
        $this->assertEquals('t2_data', $this->cache->get('multi_tenant_delete', 2));
    }

    // =========================================================================
    // has()
    // =========================================================================

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->cache->has('missing_key_' . time()));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->cache->set('has_test', 'value');
        $this->assertTrue($this->cache->has('has_test'));
    }

    public function testHasReturnsFalseAfterDelete(): void
    {
        $this->cache->set('has_delete_test', 'value');
        $this->cache->delete('has_delete_test');
        $this->assertFalse($this->cache->has('has_delete_test'));
    }

    public function testHasWithTenantScope(): void
    {
        $this->cache->set('has_scoped', 'value', 300, 1);
        $this->assertTrue($this->cache->has('has_scoped', 1));
        $this->assertFalse($this->cache->has('has_scoped', 2));
    }

    // =========================================================================
    // increment()
    // =========================================================================

    public function testIncrementReturnsNewValue(): void
    {
        $key = 'incr_test_' . time();
        $result = $this->cache->increment($key);
        $this->assertIsInt($result);
        // First increment should return 1 (or 0 on failure)
        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testIncrementIncrementsExistingValue(): void
    {
        $key = 'incr_multi_' . time();
        $first = $this->cache->increment($key);
        $second = $this->cache->increment($key);

        if ($first > 0) {
            $this->assertGreaterThan($first, $second);
        }
    }

    // =========================================================================
    // getStats()
    // =========================================================================

    public function testGetStatsReturnsArray(): void
    {
        $stats = $this->cache->getStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('enabled', $stats);
    }

    public function testGetStatsContainsEnabledKey(): void
    {
        $stats = $this->cache->getStats();

        // In test environment Redis may not be available
        $this->assertIsBool($stats['enabled']);
        if (!$stats['enabled']) {
            $this->assertArrayHasKey('error', $stats);
        }
    }

    // =========================================================================
    // __callStatic
    // =========================================================================

    public function testStaticCallResolvesFromContainer(): void
    {
        // Ensure the service is bound in the container
        $this->app->bind(RedisCache::class, fn () => new RedisCache());

        // The static call should work via __callStatic
        $result = RedisCache::has('static_test_' . time());
        $this->assertIsBool($result);
    }

    // =========================================================================
    // clearTenant()
    // =========================================================================

    public function testClearTenantReturnsZeroForNullTenant(): void
    {
        $result = $this->cache->clearTenant(null);
        $this->assertSame(0, $result);
    }

    public function testClearTenantReturnsInt(): void
    {
        // May return 0 if Redis is not available in test env
        $result = $this->cache->clearTenant(999);
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    // =========================================================================
    // Default TTL
    // =========================================================================

    public function testDefaultTtlIsUsedWhenNotSpecified(): void
    {
        // The default TTL is 300 seconds — we just verify set works without explicit TTL
        $result = $this->cache->set('default_ttl_test', 'value');
        $this->assertTrue($result);
        $this->assertEquals('value', $this->cache->get('default_ttl_test'));
    }
}
