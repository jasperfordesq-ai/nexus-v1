<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\TenantService;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;

class TenantServiceTest extends TestCase
{
    private TenantService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TenantService(new Tenant());
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(TenantService::class));
    }

    public function testBootstrapMethodExists(): void
    {
        $this->assertTrue(method_exists(TenantService::class, 'bootstrap'));
    }

    public function testGetAllMethodExists(): void
    {
        $this->assertTrue(method_exists(TenantService::class, 'getAll'));
    }

    public function testGetSettingsMethodExists(): void
    {
        $this->assertTrue(method_exists(TenantService::class, 'getSettings'));
    }

    public function testBootstrapReturnsNullForNonExistentSlug(): void
    {
        $result = $this->service->bootstrap('nonexistent-slug-xyz-999');
        $this->assertNull($result);
    }

    public function testGetAllReturnsCollection(): void
    {
        $result = $this->service->getAll();
        $this->assertInstanceOf(Collection::class, $result);
    }

    public function testGetSettingsSignature(): void
    {
        $ref = new \ReflectionMethod(TenantService::class, 'getSettings');
        $params = $ref->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('tenantId', $params[0]->getName());
        $this->assertEquals('array', $ref->getReturnType()->getName());
    }

    public function testBootstrapSignature(): void
    {
        $ref = new \ReflectionMethod(TenantService::class, 'bootstrap');
        $params = $ref->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('slug', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }

    public function testBootstrapReturnType(): void
    {
        $ref = new \ReflectionMethod(TenantService::class, 'bootstrap');
        $returnType = $ref->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertTrue($returnType->allowsNull());
    }
}
