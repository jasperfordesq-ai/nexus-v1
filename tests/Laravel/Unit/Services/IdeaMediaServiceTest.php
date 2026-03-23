<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\IdeaMedia;
use App\Services\IdeaMediaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class IdeaMediaServiceTest extends TestCase
{
    private IdeaMediaService $service;
    private $ideaMediaAlias;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ideaMediaAlias = Mockery::mock('alias:' . IdeaMedia::class);
        $this->service = new IdeaMediaService();
    }

    public function test_getErrors_initially_empty(): void
    {
        $this->assertSame([], $this->service->getErrors());
    }

    public function test_addMedia_idea_not_found_returns_null(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $result = $this->service->addMedia(999, 1, ['url' => 'http://example.com/img.jpg']);
        $this->assertNull($result);
        $this->assertSame('RESOURCE_NOT_FOUND', $this->service->getErrors()[0]['code']);
    }

    public function test_addMedia_not_author_not_admin_returns_null(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(
            (object) ['id' => 1, 'user_id' => 5, 'challenge_id' => 1], // idea
            (object) ['role' => 'member'], // user role check
        );

        $result = $this->service->addMedia(1, 99, ['url' => 'http://example.com/img.jpg']);
        $this->assertNull($result);
        $this->assertSame('RESOURCE_FORBIDDEN', $this->service->getErrors()[0]['code']);
    }

    public function test_addMedia_empty_url_returns_null(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(
            (object) ['id' => 1, 'user_id' => 10, 'challenge_id' => 1],
        );

        $result = $this->service->addMedia(1, 10, ['url' => '']);
        $this->assertNull($result);
        $this->assertSame('VALIDATION_REQUIRED_FIELD', $this->service->getErrors()[0]['code']);
    }

    public function test_addMedia_invalid_media_type_defaults_to_image(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(
            (object) ['id' => 1, 'user_id' => 10, 'challenge_id' => 1],
        );

        $media = Mockery::mock();
        $media->id = 42;
        $media->shouldReceive('getAttribute')->with('id')->andReturn(42);

        // IdeaMedia::create should be called
        $this->ideaMediaAlias->shouldReceive('create')->once()->andReturn($media);

        $result = $this->service->addMedia(1, 10, ['url' => 'http://example.com/img.jpg', 'media_type' => 'invalid']);
        $this->assertSame(42, $result);
    }

    public function test_deleteMedia_not_found_returns_false(): void
    {
        $this->ideaMediaAlias->shouldReceive('find')->with(999)->andReturn(null);

        $result = $this->service->deleteMedia(999, 1);
        $this->assertFalse($result);
    }
}
