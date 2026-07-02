<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services\Matching;

use Tests\Laravel\TestCase;
use App\Services\Matching\CandidateRetriever;
use Illuminate\Support\Facades\DB;

/**
 * Gate-matrix tests for the Stage-0 candidate SQL. These assert the SHAPE of
 * the generated SQL (which gates are present for which searcher/config state);
 * end-to-end row behaviour is covered by the feature/integration suites.
 */
class CandidateRetrieverTest extends TestCase
{
    private CandidateRetriever $retriever;

    private const GATES_DEFAULT = [
        'geo_hard_gate' => true,
        'missing_coords_mode' => 'remote_only',
        'dormancy_days' => 90,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->retriever = new CandidateRetriever();
        // user_blocks / match_dismissals existence probes succeed.
        DB::shouldReceive('selectOne')->andReturn((object) ['1' => 1])->byDefault();
    }

    /** Run $invoke, capturing the SQL + params of the single DB::select call. */
    private function captureSql(callable $invoke): array
    {
        $captured = ['sql' => '', 'params' => []];
        DB::shouldReceive('select')->once()->withArgs(function ($sql, $params) use (&$captured) {
            $captured['sql'] = preg_replace('/\s+/', ' ', $sql);
            $captured['params'] = $params;
            return true;
        })->andReturn([]);
        $invoke();
        return $captured;
    }

    public function test_geo_gate_with_coords_exempts_remote_and_hard_gates_physical(): void
    {
        $c = $this->captureSql(fn () => $this->retriever->retrieveBatch(
            7, 1, 'request', [3], null, 53.35, -6.26, 50.0, self::GATES_DEFAULT
        ));

        // Remote/hybrid exempt; physical must resolve coords inside the bounding box…
        $this->assertStringContainsString("AND (l.service_type IN ('remote_only','hybrid') OR (COALESCE(l.latitude, u.latitude) IS NOT NULL", $c['sql']);
        $this->assertStringContainsString('BETWEEN ? AND ?', $c['sql']);
        // …and the true Haversine ceiling is enforced in HAVING.
        $this->assertStringContainsString("HAVING (service_type IN ('remote_only','hybrid') OR (distance_km IS NOT NULL AND distance_km <= ?))", $c['sql']);
        // NULL-safe distance: unresolvable coords give NULL, not a Null-Island distance.
        $this->assertStringContainsString('CASE WHEN COALESCE(l.latitude, u.latitude) IS NULL', $c['sql']);
        // Distance ceiling is the final bound param.
        $this->assertEqualsWithDelta(50.0, (float) end($c['params']), 0.001);
    }

    public function test_searcher_without_coords_gets_remote_only_candidates_by_default(): void
    {
        $c = $this->captureSql(fn () => $this->retriever->retrieveBatch(
            7, 1, 'request', [3], null, null, null, 50.0, self::GATES_DEFAULT
        ));

        // Regression: this exact state used to fall back to newest-listings
        // tenant-wide, producing the cross-country "nearby" matches.
        // (NB: 'HAVING (' is the distance gate; the owner-dismissal subquery
        // legitimately contains 'HAVING COUNT'.)
        $this->assertStringContainsString("AND l.service_type IN ('remote_only','hybrid')", $c['sql']);
        $this->assertStringNotContainsString('HAVING (', $c['sql']);
        $this->assertStringNotContainsString('distance_km', $c['sql']);
        $this->assertStringContainsString('ORDER BY l.created_at DESC', $c['sql']);
    }

    public function test_tenant_wide_mode_restores_legacy_reach_for_no_coords_searchers(): void
    {
        $gates = ['missing_coords_mode' => 'tenant_wide'] + self::GATES_DEFAULT;
        $c = $this->captureSql(fn () => $this->retriever->retrieveBatch(
            7, 1, 'request', [3], null, null, null, 50.0, $gates
        ));

        $this->assertStringNotContainsString('service_type', $c['sql']);
        $this->assertStringNotContainsString('HAVING (', $c['sql']);
    }

    public function test_gate_disabled_with_coords_falls_back_to_plain_distance_ceiling(): void
    {
        $gates = ['geo_hard_gate' => false] + self::GATES_DEFAULT;
        $c = $this->captureSql(fn () => $this->retriever->retrieveBatch(
            7, 1, 'request', [3], null, 53.35, -6.26, 50.0, $gates
        ));

        $this->assertStringContainsString('HAVING (distance_km IS NOT NULL AND distance_km <= ?)', $c['sql']);
        $this->assertStringNotContainsString('service_type', $c['sql']);
    }

    public function test_dormancy_gate_excludes_stale_owners_but_allows_unknown(): void
    {
        $c = $this->captureSql(fn () => $this->retriever->retrieveBatch(
            7, 1, 'request', [3], null, 53.35, -6.26, 50.0, self::GATES_DEFAULT
        ));

        $this->assertStringContainsString('u.last_active_at IS NULL OR u.last_active_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)', $c['sql']);
    }

    public function test_dormancy_gate_can_be_disabled_with_zero_days(): void
    {
        $gates = ['dormancy_days' => 0] + self::GATES_DEFAULT;
        $c = $this->captureSql(fn () => $this->retriever->retrieveBatch(
            7, 1, 'request', [3], null, 53.35, -6.26, 50.0, $gates
        ));

        $this->assertStringNotContainsString('last_active_at', $c['sql']);
    }

    public function test_dismissed_listings_are_excluded_in_sql(): void
    {
        $c = $this->captureSql(fn () => $this->retriever->retrieveBatch(
            7, 1, 'request', [3], null, 53.35, -6.26, 50.0, self::GATES_DEFAULT
        ));

        $this->assertStringContainsString('l.id NOT IN (SELECT listing_id FROM match_dismissals WHERE tenant_id = ? AND user_id = ?)', $c['sql']);
    }

    public function test_cold_start_applies_the_same_gates_without_a_type_constraint(): void
    {
        $c = $this->captureSql(fn () => $this->retriever->retrieveColdStart(
            7, 1, null, null, 50.0, self::GATES_DEFAULT, 20
        ));

        // Same degraded-mode restriction as the main path…
        $this->assertStringContainsString("AND l.service_type IN ('remote_only','hybrid')", $c['sql']);
        // …but no offer/request type filter (cold-start users have no listings yet).
        $this->assertStringNotContainsString('l.type = ?', $c['sql']);
        $this->assertStringContainsString('LIMIT 20', $c['sql']);
    }

    public function test_zero_zero_coordinates_are_treated_as_missing(): void
    {
        // Null Island is the COALESCE sentinel for "no coordinates" — it must
        // trigger degraded mode, not a bounding box around (0, 0).
        $c = $this->captureSql(fn () => $this->retriever->retrieveBatch(
            7, 1, 'request', [3], null, 0.0, 0.0, 50.0, self::GATES_DEFAULT
        ));

        $this->assertStringContainsString("AND l.service_type IN ('remote_only','hybrid')", $c['sql']);
        $this->assertStringNotContainsString('HAVING (', $c['sql']);
    }
}
