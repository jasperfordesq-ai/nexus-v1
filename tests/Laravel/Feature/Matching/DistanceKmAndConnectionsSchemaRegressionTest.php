<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Matching;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Laravel\TestCase;

/**
 * Regression tests for two production SQL bugs found 2026-07-02:
 *
 *  1. Stray `distance_km varchar(255)` columns on users/categories/attributes/
 *     listing_attributes collided with the computed `(haversine) AS distance_km`
 *     alias, making `ORDER BY distance_km` ambiguous (SQLSTATE 1052) and breaking
 *     the Smart Matching cache warm-up + every proximity search that JOINs one of
 *     those tables. Fixed by dropping the stray columns
 *     (2026_07_02_000010_drop_stray_distance_km_columns) — the legitimate
 *     match_cache/match_approvals/match_history decimal columns are kept.
 *
 *  2. GroupRecommendationEngine::getConnectionGroups referenced `c.addressee_id`,
 *     but the `connections` table has `receiver_id` (not addressee_id) — "Unknown
 *     column 'c.addressee_id'". Fixed by using `c.receiver_id`.
 */
class DistanceKmAndConnectionsSchemaRegressionTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<string> tables that carried the accidental varchar column */
    private const STRAY_TABLES = ['users', 'categories', 'attributes', 'listing_attributes'];

    public function test_stray_distance_km_columns_are_dropped(): void
    {
        foreach (self::STRAY_TABLES as $table) {
            $this->assertFalse(
                Schema::hasColumn($table, 'distance_km'),
                "{$table}.distance_km should be dropped — a stray varchar column made proximity ORDER BY ambiguous."
            );
        }
    }

    public function test_legitimate_matching_distance_columns_are_retained(): void
    {
        // The matching tables genuinely store a per-match distance and must NOT
        // be touched by the stray-column drop.
        foreach (['match_cache', 'match_approvals', 'match_history'] as $table) {
            if (Schema::hasTable($table)) {
                $this->assertTrue(
                    Schema::hasColumn($table, 'distance_km'),
                    "{$table}.distance_km must be retained (legitimate decimal distance column)."
                );
            }
        }
    }

    public function test_proximity_query_joining_users_is_not_ambiguous(): void
    {
        // Before the drop this threw SQLSTATE[23000] 1052 because `users` carried
        // a physical distance_km alongside the computed alias. Reaching the
        // assertion means MySQL resolved `ORDER BY distance_km` unambiguously.
        $tenantId = (int) (DB::table('users')->value('tenant_id') ?? 1);

        $rows = DB::select(
            'SELECT u.id,
                    (6371 * acos(LEAST(1.0,
                        cos(radians(?)) * cos(radians(COALESCE(u.latitude, 0))) *
                        cos(radians(COALESCE(u.longitude, 0)) - radians(?)) +
                        sin(radians(?)) * sin(radians(COALESCE(u.latitude, 0)))
                    ))) AS distance_km
             FROM users u
             WHERE u.tenant_id = ?
             HAVING distance_km <= ?
             ORDER BY distance_km ASC
             LIMIT 1',
            [53.0, -7.0, 53.0, $tenantId, 50]
        );

        $this->assertIsArray($rows);
    }

    public function test_group_connection_query_uses_receiver_id(): void
    {
        // connections has requester_id + receiver_id (no addressee_id). Running the
        // corrected join proves the column names are valid against the live schema.
        $rows = DB::select(
            "SELECT g.id
             FROM connections c
             JOIN group_members gm ON gm.user_id = CASE
                 WHEN c.requester_id = ? THEN c.receiver_id ELSE c.requester_id END
             JOIN `groups` g ON g.id = gm.group_id
             WHERE (c.requester_id = ? OR c.receiver_id = ?)
               AND c.status = 'accepted' AND c.tenant_id = ?
             LIMIT 1",
            [1, 1, 1, 1]
        );

        $this->assertIsArray($rows);
    }

    public function test_group_recommendation_engine_source_has_no_addressee_id(): void
    {
        // Guards against reintroducing the non-existent column in the service.
        $source = (string) file_get_contents(base_path('app/Services/GroupRecommendationEngine.php'));
        $this->assertStringNotContainsString(
            'addressee_id',
            $source,
            'GroupRecommendationEngine must not reference connections.addressee_id (use receiver_id).'
        );
    }

    public function test_schema_dump_has_no_stray_varchar_distance_km(): void
    {
        // Guards against the accidental column reappearing in the committed dump.
        $dump = (string) file_get_contents(base_path('database/schema/mysql-schema.sql'));
        $this->assertStringNotContainsString(
            '`distance_km` varchar(255)',
            $dump,
            'The stray `distance_km varchar(255)` column must not reappear in the schema dump.'
        );
    }
}
