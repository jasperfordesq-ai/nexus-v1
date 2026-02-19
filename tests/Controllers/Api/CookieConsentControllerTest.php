<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\CookieConsentController;

class CookieConsentControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(CookieConsentController::class));
    }

    public function testHasShowMethod(): void
    {
        $reflection = new \ReflectionClass(CookieConsentController::class);
        $this->assertTrue($reflection->hasMethod('show'));
        $this->assertTrue($reflection->getMethod('show')->isPublic());
    }

    public function testHasStoreMethod(): void
    {
        $reflection = new \ReflectionClass(CookieConsentController::class);
        $this->assertTrue($reflection->hasMethod('store'));
        $this->assertTrue($reflection->getMethod('store')->isPublic());
    }

    public function testHasUpdateMethod(): void
    {
        $reflection = new \ReflectionClass(CookieConsentController::class);
        $this->assertTrue($reflection->hasMethod('update'));
        $method = $reflection->getMethod('update');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('id', $params[0]->getName());
    }

    public function testHasWithdrawMethod(): void
    {
        $reflection = new \ReflectionClass(CookieConsentController::class);
        $this->assertTrue($reflection->hasMethod('withdraw'));
        $method = $reflection->getMethod('withdraw');
        $params = $method->getParameters();
        $this->assertEquals('id', $params[0]->getName());
    }

    public function testHasInventoryMethod(): void
    {
        $reflection = new \ReflectionClass(CookieConsentController::class);
        $this->assertTrue($reflection->hasMethod('inventory'));
        $this->assertTrue($reflection->getMethod('inventory')->isPublic());
    }

    public function testHasCheckMethod(): void
    {
        $reflection = new \ReflectionClass(CookieConsentController::class);
        $this->assertTrue($reflection->hasMethod('check'));
        $method = $reflection->getMethod('check');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('category', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }
}
