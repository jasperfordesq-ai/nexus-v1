<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupNotificationService;

class GroupNotificationServiceTest extends TestCase
{
    private GroupNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupNotificationService();
    }

    // Uses private helpers (getGroupName, getUserName, getGroupAdmins) that query DB
    public function test_notifyJoinRequest_requires_integration_test(): void
    {
        $this->markTestIncomplete('Uses private DB helper methods — requires integration test');
    }
}
