<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\BadgeCollectionService;
use App\Models\BadgeCollection;
use App\Models\BadgeCollectionItem;
use App\Models\UserBadge;
use App\Models\UserCollectionCompletion;
use Mockery;

class BadgeCollectionServiceTest extends TestCase
{
    public function test_getCollectionsWithProgress_returns_empty_when_no_collections(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_checkCollectionCompletion_returns_empty_when_no_collections(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_create_returns_id(): void
    {
        $mockCollection = Mockery::mock(BadgeCollection::class);
        $mockCollection->id = 5;
        $mockCollection->shouldReceive('save')->once();

        // Use partial mock approach
        $this->markTestIncomplete('Requires integration test — model instantiation inside static method');
    }

    public function test_addBadgeToCollection_creates_item(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }

    public function test_removeBadgeFromCollection_does_nothing_when_collection_not_found(): void
    {
        $this->markTestIncomplete('Requires integration test — Eloquent models cannot use shouldReceive()');
    }
}
