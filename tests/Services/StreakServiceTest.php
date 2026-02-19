<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\StreakService;

class StreakServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(StreakService::class));
    }

    public function testStreakTypesConstant(): void
    {
        $types = StreakService::STREAK_TYPES;

        $this->assertIsArray($types);
        $this->assertContains('login', $types);
        $this->assertContains('activity', $types);
        $this->assertContains('giving', $types);
        $this->assertContains('volunteer', $types);
    }

    public function testRecordActivityMethodExists(): void
    {
        $this->assertTrue(method_exists(StreakService::class, 'recordActivity'));
    }

    public function testRecordActivityIsStatic(): void
    {
        $ref = new \ReflectionMethod(StreakService::class, 'recordActivity');
        $this->assertTrue($ref->isStatic());
    }
}
