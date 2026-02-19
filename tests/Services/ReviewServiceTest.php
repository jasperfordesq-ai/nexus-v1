<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\ReviewService;

class ReviewServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(ReviewService::class));
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['createReview'];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(ReviewService::class, $method),
                "Method {$method} should exist on ReviewService"
            );
        }
    }

    public function testCreateReviewMethodSignature(): void
    {
        $ref = new \ReflectionMethod(ReviewService::class, 'createReview');
        $this->assertTrue($ref->isStatic());

        $params = $ref->getParameters();
        $this->assertEquals('reviewerId', $params[0]->getName());
        $this->assertEquals('receiverId', $params[1]->getName());
        $this->assertEquals('rating', $params[2]->getName());
        $this->assertEquals('comment', $params[3]->getName());
        $this->assertTrue($params[3]->allowsNull());
    }
}
