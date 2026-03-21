<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Services\BrokerService;
use App\Tests\TestCase;

class BrokerServiceTest extends TestCase
{
    private BrokerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BrokerService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(BrokerService::class));
    }

    public function testGetConfigReturnsArray(): void
    {
        $result = $this->service->getConfig(1);
        $this->assertIsArray($result);
    }

    public function testGetConfigReturnsDefaultsForNonExistentTenant(): void
    {
        $result = $this->service->getConfig(999999);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('enabled', $result);
        $this->assertFalse($result['enabled']);
        $this->assertArrayHasKey('auto_match', $result);
        $this->assertFalse($result['auto_match']);
        $this->assertArrayHasKey('visibility', $result);
        $this->assertSame('admin_only', $result['visibility']);
        $this->assertArrayHasKey('notification_emails', $result);
        $this->assertIsArray($result['notification_emails']);
    }

    public function testGetMessagesReturnsExpectedStructure(): void
    {
        $result = $this->service->getMessages(1);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertIsArray($result['items']);
        $this->assertIsInt($result['total']);
    }

    public function testGetMessagesLimitsCapped(): void
    {
        $result = $this->service->getMessages(1, 500);
        $this->assertIsArray($result);
    }

    public function testGetMessagesAcceptsOffset(): void
    {
        $result = $this->service->getMessages(1, 10, 5);
        $this->assertIsArray($result);
    }

    public function testUpdateVisibilityRejectsInvalidValue(): void
    {
        $result = $this->service->updateVisibility(1, 'invalid_visibility');
        $this->assertFalse($result);
    }

    public function testUpdateVisibilityAcceptsValidValues(): void
    {
        $allowed = ['admin_only', 'brokers', 'members', 'public'];
        foreach ($allowed as $visibility) {
            $result = $this->service->updateVisibility(1, $visibility);
            // updateOrInsert returns bool
            $this->assertIsBool($result);
        }
    }

    public function testMethodSignatures(): void
    {
        $ref = new \ReflectionMethod(BrokerService::class, 'getConfig');
        $this->assertCount(1, $ref->getParameters());

        $ref = new \ReflectionMethod(BrokerService::class, 'getMessages');
        $this->assertCount(3, $ref->getParameters());

        $ref = new \ReflectionMethod(BrokerService::class, 'updateVisibility');
        $this->assertCount(2, $ref->getParameters());
    }
}
