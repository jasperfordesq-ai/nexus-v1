<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Models;

use App\Models\Event;
use PHPUnit\Framework\TestCase;

final class EventTimeIdentityModelTest extends TestCase
{
    public function test_all_day_is_cast_to_boolean_without_activating_identity_writers(): void
    {
        $event = (new Event())->forceFill(['all_day' => 1]);

        $this->assertTrue($event->all_day);
        $this->assertSame('boolean', $event->getCasts()['all_day']);
        $this->assertNotContains('timezone_source', $event->getFillable());
        $this->assertNotContains('occurrence_key', $event->getFillable());
        $this->assertNotContains('recurrence_engine', $event->getFillable());
        $this->assertNotContains('recurrence_engine_version', $event->getFillable());
    }
}
