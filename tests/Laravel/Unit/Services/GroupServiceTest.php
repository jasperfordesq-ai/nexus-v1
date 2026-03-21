<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupService;

class GroupServiceTest extends TestCase
{
    // GroupService uses Eloquent Group model with HasTenantScope, complex with/withCount chains
    public function test_getAll_requires_integration_test(): void
    {
        $this->markTestIncomplete('Requires integration test with Group model, HasTenantScope, and related models');
    }
}
