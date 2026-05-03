<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\FeedActivityService;
use Illuminate\Support\Facades\DB;

class FeedActivityServiceTest extends TestCase
{
    private FeedActivityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FeedActivityService();
    }

    public function test_recordActivity_rejects_invalid_source_type(): void
    {
        // Should silently no-op (logs error) rather than throw or write a row.
        DB::shouldReceive('statement')->never();

        $this->service->recordActivity(2, 1, 'not_a_real_type', 5);
        $this->assertTrue(true);
    }

    public function test_recordActivity_writes_for_valid_type(): void
    {
        DB::shouldReceive('statement')->once();

        $this->service->recordActivity(2, 1, 'post', 5, ['title' => 'Hi']);
        $this->assertTrue(true);
    }
}
