<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\ListingSkillTag;
use App\Services\ListingSkillTagService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class ListingSkillTagServiceTest extends TestCase
{
    private ListingSkillTagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ListingSkillTagService();
    }

    public function test_setTags_listing_not_found_returns_false(): void
    {
        DB::shouldReceive('table')->with('listings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('exists')->andReturn(false);

        $this->assertFalse($this->service->setTags(999, ['gardening']));
    }

    public function test_getTags_returns_array(): void
    {
        ListingSkillTag::shouldReceive('where')->andReturnSelf();
        ListingSkillTag::shouldReceive('orderBy')->andReturnSelf();
        ListingSkillTag::shouldReceive('pluck')->andReturn(collect(['cooking', 'gardening']));

        $result = $this->service->getTags(1);
        $this->assertSame(['cooking', 'gardening'], $result);
    }

    public function test_addTag_empty_tag_returns_false(): void
    {
        $this->assertFalse($this->service->addTag(1, ''));
    }

    public function test_addTag_max_tags_reached_returns_false(): void
    {
        ListingSkillTag::shouldReceive('where')->andReturnSelf();
        ListingSkillTag::shouldReceive('count')->andReturn(10);

        $this->assertFalse($this->service->addTag(1, 'new-tag'));
    }

    public function test_findListingsByTags_empty_tags_returns_empty(): void
    {
        $this->assertSame([], $this->service->findListingsByTags([]));
    }

    public function test_getPopularTags_returns_array(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('selectRaw')->andReturnSelf();
        DB::shouldReceive('groupBy')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([
            (object) ['tag' => 'gardening', 'count' => 5],
        ]));

        $result = $this->service->getPopularTags(20);
        $this->assertCount(1, $result);
        $this->assertSame('gardening', $result[0]['tag']);
    }

    public function test_autocompleteTags_short_prefix_returns_empty(): void
    {
        $this->assertSame([], $this->service->autocompleteTags('a'));
    }

    public function test_autocompleteTags_valid_prefix(): void
    {
        DB::shouldReceive('table')->with('listing_skill_tags')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('distinct')->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect(['gardening', 'garden-work']));

        $result = $this->service->autocompleteTags('gard');
        $this->assertCount(2, $result);
    }
}
