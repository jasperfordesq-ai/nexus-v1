<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Models\User;
use App\Services\AuthService;
use App\Tests\TestCase;

class AuthServiceTest extends TestCase
{
    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AuthService(new User());
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(AuthService::class));
    }

    public function testLoginReturnsNullForInvalidCredentials(): void
    {
        $result = $this->service->login('nonexistent@example.com', 'wrongpassword');
        $this->assertNull($result);
    }

    public function testLoginReturnsNullForEmptyEmail(): void
    {
        $result = $this->service->login('', 'password');
        $this->assertNull($result);
    }

    public function testLogoutReturnsBoolForInvalidToken(): void
    {
        $result = $this->service->logout('invalid-token-string');
        $this->assertFalse($result);
    }

    public function testValidateTokenReturnsNullForInvalidToken(): void
    {
        $result = $this->service->validateToken('invalid-token-string');
        $this->assertNull($result);
    }

    public function testValidateTokenReturnsNullForEmptyToken(): void
    {
        $result = $this->service->validateToken('');
        $this->assertNull($result);
    }

    public function testRefreshTokenReturnsNullForInvalidToken(): void
    {
        $result = $this->service->refreshToken('invalid-token-string');
        $this->assertNull($result);
    }

    public function testLoginMethodSignature(): void
    {
        $ref = new \ReflectionMethod(AuthService::class, 'login');
        $params = $ref->getParameters();
        $this->assertCount(3, $params);
        $this->assertSame('email', $params[0]->getName());
        $this->assertSame('password', $params[1]->getName());
        $this->assertSame('deviceType', $params[2]->getName());
        $this->assertTrue($params[2]->isOptional());
    }

    public function testLogoutMethodSignature(): void
    {
        $ref = new \ReflectionMethod(AuthService::class, 'logout');
        $params = $ref->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('token', $params[0]->getName());
    }

    public function testRefreshTokenMethodSignature(): void
    {
        $ref = new \ReflectionMethod(AuthService::class, 'refreshToken');
        $params = $ref->getParameters();
        $this->assertCount(1, $params);
    }

    public function testConstructorAcceptsUserModel(): void
    {
        $ref = new \ReflectionClass(AuthService::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('user', $params[0]->getName());
    }
}
