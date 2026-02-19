<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\UploadService;

class UploadServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(UploadService::class));
    }

    public function testPublicMethodsExist(): void
    {
        $this->assertTrue(method_exists(UploadService::class, 'handleUpload'));
    }

    public function testHandleUploadMethodSignature(): void
    {
        $ref = new \ReflectionMethod(UploadService::class, 'handleUpload');
        $this->assertFalse($ref->isStatic());

        $params = $ref->getParameters();
        $this->assertEquals('file', $params[0]->getName());
        $this->assertEquals('destination', $params[1]->getName());
        $this->assertEquals('newFilename', $params[2]->getName());
        $this->assertTrue($params[2]->isOptional());
    }
}
