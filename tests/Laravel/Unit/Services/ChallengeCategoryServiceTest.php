<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\ChallengeCategoryService;
use App\Models\ChallengeCategory;
use Illuminate\Support\Facades\DB;
use Mockery;

class ChallengeCategoryServiceTest extends TestCase
{
    private ChallengeCategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChallengeCategoryService();
    }

    public function test_getAll_returns_array(): void
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

        $result = $this->service->create(1, ['name' => 'Test']);
        $this->assertNull($result);
        $this->assertSame('RESOURCE_FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    public function test_create_returns_null_when_name_empty(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['role' => 'admin']);

        $result = $this->service->create(1, ['name' => '']);
        $this->assertNull($result);
        $this->assertSame('VALIDATION_REQUIRED_FIELD', $this->service->getErrors()[0]['code']);
    }

    public function test_create_returns_null_when_duplicate_slug(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_create_returns_id_on_success(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_delete_returns_false_when_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_update_returns_true_when_no_changes(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }
}
