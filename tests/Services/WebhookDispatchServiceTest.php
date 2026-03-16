<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Services;

use Nexus\Tests\DatabaseTestCase;
use Nexus\Core\Database;
use Nexus\Core\TenantContext;
use Nexus\Services\WebhookDispatchService;

class WebhookDispatchServiceTest extends DatabaseTestCase
{
    private const TENANT_ID = 2;

    /** @var int|null */
    private static ?int $testWebhookId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        TenantContext::setById(self::TENANT_ID);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up any test webhooks that may have been committed outside transactions
        try {
            $pdo = Database::getConnection();
            $pdo->prepare(
                "DELETE FROM outbound_webhook_logs WHERE tenant_id = ? AND webhook_id IN
                 (SELECT id FROM outbound_webhooks WHERE tenant_id = ? AND name LIKE 'Test Webhook%')"
            )->execute([self::TENANT_ID, self::TENANT_ID]);
            $pdo->prepare(
                "DELETE FROM outbound_webhooks WHERE tenant_id = ? AND name LIKE 'Test Webhook%'"
            )->execute([self::TENANT_ID]);
        } catch (\Exception $e) {
            // Tables may not exist in test DB — ignore
        }

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ========================================================================
    // Class & Method Existence
    // ========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(WebhookDispatchService::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = [
            'dispatch',
            'getWebhooks',
            'createWebhook',
            'updateWebhook',
            'deleteWebhook',
            'testWebhook',
            'getLogs',
            'retryFailed',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(WebhookDispatchService::class, $method),
                "Method {$method} should exist on WebhookDispatchService"
            );
        }
    }

    public function testMethodsAreStatic(): void
    {
        $ref = new \ReflectionClass(WebhookDispatchService::class);

        $publicMethods = [
            'dispatch',
            'getWebhooks',
            'createWebhook',
            'updateWebhook',
            'deleteWebhook',
            'testWebhook',
            'getLogs',
            'retryFailed',
        ];

        foreach ($publicMethods as $methodName) {
            $method = $ref->getMethod($methodName);
            $this->assertTrue($method->isStatic(), "Method {$methodName} should be static");
        }
    }

    // ========================================================================
    // createWebhook
    // ========================================================================

    public function testCreateWebhookReturnsArrayWithExpectedKeys(): void
    {
        $result = WebhookDispatchService::createWebhook(0, [
            'name'   => 'Test Webhook Create',
            'url'    => 'https://example.com/webhook',
            'secret' => 'test-secret-123',
            'events' => ['exchange.completed'],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('events', $result);
        $this->assertArrayHasKey('is_active', $result);
        $this->assertIsInt($result['id']);
        $this->assertGreaterThan(0, $result['id']);
        $this->assertSame('Test Webhook Create', $result['name']);
        $this->assertSame('https://example.com/webhook', $result['url']);
    }

    public function testCreateWebhookRequiresName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name');

        WebhookDispatchService::createWebhook(0, [
            'name'   => '',
            'url'    => 'https://example.com/hook',
            'secret' => 'secret',
            'events' => ['exchange.completed'],
        ]);
    }

    public function testCreateWebhookRequiresValidUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WebhookDispatchService::createWebhook(0, [
            'name'   => 'Test Webhook Bad URL',
            'url'    => 'not-a-url',
            'secret' => 'secret',
            'events' => ['exchange.completed'],
        ]);
    }

    public function testCreateWebhookRequiresEventsArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('event');

        WebhookDispatchService::createWebhook(0, [
            'name'   => 'Test Webhook No Events',
            'url'    => 'https://example.com/hook',
            'secret' => 'secret',
            'events' => [],
        ]);
    }

    public function testCreateWebhookRequiresSecret(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('secret');

        WebhookDispatchService::createWebhook(0, [
            'name'   => 'Test Webhook No Secret',
            'url'    => 'https://example.com/hook',
            'secret' => '',
            'events' => ['exchange.completed'],
        ]);
    }

    public function testCreateWebhookWithSecretStoresSecret(): void
    {
        $result = WebhookDispatchService::createWebhook(0, [
            'name'   => 'Test Webhook Secret Check',
            'url'    => 'https://example.com/webhook-secret',
            'secret' => 'my-super-secret',
            'events' => ['user.created'],
        ]);

        $this->assertIsInt($result['id']);

        // Verify the secret was stored in the database
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT secret FROM outbound_webhooks WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$result['id'], self::TENANT_ID]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotNull($row);
        $this->assertSame('my-super-secret', $row['secret']);
    }

    public function testCreateWebhookSetsActiveByDefault(): void
    {
        $result = WebhookDispatchService::createWebhook(0, [
            'name'   => 'Test Webhook Active Default',
            'url'    => 'https://example.com/hook-active',
            'secret' => 'secret',
            'events' => ['exchange.completed'],
        ]);

        $this->assertEquals(1, $result['is_active']);
    }

    public function testCreateWebhookStoresEventsArray(): void
    {
        $events = ['exchange.completed', 'user.created', 'listing.published'];

        $result = WebhookDispatchService::createWebhook(0, [
            'name'   => 'Test Webhook Multi Events',
            'url'    => 'https://example.com/multi-events',
            'secret' => 'secret',
            'events' => $events,
        ]);

        $this->assertIsArray($result['events']);
        $this->assertCount(3, $result['events']);
        $this->assertSame($events, $result['events']);
    }

    // ========================================================================
    // getWebhooks
    // ========================================================================

    public function testGetWebhooksReturnsArray(): void
    {
        $result = WebhookDispatchService::getWebhooks();
        $this->assertIsArray($result);
    }

    public function testGetWebhooksIncludesCreatedWebhook(): void
    {
        $created = WebhookDispatchService::createWebhook(0, [
            'name'   => 'Test Webhook List Check',
            'url'    => 'https://example.com/list-check',
            'secret' => 'secret',
            'events' => ['exchange.completed'],
        ]);

        $webhooks = WebhookDispatchService::getWebhooks();
        $found = false;
        foreach ($webhooks as $wh) {
            if ((int) $wh['id'] === $created['id']) {
                $found = true;
                $this->assertSame('Test Webhook List Check', $wh['name']);
                $this->assertIsArray($wh['events']);
                break;
            }
        }
        $this->assertTrue($found, 'Created webhook should appear in getWebhooks list');
    }

    // ========================================================================
    // updateWebhook
    // ========================================================================

    public function testUpdateWebhookReturnsTrue(): void
    {
        $created = WebhookDispatchService::createWebhook(0, [
            'name'   => 'Test Webhook Update',
            'url'    => 'https://example.com/update',
            'secret' => 'secret',
            'events' => ['exchange.completed'],
        ]);

        $result = WebhookDispatchService::updateWebhook($created['id'], [
            'name' => 'Test Webhook Updated Name',
        ]);

        $this->assertTrue($result);
    }

    public function testUpdateWebhookChangesName(): void
    {
        $created = WebhookDispatchService::createWebhook(0, [
            'name'   => 'Test Webhook Before Update',
            'url'    => 'https://example.com/before-update',
            'secret' => 'secret',
            'events' => ['exchange.completed'],
        ]);

        WebhookDispatchService::updateWebhook($created['id'], [
            'name' => 'Test Webhook After Update',
        ]);

        // Verify by fetching from DB
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            "SELECT name FROM outbound_webhooks WHERE id = ? AND tenant_id = ?"
        );
        $stmt->execute([$created['id'], self::TENANT_ID]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('Test Webhook After Update', $row['name']);
    }

    public function testUpdateWebhookWithInvalidUrlThrows(): void
    {
        $created = WebhookDispatchService::createWebhook(0, [
            'name'   => 'Test Webhook Update Bad URL',
            'url'    => 'https://example.com/good-url',
            'secret' => 'secret',
            'events' => ['exchange.completed'],
        ]);

        $this->expectException(\InvalidArgumentException::class);

        WebhookDispatchService::updateWebhook($created['id'], [
            'url' => 'not-a-url',
        ]);
    }

    // ========================================================================
    // deleteWebhook
    // ========================================================================

    public function testDeleteWebhookReturnsTrue(): void
    {
        $created = WebhookDispatchService::createWebhook(0, [
            'name'   => 'Test Webhook Delete',
            'url'    => 'https://example.com/delete',
            'secret' => 'secret',
            'events' => ['exchange.completed'],
        ]);

        $result = WebhookDispatchService::deleteWebhook($created['id']);
        $this->assertTrue($result);
    }

    public function testDeleteNonExistentWebhookReturnsFalse(): void
    {
        $result = WebhookDispatchService::deleteWebhook(999999);
        $this->assertFalse($result);
    }

    // ========================================================================
    // getLogs
    // ========================================================================

    public function testGetLogsReturnsArrayForNewWebhook(): void
    {
        $created = WebhookDispatchService::createWebhook(0, [
            'name'   => 'Test Webhook Logs',
            'url'    => 'https://example.com/logs',
            'secret' => 'secret',
            'events' => ['exchange.completed'],
        ]);

        $logs = WebhookDispatchService::getLogs($created['id']);
        $this->assertIsArray($logs);
        $this->assertEmpty($logs);
    }

    // ========================================================================
    // dispatch
    // ========================================================================

    public function testDispatchWithNoMatchingWebhooksDoesNotThrow(): void
    {
        // Dispatch an event type that no webhook subscribes to — should complete without error
        WebhookDispatchService::dispatch('nonexistent.event.type.test', ['foo' => 'bar']);

        // If we reach here, no exception was thrown
        $this->assertTrue(true);
    }

    // ========================================================================
    // retryFailed
    // ========================================================================

    public function testRetryFailedReturnsIntZeroWhenNoFailedLogs(): void
    {
        $result = WebhookDispatchService::retryFailed();
        $this->assertIsInt($result);
        $this->assertSame(0, $result);
    }

    // ========================================================================
    // testWebhook (HTTP-dependent — skipped)
    // ========================================================================

    public function testTestWebhookThrowsForNonExistentWebhook(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found');

        WebhookDispatchService::testWebhook(999999);
    }

    public function testTestWebhookMethodSignature(): void
    {
        $ref = new \ReflectionMethod(WebhookDispatchService::class, 'testWebhook');

        $this->assertTrue($ref->isPublic());
        $this->assertTrue($ref->isStatic());
        $this->assertCount(1, $ref->getParameters());
        $this->assertSame('id', $ref->getParameters()[0]->getName());
    }
}
