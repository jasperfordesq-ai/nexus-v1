<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Migrated\Controllers\Api;

use Tests\Laravel\LegacyBridgeTestCase;
use App\Http\Controllers\Api\EventsController as EventsApiController;

/**
 * Tests for EventsApiController (Laravel migration)
 *
 * Migrated from: Nexus\Tests\Controllers\Api\EventsApiControllerTest
 * Original base: PHPUnit\Framework\TestCase -> now LegacyBridgeTestCase
 */
class EventsApiControllerTest extends LegacyBridgeTestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(EventsApiController::class));
    }

    public function testHasRequiredCrudMethods(): void
    {
        $reflection = new \ReflectionClass(EventsApiController::class);
        $methods = ['index', 'show', 'store', 'update', 'destroy'];

        foreach ($methods as $methodName) {
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                "Controller should have {$methodName} method"
            );
            $this->assertTrue(
                $reflection->getMethod($methodName)->isPublic(),
                "Method {$methodName} should be public"
            );
        }
    }

    public function testHasRsvpMethod(): void
    {
        $reflection = new \ReflectionClass(EventsApiController::class);
        $this->assertTrue($reflection->hasMethod('rsvp'));
        $method = $reflection->getMethod('rsvp');
        $this->assertTrue($method->isPublic());
    }

    public function testHasRemoveRsvpMethod(): void
    {
        $reflection = new \ReflectionClass(EventsApiController::class);
        $this->assertTrue($reflection->hasMethod('removeRsvp'));
        $method = $reflection->getMethod('removeRsvp');
        $this->assertTrue($method->isPublic());
    }

    public function testHasAttendeesMethod(): void
    {
        $reflection = new \ReflectionClass(EventsApiController::class);
        $this->assertTrue($reflection->hasMethod('attendees'));
        $method = $reflection->getMethod('attendees');
        $this->assertTrue($method->isPublic());
    }

    public function testHasUploadImageMethod(): void
    {
        $reflection = new \ReflectionClass(EventsApiController::class);
        $this->assertTrue($reflection->hasMethod('uploadImage'));
        $method = $reflection->getMethod('uploadImage');
        $this->assertTrue($method->isPublic());
    }

    public function testHasCheckInMethod(): void
    {
        $reflection = new \ReflectionClass(EventsApiController::class);
        $this->assertTrue($reflection->hasMethod('checkIn'));
        $method = $reflection->getMethod('checkIn');
        $this->assertTrue($method->isPublic());
    }
}
