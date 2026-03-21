<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GoalCheckinService;

class GoalCheckinServiceTest extends TestCase
{
    private GoalCheckinService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GoalCheckinService();
    }

    // Uses Eloquent GoalCheckin model with HasTenantScope
    public function test_create_requires_integration_test(): void
    {
        $this->markTestIncomplete('Requires integration test with GoalCheckin model');
    }

    public function test_getByGoalId_requires_integration_test(): void
    {
        $this->markTestIncomplete('Requires integration test with GoalCheckin model');
    }
}
