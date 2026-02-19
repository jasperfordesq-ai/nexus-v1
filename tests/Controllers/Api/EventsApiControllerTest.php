<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\EventsApiController;

class EventsApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(EventsApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(EventsApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
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

    public function testHasNearbyMethod(): void
    {
        $reflection = new \ReflectionClass(EventsApiController::class);
        $this->assertTrue($reflection->hasMethod('nearby'));
        $this->assertTrue($reflection->getMethod('nearby')->isPublic());
    }

    public function testHasRsvpMethod(): void
    {
        $reflection = new \ReflectionClass(EventsApiController::class);
        $this->assertTrue($reflection->hasMethod('rsvp'));
        $method = $reflection->getMethod('rsvp');
        $this->assertTrue($method->isPublic());
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('id', $params[0]->getName());
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
        $params = $method->getParameters();
        $this->assertEquals('id', $params[0]->getName());
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
        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertEquals('id', $params[0]->getName());
        $this->assertEquals('attendeeId', $params[1]->getName());
    }

    public function testShowMethodAcceptsIntId(): void
    {
        $reflection = new \ReflectionClass(EventsApiController::class);
        $method = $reflection->getMethod('show');
        $params = $method->getParameters();
        $this->assertEquals('int', $params[0]->getType()->getName());
    }
}
