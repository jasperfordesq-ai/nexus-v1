<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\RedisCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Mockery;

class RedisCacheTest extends TestCase
{
    private RedisCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new RedisCache();
    }

    // ── get ──

    public function test_get_returns_cached_value(): void
    {
        $store = Mockery::mock();
        $store->shouldReceive('get')->with('test-key')->andReturn('hello');
        Cache::shouldReceive('store')->with('redis')->andReturn($store);

        $result = $this->cache->get('test-key');
        $this->assertEquals('hello', $result);
    }

    public function test_get_returns_null_on_failure(): void
    {
        $store = Mockery::mock();
        $store->shouldReceive('get')->andThrow(new \RuntimeException('fail'));
        Cache::shouldReceive('store')->with('redis')->andReturn($store);

        $result = $this->cache->get('test-key');
        $this->assertNull($result);
    }

    public function test_get_with_tenant_scoped_key(): void
    {
        $store = Mockery::mock();
        $store->shouldReceive('get')->with('t2:test-key')->andReturn('scoped');
        Cache::shouldReceive('store')->with('redis')->andReturn($store);

        $result = $this->cache->get('test-key', 2);
        $this->assertEquals('scoped', $result);
    }

    // ── set ──

    public function test_set_stores_value(): void
    {
        $store = Mockery::mock();
        $store->shouldReceive('put')->with('test-key', 'value', 300)->andReturn(true);
        Cache::shouldReceive('store')->with('redis')->andReturn($store);

        $result = $this->cache->set('test-key', 'value');
        $this->assertTrue($result);
    }

    public function test_set_returns_false_on_failure(): void
    {
        $store = Mockery::mock();
        $store->shouldReceive('put')->andThrow(new \RuntimeException('fail'));
        Cache::shouldReceive('store')->with('redis')->andReturn($store);

        $result = $this->cache->set('key', 'val');
        $this->assertFalse($result);
    }

    // ── delete ──

    public function test_delete_removes_key(): void
    {
        $store = Mockery::mock();
        $store->shouldReceive('forget')->with('test-key')->andReturn(true);
        Cache::shouldReceive('store')->with('redis')->andReturn($store);

        $result = $this->cache->delete('test-key');
        $this->assertTrue($result);
    }

    // ── has ──

    public function test_has_returns_true_when_key_exists(): void
    {
        $store = Mockery::mock();
        $store->shouldReceive('has')->with('test-key')->andReturn(true);
        Cache::shouldReceive('store')->with('redis')->andReturn($store);

        $this->assertTrue($this->cache->has('test-key'));
    }

    public function test_has_returns_false_on_exception(): void
    {
        $store = Mockery::mock();
        $store->shouldReceive('has')->andThrow(new \RuntimeException('fail'));
        Cache::shouldReceive('store')->with('redis')->andReturn($store);

        $this->assertFalse($this->cache->has('test-key'));
    }

    // ── increment ──

    public function test_increment_returns_new_value(): void
    {
        $store = Mockery::mock();
        $store->shouldReceive('increment')->with('count')->andReturn(5);
        Cache::shouldReceive('store')->with('redis')->andReturn($store);

        $result = $this->cache->increment('count');
        $this->assertEquals(5, $result);
    }

    public function test_increment_sets_ttl_on_first_creation(): void
    {
        $store = Mockery::mock();
        $store->shouldReceive('increment')->with('new-count')->andReturn(1);
        $store->shouldReceive('put')->with('new-count', 1, 300)->once();
        Cache::shouldReceive('store')->with('redis')->andReturn($store);

        $result = $this->cache->increment('new-count');
        $this->assertEquals(1, $result);
    }

    public function test_increment_returns_zero_on_failure(): void
    {
        $store = Mockery::mock();
        $store->shouldReceive('increment')->andThrow(new \RuntimeException('fail'));
        Cache::shouldReceive('store')->with('redis')->andReturn($store);

        $this->assertEquals(0, $this->cache->increment('key'));
    }

    // ── clearTenant ──

    public function test_clearTenant_returns_zero_for_null_tenant(): void
    {
        $result = $this->cache->clearTenant(null);
        $this->assertEquals(0, $result);
    }

    // ── getStats ──

    public function test_getStats_returns_disabled_on_failure(): void
    {
        Redis::shouldReceive('connection')->andThrow(new \RuntimeException('unavailable'));

        $result = $this->cache->getStats();
        $this->assertFalse($result['enabled']);
        $this->assertArrayHasKey('error', $result);
    }
}
