<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Migrated\Controllers\Api;

use Tests\Laravel\LegacyBridgeTestCase;
use App\Http\Controllers\Api\ListingsController as ListingsApiController;

/**
 * Tests for ListingsApiController (Laravel migration)
 *
 * Migrated from: Nexus\Tests\Controllers\Api\ListingsApiControllerTest
 * Original base: PHPUnit\Framework\TestCase -> now LegacyBridgeTestCase
 *
 * Note: The original used Nexus\Controllers\Api\ListingsApiController.
 * Updated import to App\Http\Controllers\Api\ListingsApiController for Laravel.
 */
class ListingsApiControllerTest extends LegacyBridgeTestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(ListingsApiController::class));
    }

    public function testHasIndexMethod(): void
    {
        $reflection = new \ReflectionClass(ListingsApiController::class);
        $this->assertTrue($reflection->hasMethod('index'));
        $method = $reflection->getMethod('index');
        $this->assertTrue($method->isPublic());
    }

    public function testHasShowMethod(): void
    {
        $reflection = new \ReflectionClass(ListingsApiController::class);
        $this->assertTrue($reflection->hasMethod('show'));
        $method = $reflection->getMethod('show');
        $this->assertTrue($method->isPublic());
    }

    public function testHasStoreMethod(): void
    {
        $reflection = new \ReflectionClass(ListingsApiController::class);
        $this->assertTrue($reflection->hasMethod('store'));
        $method = $reflection->getMethod('store');
        $this->assertTrue($method->isPublic());
    }

    public function testHasUpdateMethod(): void
    {
        $reflection = new \ReflectionClass(ListingsApiController::class);
        $this->assertTrue($reflection->hasMethod('update'));
        $method = $reflection->getMethod('update');
        $this->assertTrue($method->isPublic());
    }

    public function testHasDestroyMethod(): void
    {
        $reflection = new \ReflectionClass(ListingsApiController::class);
        $this->assertTrue($reflection->hasMethod('destroy'));
        $method = $reflection->getMethod('destroy');
        $this->assertTrue($method->isPublic());
    }
}
