<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\ChallengeTemplateService;
use App\Models\ChallengeTemplate;
use Illuminate\Support\Facades\DB;
use Mockery;

class ChallengeTemplateServiceTest extends TestCase
{
    private ChallengeTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChallengeTemplateService();
    }

    public function test_getAll_returns_array(): void
    {
        $this->markTestIncomplete('Requires integration test — uses with() eager loading');
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_create_returns_null_when_not_admin(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['role' => 'member']);

        $this->assertNull($this->service->create(1, ['title' => 'Test']));
    }

    public function test_create_returns_null_when_title_empty(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['role' => 'admin']);

        $this->assertNull($this->service->create(1, ['title' => '']));
    }

    public function test_create_returns_id_on_success(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_delete_returns_true_on_success(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_getTemplateData_returns_null_when_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_update_returns_true_when_no_changes(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }
}
