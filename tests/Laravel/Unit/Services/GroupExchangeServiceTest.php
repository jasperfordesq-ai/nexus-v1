<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\User;
use App\Services\GroupExchangeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Laravel\TestCase;

class GroupExchangeServiceTest extends TestCase
{
    use DatabaseTransactions;

    private GroupExchangeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GroupExchangeService();
    }

    public function test_create_returns_id(): void
    {
        $organizer = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
        ]);

        $result = $this->service->create((int) $organizer->id, [
            'title' => 'Group Exchange',
            'total_hours' => 10,
            'split_type' => 'equal',
        ]);

        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
        $this->assertDatabaseHas('group_exchanges', [
            'id' => $result,
            'tenant_id' => $this->testTenantId,
            'organizer_id' => $organizer->id,
            'title' => 'Group Exchange',
        ]);
    }

    public function test_get_returns_null_when_not_found(): void
    {
        $this->assertNull($this->service->get(999));
    }
}
