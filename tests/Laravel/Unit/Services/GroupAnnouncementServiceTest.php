<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\GroupAnnouncementService;
use Illuminate\Support\Facades\DB;

class GroupAnnouncementServiceTest extends TestCase
{
    private GroupAnnouncementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupAnnouncementService();
    }

    public function test_list_returns_null_when_user_not_member(): void
    {
        DB::shouldReceive('selectOne')->andReturn(null); // isMember check

        $this->markTestIncomplete('isMember is a private method — requires integration test');
    }

    public function test_getErrors_returns_array(): void
    {
        $this->assertIsArray($this->service->getErrors());
    }
}
