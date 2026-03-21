<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\WebPushService;
use Illuminate\Support\Facades\DB;

class WebPushServiceTest extends TestCase
{
    private WebPushService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WebPushService();
    }

    public function test_sendToUser_returns_false_when_no_subscriptions(): void
    {
        DB::shouldReceive('table')->with('push_subscriptions')->andReturnSelf();
        DB::shouldReceive('where')->with('user_id', 1)->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->sendToUser(1, 'Test', 'Body');
        $this->assertFalse($result);
    }

    public function test_sendToUser_returns_false_when_vapid_not_configured(): void
    {
        $sub = (object) ['endpoint' => 'https://fcm.googleapis.com/test', 'p256dh_key' => 'key', 'auth_token' => 'token'];
        DB::shouldReceive('table')->with('push_subscriptions')->andReturnSelf();
        DB::shouldReceive('where')->with('user_id', 1)->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([$sub]));

        // Without VAPID keys, createWebPushInstance returns null
        $result = $this->service->sendToUser(1, 'Test', 'Body');
        $this->assertFalse($result);
    }

    public function test_sendToUsers_returns_zero_when_all_fail(): void
    {
        DB::shouldReceive('table')->with('push_subscriptions')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->sendToUsers([1, 2, 3], 'Test', 'Body');
        $this->assertEquals(0, $result);
    }
}
