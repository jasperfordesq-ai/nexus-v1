<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\WebhookDispatchService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WebhookDispatchServiceTest extends TestCase
{
    /**
     * Defensively clear any leaked DB-facade Mockery expectation before each test.
     *
     * Hypothesis for the polluted failure of test_createWebhook_throws_when_secret_missing:
     * these are Unit tests that swap the DB facade with Mockery (DB::shouldReceive(...)).
     * createWebhook() only throws InvalidArgumentException for a missing secret indirectly —
     * an empty secret is auto-generated, then execution reaches DB::table('outbound_webhooks')
     * ->insertGetId(...). When a stale DB-facade mock leaks in from an earlier test (in this
     * class or another Unit class run earlier in the full suite), that insert resolves against
     * the leaked mock instead of throwing the validation exception the test expects, so the
     * expected InvalidArgumentException never surfaces.
     *
     * Resetting the DB facade (and the Mockery container) here guarantees each test starts
     * from the real DB facade, exactly as it does when this file runs on its own.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Drop any leaked facade mocks (DB::swap/shouldReceive) from a prior test/class
        // so the validation-then-DB path in the service behaves deterministically.
        \Mockery::close();
        DB::clearResolvedInstances();

        Cache::flush();

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    public function test_getWebhooks_returns_array(): void
    {
        DB::shouldReceive('table')->with('outbound_webhooks')->andReturnSelf();
        DB::shouldReceive('where')->with('tenant_id', TenantContext::getId())->andReturnSelf();
        DB::shouldReceive('orderByDesc')->with('created_at')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = WebhookDispatchService::getWebhooks();
        $this->assertIsArray($result);
    }

    public function test_createWebhook_throws_when_name_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookDispatchService::createWebhook(1, ['url' => 'https://example.com', 'secret' => 'x', 'events' => ['test']]);
    }

    public function test_createWebhook_throws_when_url_invalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookDispatchService::createWebhook(1, ['name' => 'Test', 'url' => 'not-a-url', 'secret' => 'x', 'events' => ['test']]);
    }

    public function test_createWebhook_throws_when_url_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookDispatchService::createWebhook(1, ['name' => 'Test', 'secret' => 'x', 'events' => ['test']]);
    }

    public function test_createWebhook_throws_when_secret_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookDispatchService::createWebhook(1, ['name' => 'Test', 'url' => 'https://example.com', 'events' => ['test']]);
    }

    public function test_createWebhook_throws_when_events_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookDispatchService::createWebhook(1, ['name' => 'Test', 'url' => 'https://example.com', 'secret' => 'x']);
    }

    public function test_updateWebhook_throws_for_invalid_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebhookDispatchService::updateWebhook(1, ['url' => 'not-a-url']);
    }

    public function test_updateWebhook_returns_false_for_empty_data(): void
    {
        $this->assertFalse(WebhookDispatchService::updateWebhook(1, []));
    }

    public function test_deleteWebhook_returns_false_when_not_found(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('delete')->andReturn(0);

        $this->assertFalse(WebhookDispatchService::deleteWebhook(999));
    }

    public function test_testWebhook_throws_when_not_found(): void
    {
        DB::shouldReceive('table')->with('outbound_webhooks')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->expectException(\RuntimeException::class);
        WebhookDispatchService::testWebhook(999);
    }

    public function test_getLogs_returns_array(): void
    {
        DB::shouldReceive('table')->with('outbound_webhook_logs')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = WebhookDispatchService::getLogs(1);
        $this->assertIsArray($result);
    }
}
