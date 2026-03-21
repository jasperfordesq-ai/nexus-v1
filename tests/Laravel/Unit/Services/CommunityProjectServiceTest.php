<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\CommunityProjectService;
use Illuminate\Support\Facades\DB;

class CommunityProjectServiceTest extends TestCase
{
    private CommunityProjectService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommunityProjectService();
    }

    public function test_propose_returns_error_when_title_empty(): void
    {
        $result = $this->service->propose(1, ['title' => '', 'description' => 'Test']);
        $this->assertEmpty($result);
        $this->assertSame('VALIDATION_ERROR', $this->service->getErrors()[0]['code']);
        $this->assertSame('title', $this->service->getErrors()[0]['field']);
    }

    public function test_propose_returns_error_when_description_empty(): void
    {
        $result = $this->service->propose(1, ['title' => 'Project', 'description' => '']);
        $this->assertEmpty($result);
        $this->assertSame('description', $this->service->getErrors()[0]['field']);
    }

    public function test_propose_returns_error_for_invalid_date_format(): void
    {
        $result = $this->service->propose(1, [
            'title' => 'Project',
            'description' => 'Description',
            'proposed_date' => 'not-a-date',
        ]);
        $this->assertEmpty($result);
        $this->assertSame('proposed_date', $this->service->getErrors()[0]['field']);
    }

    public function test_review_returns_false_for_invalid_status(): void
    {
        $result = $this->service->review(1, 'invalid_status', null, 1, 2);
        $this->assertFalse($result);
    }

    public function test_review_returns_false_when_project_not_found(): void
    {
        DB::shouldReceive('table')->with('vol_community_projects')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $result = $this->service->review(999, 'approved', null, 1, 2);
        $this->assertFalse($result);
    }

    public function test_review_returns_false_when_status_not_reviewable(): void
    {
        DB::shouldReceive('table')->with('vol_community_projects')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) ['status' => 'completed']);

        $result = $this->service->review(1, 'approved', null, 1, 2);
        $this->assertFalse($result);
    }

    public function test_support_returns_false_when_project_not_found(): void
    {
        DB::shouldReceive('table')->with('vol_community_projects')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('exists')->andReturn(false);

        $result = $this->service->support(999, 1, 2);
        $this->assertFalse($result);
    }

    public function test_unsupport_returns_false_when_not_supported(): void
    {
        DB::shouldReceive('table')->with('vol_community_project_supporters')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('delete')->andReturn(0);

        $result = $this->service->unsupport(1, 1, 2);
        $this->assertFalse($result);
    }

    public function test_getProposal_returns_null_when_not_found(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('leftJoin')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $result = $this->service->getProposal(999);
        $this->assertNull($result);
    }

    public function test_updateProposal_returns_error_when_project_not_found(): void
    {
        DB::shouldReceive('table')->with('vol_community_projects')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('first')->andReturnNull();

        $result = $this->service->updateProposal(999, 1, ['title' => 'New']);
        $this->assertFalse($result);
        $this->assertSame('NOT_FOUND', $this->service->getErrors()[0]['code']);
    }
}
