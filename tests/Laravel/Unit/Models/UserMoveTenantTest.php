<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Models;

use Tests\Laravel\TestCase;
use App\Models\User;
use ReflectionMethod;

/**
 * User::moveTenant() Contract Tests
 *
 * Unit tests that verify the moveTenant() method signature and return type
 * without requiring a database connection.
 *
 * The current implementation is a lightweight helper that updates users.tenant_id
 * and returns bool. It takes two required parameters: userId and newTenantId.
 */
class UserMoveTenantTest extends \Tests\Laravel\TestCase
{
    // ==========================================
    // Method Existence & Signature Tests
    // ==========================================

    public function testMoveTenantMethodExists(): void
    {
        $this->assertTrue(
            method_exists(User::class, 'moveTenant'),
            'User::moveTenant() method should exist'
        );
    }

    public function testMoveTenantIsPublicStatic(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');

        $this->assertTrue($method->isPublic(), 'moveTenant should be public');
        $this->assertTrue($method->isStatic(), 'moveTenant should be static');
    }

    public function testMoveTenantParameterCount(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'moveTenant should have 2 parameters');
    }

    public function testMoveTenantParameterNames(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $params = $method->getParameters();

        $this->assertEquals('userId', $params[0]->getName());
        $this->assertEquals('newTenantId', $params[1]->getName());
    }

    public function testMoveTenantParameterTypes(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $params = $method->getParameters();

        $this->assertEquals('int', $params[0]->getType()->getName());
        $this->assertEquals('int', $params[1]->getType()->getName());
    }

    public function testMoveTenantBothParametersRequired(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $params = $method->getParameters();

        $this->assertFalse($params[0]->isOptional(), 'userId should be required');
        $this->assertFalse($params[1]->isOptional(), 'newTenantId should be required');
    }

    public function testMoveTenantReturnType(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType, 'moveTenant should declare a return type');
        $this->assertEquals('bool', $returnType->getName());
    }

    public function testMoveTenantSourceUpdatesTenantId(): void
    {
        $method = new ReflectionMethod(User::class, 'moveTenant');
        $lines = file($method->getFileName());
        $source = implode('', array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));

        $this->assertStringContainsString('tenant_id', $source,
            'moveTenant should update tenant_id');
        $this->assertStringContainsString('newTenantId', $source,
            'moveTenant should use the newTenantId parameter');
    }
}
