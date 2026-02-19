<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use Nexus\Services\DigestService;

class DigestServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(DigestService::class));
    }

    public function testSendWeeklyDigestsMethodExists(): void
    {
        $this->assertTrue(method_exists(DigestService::class, 'sendWeeklyDigests'));
        $ref = new \ReflectionMethod(DigestService::class, 'sendWeeklyDigests');
        $this->assertTrue($ref->isStatic());
    }
}
