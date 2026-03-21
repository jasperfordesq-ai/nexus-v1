<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FeedService;

class FeedServiceTest extends TestCase
{
    // FeedService uses Eloquent models (FeedActivity, FeedPost, Like) with complex query chains
    public function test_getFeed_requires_integration_test(): void
    {
        $this->markTestIncomplete('Requires integration test with FeedActivity model and HasTenantScope');
    }
}
