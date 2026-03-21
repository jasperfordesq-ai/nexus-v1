<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\RegistrationService;
use App\Models\User;

class RegistrationServiceTest extends TestCase
{
    private RegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RegistrationService(new User());
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(RegistrationService::class));
    }

    public function testRegisterMethodExists(): void
    {
        $this->assertTrue(method_exists(RegistrationService::class, 'register'));
    }

    public function testVerifyEmailMethodExists(): void
    {
        $this->assertTrue(method_exists(RegistrationService::class, 'verifyEmail'));
    }

    public function testResendVerificationMethodExists(): void
    {
        $this->assertTrue(method_exists(RegistrationService::class, 'resendVerification'));
    }

    public function testRegisterReturnsErrorForMissingFirstName(): void
    {
        $result = $this->service->register([
            'last_name' => 'Doe',
            'email' => 'test@example.com',
            'password' => 'securepass123',
        ], 999999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    public function testRegisterReturnsErrorForMissingEmail(): void
    {
        $result = $this->service->register([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'password' => 'securepass123',
        ], 999999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    public function testRegisterReturnsErrorForShortPassword(): void
    {
        $result = $this->service->register([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'test@example.com',
            'password' => 'short',
        ], 999999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    public function testRegisterReturnsErrorForInvalidEmail(): void
    {
        $result = $this->service->register([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'not-an-email',
            'password' => 'securepass123',
        ], 999999);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('error', $result);
    }

    public function testVerifyEmailSignature(): void
    {
        $ref = new \ReflectionMethod(RegistrationService::class, 'verifyEmail');
        $params = $ref->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('token', $params[0]->getName());
        $this->assertEquals('bool', $ref->getReturnType()->getName());
    }

    public function testResendVerificationReturnsNullForNonExistentEmail(): void
    {
        $result = $this->service->resendVerification('nonexistent@example.com', 999999);
        $this->assertNull($result);
    }
}
