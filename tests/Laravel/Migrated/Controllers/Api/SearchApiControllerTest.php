<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Migrated\Controllers\Api;

use Tests\Laravel\LegacyBridgeTestCase;
use App\Http\Controllers\Api\SearchApiController;

/**
 * Tests for SearchApiController (Laravel migration)
 *
 * Migrated from: Nexus\Tests\Controllers\Api\SearchApiControllerTest
 * Original base: PHPUnit\Framework\TestCase -> now LegacyBridgeTestCase
 */
class SearchApiControllerTest extends LegacyBridgeTestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(SearchApiController::class));
    }

    public function testHasIndexMethod(): void
    {
        $reflection = new \ReflectionClass(SearchApiController::class);
        $this->assertTrue($reflection->hasMethod('index'));
        $this->assertTrue($reflection->getMethod('index')->isPublic());
    }

    public function testHasSuggestionsMethod(): void
    {
        $reflection = new \ReflectionClass(SearchApiController::class);
        $this->assertTrue($reflection->hasMethod('suggestions'));
        $this->assertTrue($reflection->getMethod('suggestions')->isPublic());
    }
}
