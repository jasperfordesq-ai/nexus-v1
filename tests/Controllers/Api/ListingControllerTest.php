<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\ListingController;

class ListingControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(ListingController::class));
    }

    public function testHasIndexMethod(): void
    {
        $reflection = new \ReflectionClass(ListingController::class);
        $this->assertTrue($reflection->hasMethod('index'));
        $this->assertTrue($reflection->getMethod('index')->isPublic());
    }

    public function testHasStoreMethod(): void
    {
        $reflection = new \ReflectionClass(ListingController::class);
        $this->assertTrue($reflection->hasMethod('store'));
        $this->assertTrue($reflection->getMethod('store')->isPublic());
    }
}
