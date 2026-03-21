<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\Listing;
use App\Services\ListingModerationService;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class ListingModerationServiceTest extends TestCase
{
    private ListingModerationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ListingModerationService();
    }

    public function test_isModerationEnabled_returns_false_by_default(): void
    {
        DB::shouldReceive('table')->with('tenant_settings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('value')->andReturn(null);

        $this->assertFalse($this->service->isModerationEnabled());
    }

    public function test_isModerationEnabled_returns_true_when_set(): void
    {
        DB::shouldReceive('table')->with('tenant_settings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('value')->andReturn('1');

        $this->assertTrue($this->service->isModerationEnabled());
    }

    public function test_isModerationEnabled_handles_error(): void
    {
        DB::shouldReceive('table')->andThrow(new \Exception('Error'));

        $this->assertFalse($this->service->isModerationEnabled());
    }

    public function test_flag_not_found_returns_false(): void
    {
        Listing::shouldReceive('where')->andReturnSelf();
        Listing::shouldReceive('first')->andReturn(null);

        $this->assertFalse($this->service->flag(2, 999, 1, 'spam'));
    }

    public function test_approve_not_found_returns_false(): void
    {
        Listing::shouldReceive('where')->andReturnSelf();
        Listing::shouldReceive('first')->andReturn(null);

        $this->assertFalse($this->service->approve(2, 999, 1));
    }

    public function test_reject_empty_reason_returns_false(): void
    {
        $listing = \Mockery::mock(Listing::class)->makePartial();
        $listing->moderation_status = 'pending_review';

        Listing::shouldReceive('where')->andReturnSelf();
        Listing::shouldReceive('first')->andReturn($listing);

        $this->assertFalse($this->service->reject(2, 1, 5, ''));
    }

    public function test_reject_not_pending_returns_false(): void
    {
        $listing = \Mockery::mock(Listing::class)->makePartial();
        $listing->moderation_status = 'approved';

        Listing::shouldReceive('where')->andReturnSelf();
        Listing::shouldReceive('first')->andReturn($listing);

        $this->assertFalse($this->service->reject(2, 1, 5, 'reason'));
    }

    public function test_getPending_returns_array(): void
    {
        Listing::shouldReceive('where')->andReturnSelf();
        Listing::shouldReceive('orderBy')->andReturnSelf();
        Listing::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getPending(2);
        $this->assertSame([], $result);
    }

    public function test_getStats_returns_counts(): void
    {
        DB::shouldReceive('table')->with('listings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNotNull')->andReturnSelf();
        DB::shouldReceive('selectRaw')->andReturnSelf();
        DB::shouldReceive('first')->andReturn((object) [
            'total' => 100, 'pending' => 5, 'approved' => 90, 'rejected' => 5,
        ]);

        DB::shouldReceive('table')->with('tenant_settings')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('value')->andReturn('1');

        $result = $this->service->getStats();
        $this->assertSame(100, $result['total']);
        $this->assertTrue($result['moderation_enabled']);
    }
}
