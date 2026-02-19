<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Nexus\Tests\Controllers\Api;

use PHPUnit\Framework\TestCase;
use Nexus\Controllers\Api\OnboardingApiController;

class OnboardingApiControllerTest extends TestCase
{
    public function testControllerClassExists(): void
    {
        $this->assertTrue(class_exists(OnboardingApiController::class));
    }

    public function testExtendsBaseApiController(): void
    {
        $reflection = new \ReflectionClass(OnboardingApiController::class);
        $this->assertTrue($reflection->isSubclassOf(\Nexus\Controllers\Api\BaseApiController::class));
    }

    public function testHasStatusMethod(): void
    {
        $reflection = new \ReflectionClass(OnboardingApiController::class);
        $this->assertTrue($reflection->hasMethod('status'));
        $this->assertTrue($reflection->getMethod('status')->isPublic());
    }

    public function testHasCategoriesMethod(): void
    {
        $reflection = new \ReflectionClass(OnboardingApiController::class);
        $this->assertTrue($reflection->hasMethod('categories'));
        $this->assertTrue($reflection->getMethod('categories')->isPublic());
    }

    public function testHasSaveInterestsMethod(): void
    {
        $reflection = new \ReflectionClass(OnboardingApiController::class);
        $this->assertTrue($reflection->hasMethod('saveInterests'));
        $this->assertTrue($reflection->getMethod('saveInterests')->isPublic());
    }

    public function testHasSaveSkillsMethod(): void
    {
        $reflection = new \ReflectionClass(OnboardingApiController::class);
        $this->assertTrue($reflection->hasMethod('saveSkills'));
        $this->assertTrue($reflection->getMethod('saveSkills')->isPublic());
    }

    public function testHasCompleteMethod(): void
    {
        $reflection = new \ReflectionClass(OnboardingApiController::class);
        $this->assertTrue($reflection->hasMethod('complete'));
        $this->assertTrue($reflection->getMethod('complete')->isPublic());
    }
}
