<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GoalService;

class GoalServiceTest extends TestCase
{
    // GoalService uses Eloquent Goal model with HasTenantScope
    public function test_getAll_requires_integration_test(): void
    {
        $this->markTestIncomplete('Requires integration test with Goal model and HasTenantScope');
    }

    public function test_getPublicForBuddy_requires_integration_test(): void
    {
        $this->markTestIncomplete('Requires integration test with Goal model');
    }
}
