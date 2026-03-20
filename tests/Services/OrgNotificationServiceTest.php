<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Tests\Services;

use Nexus\Tests\TestCase;
use App\Services\OrgNotificationService;

class OrgNotificationServiceTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(OrgNotificationService::class));
    }
}
