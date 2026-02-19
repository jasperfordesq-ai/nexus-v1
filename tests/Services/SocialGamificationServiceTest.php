<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\SocialGamificationService;

class SocialGamificationServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(SocialGamificationService::class));
    }

    public function testGetFriendComparisonMethodExists(): void
    {
        $this->assertTrue(method_exists(SocialGamificationService::class, 'getFriendComparison'));
        $ref = new \ReflectionMethod(SocialGamificationService::class, 'getFriendComparison');
        $this->assertTrue($ref->isStatic());
    }
}
