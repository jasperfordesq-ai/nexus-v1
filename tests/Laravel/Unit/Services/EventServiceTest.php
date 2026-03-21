<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\EventService;

class EventServiceTest extends TestCase
{
    // EventService uses Eloquent models heavily (Event, EventRsvp) with complex query chains
    // These are best tested as integration tests with a database

    public function test_getAll_returns_expected_structure(): void
    {
        $this->markTestIncomplete('Requires integration test with Event model and HasTenantScope trait');
    }

    public function test_getAll_filters_by_upcoming_by_default(): void
    {
        $this->markTestIncomplete('Requires integration test with Event model');
    }

    public function test_getAll_applies_cursor_pagination(): void
    {
        $this->markTestIncomplete('Requires integration test with Event model');
    }
}
