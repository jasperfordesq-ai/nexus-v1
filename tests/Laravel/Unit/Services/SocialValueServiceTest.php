<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\SocialValueService;
use Illuminate\Support\Facades\DB;

class SocialValueServiceTest extends TestCase
{
    private SocialValueService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SocialValueService();
    }

    // ── getConfig ──

    public function test_getConfig_returns_defaults_when_no_record(): void
    {
        DB::shouldReceive('table->where->first')->andReturnNull();

        $config = $this->service->getConfig($this->testTenantId);
        $this->assertEquals('GBP', $config['hour_value_currency']);
        $this->assertEquals(15.00, $config['hour_value_amount']);
        $this->assertEquals(3.50, $config['social_multiplier']);
        $this->assertEquals('annually', $config['reporting_period']);
        // SROI defaults follow the 2023 Timebank Ireland study coefficients
        $this->assertNull($config['investment_amount']);
        $this->assertEquals(10.00, $config['deadweight_pct']);
        $this->assertEquals(10.00, $config['displacement_pct']);
        $this->assertEquals(10.00, $config['attribution_pct']);
        $this->assertEquals(70.00, $config['dropoff_pct']);
        $this->assertEquals(3.50, $config['discount_rate_pct']);
        $this->assertEquals(2, $config['projection_years']);
    }

    public function test_getConfig_returns_custom_values(): void
    {
        $row = (object) [
            'hour_value_currency' => 'EUR',
            'hour_value_amount' => 20.00,
            'social_multiplier' => 4.00,
            'reporting_period' => 'quarterly',
        ];
        DB::shouldReceive('table->where->first')->andReturn($row);

        $config = $this->service->getConfig($this->testTenantId);
        $this->assertEquals('EUR', $config['hour_value_currency']);
        $this->assertEquals(20.00, $config['hour_value_amount']);
    }

    // ── saveConfig ──

    public function test_saveConfig_creates_new_record(): void
    {
        DB::shouldReceive('table->where->exists')->andReturn(false);
        DB::shouldReceive('table->insert')->once();

        $result = $this->service->saveConfig($this->testTenantId, [
            'hour_value_currency' => 'USD',
            'hour_value_amount' => 25.00,
        ]);
        $this->assertTrue($result);
    }

    public function test_saveConfig_updates_existing_record(): void
    {
        DB::shouldReceive('table->where->exists')->andReturn(true);
        DB::shouldReceive('table->where->update')->once();

        $result = $this->service->saveConfig($this->testTenantId, [
            'social_multiplier' => 5.0,
        ]);
        $this->assertTrue($result);
    }

    // ── calculateSROI ──

    public function test_calculateSROI_returns_expected_structure(): void
    {
        DB::shouldReceive('table->where->first')->andReturnNull();
        DB::shouldReceive('table->where->orderBy->orderBy->get')->andReturn(collect([]));
        DB::shouldReceive('selectOne')->andReturn(
            (object) ['total_hours' => 100, 'total_transactions' => 50],
            (object) ['active_members' => 20],
            (object) ['total_events' => 5, 'event_hours' => 30],
            (object) ['total_listings' => 40],
        );
        DB::shouldReceive('select')->andReturn([], []);

        $result = $this->service->calculateSROI($this->testTenantId);
        $this->assertArrayHasKey('config', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('monthly_trend', $result);
        $this->assertArrayHasKey('sroi', $result);
        $this->assertArrayHasKey('outcomes', $result);

        $summary = $result['summary'];
        $this->assertEquals(100.0, $summary['total_hours']);
        $this->assertEquals(1500.0, $summary['direct_value']); // 100 * 15
        $this->assertEquals(5250.0, $summary['social_value']); // 1500 * 3.5

        // No investment / no outcomes configured → no SROI ratio is claimed
        $this->assertNull($result['sroi']['sroi_ratio']);
        $this->assertFalse($result['sroi']['is_configured']);
    }

    // ── computeSroiProjection (methodology acceptance tests) ──

    /**
     * Reproduces the 2023 Timebank Ireland SROI study end-to-end:
     * €50,000 investment → €803,184 Total Present Value → 16.06:1.
     * This is the acceptance test for the calculation methodology —
     * if it fails, the engine no longer matches the published study.
     */
    public function test_computeSroiProjection_reproduces_timebank_ireland_study(): void
    {
        $config = [
            'investment_amount' => 50000.00,
            'deadweight_pct'    => 10.0,
            'displacement_pct'  => 10.0,
            'attribution_pct'   => 10.0,
            'dropoff_pct'       => 70.0,
            'discount_rate_pct' => 3.5,
            'projection_years'  => 2,
        ];
        $outcomes = [
            ['name' => 'Increased Socialisation',      'quantity' => 95, 'proxy_value' => 3432.00],
            ['name' => 'Improved Health & Well-being', 'quantity' => 95, 'proxy_value' => 600.00],
            ['name' => 'Greater Independence',         'quantity' => 90, 'proxy_value' => 640.00],
            ['name' => 'More Included',                'quantity' => 95, 'proxy_value' => 4353.00],
        ];

        $sroi = SocialValueService::computeSroiProjection($config, $outcomes);

        $this->assertEqualsWithDelta(854175.00, $sroi['gross_value'], 0.01);
        // 854,175 × 0.9 × 0.9 × 0.9 = 622,693.58
        $this->assertEqualsWithDelta(622693.58, $sroi['year_one_net'], 0.05);
        // Year 2: 30% retained, discounted one year at 3.5%
        $this->assertEqualsWithDelta(186808.07, $sroi['yearly'][1]['retained'], 0.05);
        $this->assertEqualsWithDelta(180490.89, $sroi['yearly'][1]['present_value'], 0.05);
        // TPV ≈ €803,184 → ratio ≈ 16.06:1 (study figures)
        $this->assertEqualsWithDelta(803184.47, $sroi['total_present_value'], 1.0);
        $this->assertEqualsWithDelta(16.06, $sroi['sroi_ratio'], 0.005);
        $this->assertTrue($sroi['is_configured']);

        // Principle 6 (transparency): every coefficient echoes back
        $this->assertEquals(10.0, $sroi['coefficients']['deadweight_pct']);
        $this->assertEquals(70.0, $sroi['coefficients']['dropoff_pct']);
        $this->assertEquals(3.5, $sroi['coefficients']['discount_rate_pct']);
        $this->assertEquals(2, $sroi['coefficients']['projection_years']);
    }

    public function test_computeSroiProjection_without_investment_claims_no_ratio(): void
    {
        $sroi = SocialValueService::computeSroiProjection(
            ['investment_amount' => null],
            [['name' => 'Outcome', 'quantity' => 10, 'proxy_value' => 100]],
        );

        $this->assertNull($sroi['sroi_ratio']);
        $this->assertFalse($sroi['is_configured']);
        $this->assertGreaterThan(0, $sroi['total_present_value']);
    }

    public function test_computeSroiProjection_year_one_is_not_discounted(): void
    {
        $sroi = SocialValueService::computeSroiProjection(
            [
                'investment_amount' => 1000.0,
                'deadweight_pct' => 0.0, 'displacement_pct' => 0.0, 'attribution_pct' => 0.0,
                'dropoff_pct' => 0.0, 'discount_rate_pct' => 3.5, 'projection_years' => 1,
            ],
            [['name' => 'Outcome', 'quantity' => 1, 'proxy_value' => 1000.0]],
        );

        // Single undiscounted year with zero deductions: TPV == gross
        $this->assertEqualsWithDelta(1000.00, $sroi['total_present_value'], 0.001);
        $this->assertEqualsWithDelta(1.0, $sroi['sroi_ratio'], 0.001);
    }

    public function test_excluded_transaction_types_cover_system_issuance(): void
    {
        // Credit issuance and gifts must never be monetised as service hours
        $this->assertContains('starting_balance', SocialValueService::EXCLUDED_TRANSACTION_TYPES);
        $this->assertContains('admin_grant', SocialValueService::EXCLUDED_TRANSACTION_TYPES);
        $this->assertContains('community_fund', SocialValueService::EXCLUDED_TRANSACTION_TYPES);
        $this->assertContains('donation', SocialValueService::EXCLUDED_TRANSACTION_TYPES);

        $sql = SocialValueService::transactionTypeExclusionSql('t.');
        $this->assertStringContainsString("t.transaction_type NOT IN ('starting_balance'", $sql);
    }
}
