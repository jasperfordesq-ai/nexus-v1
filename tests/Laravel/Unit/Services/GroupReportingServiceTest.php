<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupReportingService;

class GroupReportingServiceTest extends TestCase
{
    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(GroupReportingService::class));
    }

    public function test_has_public_methods(): void
    {
        $ref = new \ReflectionClass(GroupReportingService::class);
        $this->assertTrue($ref->hasMethod('sendAllWeeklyDigests'));
        $this->assertTrue($ref->getMethod('sendAllWeeklyDigests')->isPublic());
        $this->assertTrue($ref->getMethod('sendAllWeeklyDigests')->isStatic());
    }
}
