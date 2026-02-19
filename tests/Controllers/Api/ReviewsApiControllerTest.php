<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\ReviewsApiController;

class ReviewsApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(ReviewsApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(ReviewsApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasUserReviewsMethod(): void
    {
        $reflection = new \ReflectionClass(ReviewsApiController::class);
        $this->assertTrue($reflection->hasMethod('userReviews'));
        $method = $reflection->getMethod('userReviews');
        $this->assertTrue($method->isPublic());
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertEquals('userId', $params[0]->getName());
    }

    public function testHasUserStatsMethod(): void
    {
        $reflection = new \ReflectionClass(ReviewsApiController::class);
        $this->assertTrue($reflection->hasMethod('userStats'));
        $method = $reflection->getMethod('userStats');
        $params = $method->getParameters();
        $this->assertEquals('userId', $params[0]->getName());
    }

    public function testHasUserTrustMethod(): void
    {
        $reflection = new \ReflectionClass(ReviewsApiController::class);
        $this->assertTrue($reflection->hasMethod('userTrust'));
        $method = $reflection->getMethod('userTrust');
        $params = $method->getParameters();
        $this->assertEquals('userId', $params[0]->getName());
    }

    public function testHasPendingMethod(): void
    {
        $reflection = new \ReflectionClass(ReviewsApiController::class);
        $this->assertTrue($reflection->hasMethod('pending'));
        $this->assertTrue($reflection->getMethod('pending')->isPublic());
    }

    public function testHasShowMethod(): void
    {
        $reflection = new \ReflectionClass(ReviewsApiController::class);
        $this->assertTrue($reflection->hasMethod('show'));
        $params = $reflection->getMethod('show')->getParameters();
        $this->assertEquals('id', $params[0]->getName());
    }

    public function testHasStoreMethod(): void
    {
        $reflection = new \ReflectionClass(ReviewsApiController::class);
        $this->assertTrue($reflection->hasMethod('store'));
        $this->assertTrue($reflection->getMethod('store')->isPublic());
    }

    public function testHasDestroyMethod(): void
    {
        $reflection = new \ReflectionClass(ReviewsApiController::class);
        $this->assertTrue($reflection->hasMethod('destroy'));
        $params = $reflection->getMethod('destroy')->getParameters();
        $this->assertEquals('id', $params[0]->getName());
    }
}
