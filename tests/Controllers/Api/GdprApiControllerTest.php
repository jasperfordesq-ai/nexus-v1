<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\GdprApiController;

class GdprApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(GdprApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(GdprApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasUpdateConsentMethod(): void
    {
        $reflection = new \ReflectionClass(GdprApiController::class);
        $this->assertTrue($reflection->hasMethod('updateConsent'));
        $this->assertTrue($reflection->getMethod('updateConsent')->isPublic());
    }

    public function testHasCreateRequestMethod(): void
    {
        $reflection = new \ReflectionClass(GdprApiController::class);
        $this->assertTrue($reflection->hasMethod('createRequest'));
        $this->assertTrue($reflection->getMethod('createRequest')->isPublic());
    }

    public function testHasDeleteAccountMethod(): void
    {
        $reflection = new \ReflectionClass(GdprApiController::class);
        $this->assertTrue($reflection->hasMethod('deleteAccount'));
        $this->assertTrue($reflection->getMethod('deleteAccount')->isPublic());
    }
}
