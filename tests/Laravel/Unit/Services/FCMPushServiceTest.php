<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FCMPushService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FCMPushServiceTest extends TestCase
{
    private FCMPushService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FCMPushService();
    }

    // =========================================================================
    // sendToUser() — static, requires FCM config
    // =========================================================================

    public function test_sendToUser_returns_not_configured_when_no_credentials(): void
    {
        // Without firebase credentials, should return not configured
        $result = FCMPushService::sendToUser(1, 'Title', 'Body');

        $this->assertArrayHasKey('sent', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('errors', $result);
        // Since we have no FCM config in test env
        $this->assertEquals(0, $result['sent']);
    }

    public function test_sendToUsers_returns_early_for_empty_user_ids(): void
    {
        $result = FCMPushService::sendToUsers([], 'Title', 'Body');

        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEmpty($result['errors']);
    }

    public function test_sendToUser_sends_expo_tokens_through_expo_push_api_without_fcm_credentials(): void
    {
        config([
            'services.fcm.server_key' => null,
            'services.fcm.project_id' => null,
            'services.fcm.service_account_path' => base_path('missing-firebase-service-account.json'),
        ]);

        Http::fake([
            'https://exp.host/--/api/v2/push/send' => Http::response([
                'data' => [
                    ['status' => 'ok', 'id' => 'ticket-1'],
                ],
            ], 200),
        ]);

        $query = \Mockery::mock();
        $query->shouldReceive('where')->with('user_id', 1)->once()->andReturnSelf();
        $query->shouldReceive('where')->with('tenant_id', $this->testTenantId)->once()->andReturnSelf();
        $query->shouldReceive('pluck')->with('token')->once()->andReturn(collect(['ExponentPushToken[abc123]']));
        DB::shouldReceive('table')->with('fcm_device_tokens')->once()->andReturn($query);

        $result = FCMPushService::sendToUser(1, 'New message', 'You have a new message.', [
            'link' => '/messages/123',
        ]);

        $this->assertSame(1, $result['sent']);
        $this->assertSame(0, $result['failed']);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->url() === 'https://exp.host/--/api/v2/push/send'
                && $payload['to'] === 'ExponentPushToken[abc123]'
                && $payload['title'] === 'New message'
                && $payload['body'] === 'You have a new message.'
                && $payload['data']['link'] === '/messages/123';
        });
    }

    // =========================================================================
    // registerDevice()
    // =========================================================================

    public function test_registerDevice_returns_true_on_success(): void
    {
        DB::shouldReceive('statement')->once()->andReturn(true);

        $result = $this->service->registerDevice(1, 'token123', 'ios');
        $this->assertTrue($result);
    }

    public function test_registerDevice_returns_false_on_exception(): void
    {
        DB::shouldReceive('statement')->andThrow(new \Exception('DB error'));
        Log::shouldReceive('error')->once();

        $result = $this->service->registerDevice(1, 'token123');
        $this->assertFalse($result);
    }

    // =========================================================================
    // unregisterDevice()
    // =========================================================================

    public function test_unregisterDevice_returns_true_when_token_deleted(): void
    {
        // unregisterDevice chains ->where('token')->where('tenant_id')
        // (tenant-scoped delete) before ->delete().
        $query = \Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('delete')->andReturn(1);
        DB::shouldReceive('table')->with('fcm_device_tokens')->andReturn($query);

        $this->assertTrue($this->service->unregisterDevice('token123'));
    }

    public function test_unregisterDevice_returns_false_when_token_not_found(): void
    {
        $query = \Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('delete')->andReturn(0);
        DB::shouldReceive('table')->with('fcm_device_tokens')->andReturn($query);

        $this->assertFalse($this->service->unregisterDevice('nonexistent'));
    }

    public function test_unregisterDevice_returns_false_on_exception(): void
    {
        $query = \Mockery::mock();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('delete')->andThrow(new \Exception('DB error'));
        DB::shouldReceive('table')->with('fcm_device_tokens')->andReturn($query);
        Log::shouldReceive('error')->once();

        $this->assertFalse($this->service->unregisterDevice('token123'));
    }

    // =========================================================================
    // ensureTableExists()
    // =========================================================================

    public function test_ensureTableExists_is_noop(): void
    {
        // Should not throw and should not query DB
        $this->service->ensureTableExists();
        $this->assertTrue(true);
    }

    // =========================================================================
    // isConfigured()
    // =========================================================================

    public function test_isConfigured_returns_boolean(): void
    {
        $result = $this->service->isConfigured();
        $this->assertIsBool($result);
    }
}
