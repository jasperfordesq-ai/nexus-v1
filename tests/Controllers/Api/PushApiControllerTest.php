<?php

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

/**
 * Tests for PushApiController endpoints
 *
 * Tests web push notification features including VAPID keys,
 * subscriptions, and push notification sending.
 */
class PushApiControllerTest extends ApiTestCase
{
    /**
     * Test GET /api/push/vapid-key
     */
    public function testGetVapidKey(): void
    {
        $response = $this->get('/api/push/vapid-key');

        $this->assertEquals('GET', $response['method']);
        $this->assertEquals('/api/push/vapid-key', $response['endpoint']);
    }

    /**
     * Test GET /api/push/vapid-public-key (alias)
     */
    public function testGetVapidPublicKey(): void
    {
        $response = $this->get('/api/push/vapid-public-key');

        $this->assertEquals('/api/push/vapid-public-key', $response['endpoint']);
    }

    /**
     * Test POST /api/push/subscribe
     */
    public function testSubscribeToPush(): void
    {
        $response = $this->post('/api/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/...',
            'keys' => [
                'p256dh' => 'test_p256dh_key',
                'auth' => 'test_auth_key'
            ]
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('endpoint', $response['data']);
        $this->assertArrayHasKey('keys', $response['data']);
    }

    /**
     * Test POST /api/push/unsubscribe
     */
    public function testUnsubscribeFromPush(): void
    {
        $response = $this->post('/api/push/unsubscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/...'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('endpoint', $response['data']);
    }

    /**
     * Test POST /api/push/send
     */
    public function testSendPushNotification(): void
    {
        $response = $this->post('/api/push/send', [
            'user_id' => 2,
            'title' => 'Test Notification',
            'body' => 'This is a test notification',
            'icon' => '/icon.png',
            'url' => '/notifications'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('title', $response['data']);
        $this->assertArrayHasKey('body', $response['data']);
    }

    /**
     * Test GET /api/push/status
     */
    public function testGetPushStatus(): void
    {
        $response = $this->get('/api/push/status');

        $this->assertEquals('/api/push/status', $response['endpoint']);
    }

    /**
     * Test POST /api/push/register-device
     */
    public function testRegisterDevice(): void
    {
        $response = $this->post('/api/push/register-device', [
            'device_token' => 'test_device_token',
            'device_type' => 'web',
            'user_agent' => 'Mozilla/5.0...'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('device_token', $response['data']);
    }

    /**
     * Test POST /api/push/unregister-device
     */
    public function testUnregisterDevice(): void
    {
        $response = $this->post('/api/push/unregister-device', [
            'device_token' => 'test_device_token'
        ]);

        $this->assertEquals('POST', $response['method']);
        $this->assertArrayHasKey('device_token', $response['data']);
    }
}
