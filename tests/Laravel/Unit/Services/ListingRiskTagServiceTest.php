<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Services\ListingRiskTagService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class ListingRiskTagServiceTest extends TestCase
{
    public function test_risk_levels_constant(): void
    {
        $this->assertSame(['low', 'medium', 'high', 'critical'], ListingRiskTagService::RISK_LEVELS);
    }

    public function test_categories_constant(): void
    {
        $this->assertArrayHasKey('safeguarding', ListingRiskTagService::CATEGORIES);
        $this->assertArrayHasKey('fraud', ListingRiskTagService::CATEGORIES);
    }

    public function test_tagListing_creates_new_tag(): void
    {
        // getTagForListing returns null (no existing)
        DB::shouldReceive('table')->with('listing_risk_tags as rt')->andReturnSelf();
        DB::shouldReceive('leftJoin')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        // Insert new tag
        DB::shouldReceive('table')->with('listing_risk_tags')->andReturnSelf();
        DB::shouldReceive('insertGetId')->once()->andReturn(5);

        $result = ListingRiskTagService::tagListing(1, ['risk_level' => 'low'], 10);
        $this->assertSame(5, $result);
    }

    public function test_tagListing_invalid_level_defaults_to_low(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('leftJoin')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);
        DB::shouldReceive('insertGetId')->once()->andReturn(1);

        $result = ListingRiskTagService::tagListing(1, ['risk_level' => 'invalid'], 10);
        $this->assertSame(1, $result);
    }

    public function test_getTagForListing_returns_null_when_not_found(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('leftJoin')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->assertNull(ListingRiskTagService::getTagForListing(999));
    }

    public function test_removeTag_returns_false_when_not_exists(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('leftJoin')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->assertFalse(ListingRiskTagService::removeTag(999));
    }

    public function test_getHighRiskListings_returns_array(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('leftJoin')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('orderByRaw')->andReturnSelf();
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = ListingRiskTagService::getHighRiskListings();
        $this->assertSame([], $result);
    }
}
