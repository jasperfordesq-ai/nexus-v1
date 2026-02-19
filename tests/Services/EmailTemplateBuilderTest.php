<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\EmailTemplateBuilder;

class EmailTemplateBuilderTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(EmailTemplateBuilder::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['setBrandColors', 'setLogo', 'setPreviewText', 'setUnsubscribeToken'];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(EmailTemplateBuilder::class, $method),
                "Method {$method} should exist on EmailTemplateBuilder"
            );
        }
    }

    public function testSetBrandColorsReturnsSelf(): void
    {
        $ref = new \ReflectionMethod(EmailTemplateBuilder::class, 'setBrandColors');
        $returnType = $ref->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('self', $returnType->getName());
    }

    public function testSetLogoReturnsSelf(): void
    {
        $ref = new \ReflectionMethod(EmailTemplateBuilder::class, 'setLogo');
        $returnType = $ref->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('self', $returnType->getName());
    }
}
