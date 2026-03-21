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

    public function test_getActivity_returns_array(): void
    {
        DB::shouldReceive('select')->andReturn([]);

        $result = $this->service->getActivity(2, 1);
        $this->assertIsArray($result);
    }

    public function test_logActivity_returns_true_on_success(): void
    {
        DB::shouldReceive('insert')->once()->andReturn(true);

        $result = $this->service->logActivity(2, 1, 'post', ['title' => 'Hello', 'source_id' => 5]);
        $this->assertTrue($result);
    }

    public function test_logActivity_returns_false_on_error(): void
    {
        DB::shouldReceive('insert')->andThrow(new \Exception('error'));

        $result = $this->service->logActivity(2, 1, 'post');
        $this->assertFalse($result);
    }
}
