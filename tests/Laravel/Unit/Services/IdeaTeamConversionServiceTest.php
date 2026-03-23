<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\IdeaTeamLink;
use App\Services\IdeaTeamConversionService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class IdeaTeamConversionServiceTest extends TestCase
{
    private IdeaTeamConversionService $service;
    private $ideaTeamLinkAlias;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ideaTeamLinkAlias = Mockery::mock('alias:' . IdeaTeamLink::class);
        $this->service = new IdeaTeamConversionService();
    }

    public function test_convert_idea_not_found_returns_null(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $result = $this->service->convert(999, 1);
        $this->assertNull($result);
        $this->assertSame('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_convert_unauthorized_returns_null(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(
            (object) ['id' => 1, 'title' => 'Idea', 'description' => 'Desc', 'user_id' => 5, 'challenge_id' => 1, 'status' => 'selected'],
            (object) ['user_id' => 3], // challenge creator
            (object) ['role' => 'member'], // isAdmin check
        );

        $result = $this->service->convert(1, 99);
        $this->assertNull($result);
        $this->assertSame('RESOURCE_FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    public function test_convert_already_converted_returns_null(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(
            (object) ['id' => 1, 'title' => 'Idea', 'description' => 'Desc', 'user_id' => 10, 'challenge_id' => 1, 'status' => 'selected'],
        );

        $this->ideaTeamLinkAlias->shouldReceive('where')->with('idea_id', 1)->andReturnSelf();
        $this->ideaTeamLinkAlias->shouldReceive('first')->andReturn((object) ['id' => 5]);

        $result = $this->service->convert(1, 10);
        $this->assertNull($result);
        $this->assertSame('RESOURCE_CONFLICT', $this->service->getErrors()[0]['code']);
    }

    public function test_getLinksForChallenge_returns_array(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getLinksForChallenge(1);
        $this->assertSame([], $result);
    }

    public function test_getLinkForIdea_returns_null_when_not_found(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->assertNull($this->service->getLinkForIdea(999));
    }
}
