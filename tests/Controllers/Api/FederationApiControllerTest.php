<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\FederationApiController;

class FederationApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(FederationApiController::class));
    }

    public function testHasIndexMethod(): void
    {
        $reflection = new \ReflectionClass(FederationApiController::class);
        $this->assertTrue($reflection->hasMethod('index'));
        $this->assertTrue($reflection->getMethod('index')->isPublic());
    }

    public function testHasTimebanksMethod(): void
    {
        $reflection = new \ReflectionClass(FederationApiController::class);
        $this->assertTrue($reflection->hasMethod('timebanks'));
        $this->assertTrue($reflection->getMethod('timebanks')->isPublic());
    }

    public function testHasMembersMethod(): void
    {
        $reflection = new \ReflectionClass(FederationApiController::class);
        $this->assertTrue($reflection->hasMethod('members'));
        $this->assertTrue($reflection->getMethod('members')->isPublic());
    }

    public function testHasMemberMethod(): void
    {
        $reflection = new \ReflectionClass(FederationApiController::class);
        $this->assertTrue($reflection->hasMethod('member'));
        $params = $reflection->getMethod('member')->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('id', $params[0]->getName());
    }

    public function testHasListingsMethod(): void
    {
        $reflection = new \ReflectionClass(FederationApiController::class);
        $this->assertTrue($reflection->hasMethod('listings'));
        $this->assertTrue($reflection->hasMethod('listing'));
    }

    public function testHasSendMessageMethod(): void
    {
        $reflection = new \ReflectionClass(FederationApiController::class);
        $this->assertTrue($reflection->hasMethod('sendMessage'));
        $this->assertTrue($reflection->getMethod('sendMessage')->isPublic());
    }

    public function testHasCreateTransactionMethod(): void
    {
        $reflection = new \ReflectionClass(FederationApiController::class);
        $this->assertTrue($reflection->hasMethod('createTransaction'));
        $this->assertTrue($reflection->getMethod('createTransaction')->isPublic());
    }

    public function testHasOauthTokenMethod(): void
    {
        $reflection = new \ReflectionClass(FederationApiController::class);
        $this->assertTrue($reflection->hasMethod('oauthToken'));
        $this->assertTrue($reflection->getMethod('oauthToken')->isPublic());
    }

    public function testHasTestWebhookMethod(): void
    {
        $reflection = new \ReflectionClass(FederationApiController::class);
        $this->assertTrue($reflection->hasMethod('testWebhook'));
        $this->assertTrue($reflection->getMethod('testWebhook')->isPublic());
    }
}
