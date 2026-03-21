<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CampaignService;
use App\Models\Campaign;
use Illuminate\Support\Facades\DB;
use Mockery;

class CampaignServiceTest extends TestCase
{
    private CampaignService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CampaignService();
    }

    public function test_getErrors_returns_empty_initially(): void
    {
        $this->assertEmpty($this->service->getErrors());
    }

    public function test_create_returns_null_when_not_admin(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['role' => 'member']);

        $id = $this->service->create(1, ['title' => 'Test']);

        $this->assertNull($id);
        $errors = $this->service->getErrors();
        $this->assertSame('RESOURCE_FORBIDDEN', $errors[0]['code']);
    }

    public function test_create_returns_null_when_title_empty(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['role' => 'admin']);

        $id = $this->service->create(1, ['title' => '']);

        $this->assertNull($id);
        $errors = $this->service->getErrors();
        $this->assertSame('VALIDATION_REQUIRED_FIELD', $errors[0]['code']);
    }

    public function test_create_returns_id_on_success(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_update_returns_false_when_not_admin(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['role' => 'member']);

        $result = $this->service->update(1, 1, ['title' => 'Updated']);
        $this->assertFalse($result);
    }

    public function test_update_returns_false_when_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_delete_returns_false_when_not_admin(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['role' => 'member']);

        $result = $this->service->delete(1, 1);
        $this->assertFalse($result);
    }

    public function test_delete_returns_true_on_success(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_linkChallenge_returns_false_when_not_admin(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['role' => 'member']);

        $result = $this->service->linkChallenge(1, 1, 1);
        $this->assertFalse($result);
    }

    public function test_unlinkChallenge_returns_false_when_not_admin(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['role' => 'member']);

        $result = $this->service->unlinkChallenge(1, 1, 1);
        $this->assertFalse($result);
    }

    public function test_getAll_returns_expected_structure(): void
    {
        $this->markTestIncomplete('Requires integration test — complex DB query with subselects');
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — uses DB table with joins');
    }
}
