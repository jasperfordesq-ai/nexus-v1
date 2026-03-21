<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Tests\Services;

use App\Tests\TestCase;
use App\Services\SeoService;

class SeoServiceTest extends TestCase
{
    private SeoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SeoService();
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(SeoService::class));
    }

    public function testGetMetadataSignature(): void
    {
        $ref = new \ReflectionMethod(SeoService::class, 'getMetadata');
        $params = $ref->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('tenantId', $params[0]->getName());
        $this->assertEquals('path', $params[1]->getName());
        $this->assertEquals('array', $ref->getReturnType()->getName());
    }

    public function testUpdateMetadataMethodExists(): void
    {
        $this->assertTrue(method_exists(SeoService::class, 'updateMetadata'));
    }

    public function testGetRedirectsSignature(): void
    {
        $ref = new \ReflectionMethod(SeoService::class, 'getRedirects');
        $params = $ref->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('tenantId', $params[0]->getName());
        $this->assertEquals('array', $ref->getReturnType()->getName());
    }

    public function testCreateRedirectMethodExists(): void
    {
        $this->assertTrue(method_exists(SeoService::class, 'createRedirect'));
    }

    public function testCreateRedirectSignature(): void
    {
        $ref = new \ReflectionMethod(SeoService::class, 'createRedirect');
        $params = $ref->getParameters();
        $this->assertCount(4, $params);
        $this->assertEquals('tenantId', $params[0]->getName());
        $this->assertEquals('from', $params[1]->getName());
        $this->assertEquals('to', $params[2]->getName());
        $this->assertEquals('statusCode', $params[3]->getName());
        $this->assertTrue($params[3]->isOptional());
        $this->assertEquals(301, $params[3]->getDefaultValue());
    }

    public function testUpdateMetadataFiltersAllowedFields(): void
    {
        $ref = new \ReflectionMethod(SeoService::class, 'updateMetadata');
        $params = $ref->getParameters();
        $this->assertCount(3, $params);
        $this->assertEquals('tenantId', $params[0]->getName());
        $this->assertEquals('path', $params[1]->getName());
        $this->assertEquals('data', $params[2]->getName());
    }
}
