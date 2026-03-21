<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\ChallengeTagService;
use App\Models\ChallengeTag;
use Illuminate\Support\Facades\DB;
use Mockery;

class ChallengeTagServiceTest extends TestCase
{
    private ChallengeTagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChallengeTagService();
    }

    public function test_getAll_returns_array(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_getAll_filters_by_tag_type(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
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

        $this->assertNull($this->service->create(1, ['name' => 'Test']));
    }

    public function test_create_returns_null_when_name_empty(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['role' => 'admin']);

        $this->assertNull($this->service->create(1, ['name' => '']));
    }

    public function test_create_defaults_invalid_tag_type_to_general(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_delete_returns_false_when_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_delete_returns_true_on_success(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }
}
