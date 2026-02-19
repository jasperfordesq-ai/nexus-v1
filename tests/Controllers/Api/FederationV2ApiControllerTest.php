<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\FederationV2ApiController;

class FederationV2ApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(FederationV2ApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(FederationV2ApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasStatusMethod(): void
    {
        $reflection = new \ReflectionClass(FederationV2ApiController::class);
        $this->assertTrue($reflection->hasMethod('status'));
        $this->assertTrue($reflection->getMethod('status')->isPublic());
    }

    public function testHasOptInOutMethods(): void
    {
        $reflection = new \ReflectionClass(FederationV2ApiController::class);
        $this->assertTrue($reflection->hasMethod('optIn'));
        $this->assertTrue($reflection->hasMethod('optOut'));
    }

    public function testHasPartnersMethod(): void
    {
        $reflection = new \ReflectionClass(FederationV2ApiController::class);
        $this->assertTrue($reflection->hasMethod('partners'));
        $this->assertTrue($reflection->getMethod('partners')->isPublic());
    }

    public function testHasContentMethods(): void
    {
        $reflection = new \ReflectionClass(FederationV2ApiController::class);
        $methods = ['activity', 'events', 'listings', 'members', 'member'];

        foreach ($methods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Should have {$methodName}");
            $this->assertTrue($reflection->getMethod($methodName)->isPublic());
        }
    }

    public function testHasMessagingMethods(): void
    {
        $reflection = new \ReflectionClass(FederationV2ApiController::class);
        $this->assertTrue($reflection->hasMethod('messages'));
        $this->assertTrue($reflection->hasMethod('sendMessage'));
        $this->assertTrue($reflection->hasMethod('markMessageRead'));

        $method = $reflection->getMethod('markMessageRead');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('id', $params[0]->getName());
    }

    public function testHasSettingsMethods(): void
    {
        $reflection = new \ReflectionClass(FederationV2ApiController::class);
        $this->assertTrue($reflection->hasMethod('getSettings'));
        $this->assertTrue($reflection->hasMethod('updateSettings'));
    }

    public function testMemberMethodAcceptsIntId(): void
    {
        $reflection = new \ReflectionClass(FederationV2ApiController::class);
        $method = $reflection->getMethod('member');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('int', $params[0]->getType()->getName());
    }
}
