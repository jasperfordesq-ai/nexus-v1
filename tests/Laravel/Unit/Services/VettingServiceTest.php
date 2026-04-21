<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\VettingService;
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

class VettingServiceTest extends TestCase
{
    private VettingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VettingService();
    }

    public function test_getUserRecords_returns_empty_on_error(): void
    {
        DB::shouldReceive('table')->andThrow(new \Exception('DB error'));

        $result = $this->service->getUserRecords(1);
        $this->assertEmpty($result);
    }

    public function test_getById_returns_null_when_not_found(): void
    {
        DB::shouldReceive('table')->with('vetting_records')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('first')->andReturn(null);

        $this->assertNull($this->service->getById(999));
    }

    public function test_getAll_returns_expected_structure(): void
    {
        $query = DB::shouldReceive('table')->with('vetting_records')->andReturnSelf();
        DB::shouldReceive('join')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(0);
        DB::shouldReceive('orderByDesc')->andReturnSelf();
        DB::shouldReceive('limit')->andReturnSelf();
        DB::shouldReceive('offset')->andReturnSelf();
        DB::shouldReceive('get')->andReturn(collect([]));

        $result = $this->service->getAll();

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('pagination', $result);
        $this->assertEquals(0, $result['pagination']['total']);
    }

    public function test_getStats_returns_expected_keys(): void
    {
        DB::shouldReceive('table')->with('vetting_records')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('count')->andReturn(0);
        DB::shouldReceive('select')->andReturnSelf();
        DB::shouldReceive('groupBy')->andReturnSelf();
        DB::shouldReceive('pluck')->andReturn(collect([]));
        DB::shouldReceive('whereIn')->andReturnSelf();

        $result = $this->service->getStats();

        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('by_status', $result);
        $this->assertArrayHasKey('by_type', $result);
        $this->assertArrayHasKey('expiring_soon', $result);
        $this->assertArrayHasKey('expired', $result);
        $this->assertArrayHasKey('pending', $result);
        $this->assertArrayHasKey('verified', $result);
        $this->assertArrayHasKey('rejected', $result);
    }

    public function test_getStats_returns_defaults_on_error(): void
    {
        DB::shouldReceive('table')->andThrow(new \Exception('DB error'));

        $result = $this->service->getStats();

        $this->assertEquals(0, $result['total']);
        $this->assertEmpty($result['by_status']);
    }

    // =========================================================================
    // userHasValidVetting
    // =========================================================================

    public function test_userHasValidVetting_returns_false_for_nonpositive_user_id(): void
    {
        // No DB calls expected — early return
        $this->assertFalse($this->service->userHasValidVetting(0, 'garda_vetting'));
        $this->assertFalse($this->service->userHasValidVetting(-5, 'garda_vetting'));
    }

    public function test_userHasValidVetting_returns_false_for_empty_type(): void
    {
        $this->assertFalse($this->service->userHasValidVetting(42, ''));
    }

    public function test_userHasValidVetting_returns_true_when_verified_record_exists(): void
    {
        DB::shouldReceive('table')->with('vetting_records')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('exists')->andReturn(true);

        $this->assertTrue($this->service->userHasValidVetting(42, 'garda_vetting'));
    }

    public function test_userHasValidVetting_returns_false_when_no_record(): void
    {
        DB::shouldReceive('table')->with('vetting_records')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('exists')->andReturn(false);

        $this->assertFalse($this->service->userHasValidVetting(42, 'garda_vetting'));
    }

    public function test_userHasValidVetting_fails_closed_on_db_error(): void
    {
        DB::shouldReceive('table')->andThrow(new \Exception('DB error'));

        $this->assertFalse($this->service->userHasValidVetting(42, 'garda_vetting'));
    }

    // =========================================================================
    // userHasAllValidVettings
    // =========================================================================

    public function test_userHasAllValidVettings_returns_true_for_empty_input(): void
    {
        // No DB calls expected — empty requirement means no block
        $this->assertTrue($this->service->userHasAllValidVettings(42, []));
    }

    public function test_userHasAllValidVettings_returns_true_for_input_of_only_empty_strings(): void
    {
        // array_filter removes empties, leaving no requirements
        $this->assertTrue($this->service->userHasAllValidVettings(42, ['', null, false]));
    }

    public function test_userHasAllValidVettings_returns_false_for_nonpositive_user_id(): void
    {
        $this->assertFalse($this->service->userHasAllValidVettings(0, ['garda_vetting']));
        $this->assertFalse($this->service->userHasAllValidVettings(-1, ['garda_vetting']));
    }

    public function test_userHasAllValidVettings_returns_true_when_all_types_present(): void
    {
        DB::shouldReceive('table')->with('vetting_records')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('distinct')->andReturnSelf();
        // Two unique types, DB reports two distinct vetting_type values present
        DB::shouldReceive('count')->with('vetting_type')->andReturn(2);

        $this->assertTrue($this->service->userHasAllValidVettings(
            42,
            ['garda_vetting', 'dbs_enhanced']
        ));
    }

    public function test_userHasAllValidVettings_returns_false_when_one_type_missing(): void
    {
        DB::shouldReceive('table')->with('vetting_records')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('distinct')->andReturnSelf();
        // Two unique types required, only one present
        DB::shouldReceive('count')->with('vetting_type')->andReturn(1);

        $this->assertFalse($this->service->userHasAllValidVettings(
            42,
            ['garda_vetting', 'dbs_enhanced']
        ));
    }

    public function test_userHasAllValidVettings_dedupes_input(): void
    {
        DB::shouldReceive('table')->with('vetting_records')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('whereNull')->andReturnSelf();
        DB::shouldReceive('distinct')->andReturnSelf();
        // Input has duplicates but unique count is 1 — one matching record passes
        DB::shouldReceive('count')->with('vetting_type')->andReturn(1);

        $this->assertTrue($this->service->userHasAllValidVettings(
            42,
            ['garda_vetting', 'garda_vetting', 'garda_vetting']
        ));
    }

    public function test_userHasAllValidVettings_fails_closed_on_db_error(): void
    {
        DB::shouldReceive('table')->andThrow(new \Exception('DB error'));

        $this->assertFalse($this->service->userHasAllValidVettings(42, ['garda_vetting']));
    }

    // =========================================================================
    // isSafeguardingStaff
    // =========================================================================

    public function test_isSafeguardingStaff_returns_true_for_staff_role(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('exists')->andReturn(true);

        $this->assertTrue($this->service->isSafeguardingStaff(42));
    }

    public function test_isSafeguardingStaff_returns_false_for_member_role(): void
    {
        DB::shouldReceive('table')->with('users')->andReturnSelf();
        DB::shouldReceive('where')->andReturnSelf();
        DB::shouldReceive('whereIn')->andReturnSelf();
        DB::shouldReceive('exists')->andReturn(false);

        $this->assertFalse($this->service->isSafeguardingStaff(42));
    }

    public function test_isSafeguardingStaff_returns_false_for_nonpositive_user_id(): void
    {
        $this->assertFalse($this->service->isSafeguardingStaff(0));
        $this->assertFalse($this->service->isSafeguardingStaff(-1));
    }

    public function test_isSafeguardingStaff_fails_closed_on_db_error(): void
    {
        DB::shouldReceive('table')->andThrow(new \Exception('DB error'));

        $this->assertFalse($this->service->isSafeguardingStaff(42));
    }
}
