<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\SchemaService;

class SchemaServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(SchemaService::class));
    }

    public function testOrganizationMethodExists(): void
    {
        $this->assertTrue(method_exists(SchemaService::class, 'organization'));
    }

    public function testOrganizationMethodIsStatic(): void
    {
        $ref = new \ReflectionMethod(SchemaService::class, 'organization');
        $this->assertTrue($ref->isStatic());
    }

    public function testOrganizationAcceptsOptionalParams(): void
    {
        $ref = new \ReflectionMethod(SchemaService::class, 'organization');
        $params = $ref->getParameters();

        foreach ($params as $param) {
            $this->assertTrue($param->isOptional(), "Parameter {$param->getName()} should be optional");
        }
    }

    public function testOrganizationReturnsArray(): void
    {
        $ref = new \ReflectionMethod(SchemaService::class, 'organization');
        $returnType = $ref->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }
}
