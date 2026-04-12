<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\BadgeService;
use App\Models\UserBadge;
use Illuminate\Support\Facades\DB;
use Mockery;

class BadgeServiceTest extends TestCase
{
    private BadgeService $service;
    private $mockUserBadge;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockUserBadge = Mockery::mock(UserBadge::class);
        $this->service = new BadgeService($this->mockUserBadge);
    }

    public function test_getAll_returns_badges_for_tenant(): void
    {
        DB::shouldReceive('table')->with('badges')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('orWhereNull')->andReturnSelf();
        DB::shouldReceive('orderBy')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getAll(2);
        $this->assertIsArray($result);
    }

    public function test_award_returns_false_when_insert_ignore_affects_zero_rows(): void
    {
        // INSERT IGNORE returns 0 affected rows when the unique constraint blocks a duplicate.
        DB::shouldReceive('affectingStatement')
            ->once()
            ->andReturn(0);

        $result = $this->service->award(1, 'early_adopter', 2);
        $this->assertFalse($result);
    }

    public function test_award_returns_true_when_insert_ignore_inserts_row(): void
    {
        DB::shouldReceive('affectingStatement')
            ->once()
            ->andReturn(1);

        $result = $this->service->award(1, 'early_adopter', 2, 10);
        $this->assertTrue($result);
    }

    public function test_award_is_idempotent_on_duplicate_key_exception(): void
    {
        // If the DB driver throws on duplicate (instead of INSERT IGNORE silently skipping),
        // the service must catch and return false — award() is idempotent by contract.
        DB::shouldReceive('affectingStatement')
            ->once()
            ->andThrow(new \Exception('Duplicate entry'));

        $result = $this->service->award(1, 'early_adopter', 2);
        $this->assertFalse($result);
    }

    public function test_award_passes_tenant_id_to_bound_parameters(): void
    {
        // Regression guard: bound params are [user_id, badge_key, tenant_id].
        DB::shouldReceive('affectingStatement')
            ->once()
            ->withArgs(function ($sql, $params) {
                return is_string($sql)
                    && str_contains($sql, 'user_badges')
                    && $params === [7, 'helpful_neighbour', 999];
            })
            ->andReturn(1);

        $this->assertTrue($this->service->award(7, 'helpful_neighbour', 999));
    }

    public function test_revoke_returns_true_on_success(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('delete')->andReturn(1);

        $this->mockUserBadge->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->revoke(1, 'early_adopter', 2);
        $this->assertTrue($result);
    }

    public function test_revoke_returns_false_when_not_found(): void
    {
        $mockQuery = Mockery::mock();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('delete')->andReturn(0);

        $this->mockUserBadge->shouldReceive('newQuery')->andReturn($mockQuery);

        $result = $this->service->revoke(1, 'nonexistent_badge', 2);
        $this->assertFalse($result);
    }

    public function test_getUserBadges_returns_array(): void
    {
        DB::shouldReceive('table')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getUserBadges(1, 2);
        $this->assertIsArray($result);
    }
}
