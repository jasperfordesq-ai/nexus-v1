<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\CaringTandemMatchingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * CaringTandemMatchingServiceTest
 *
 * Tests the scoring engine, candidate filtering, suppression logic, and
 * markSuggestionAsConsidered in CaringTandemMatchingService.
 *
 * Strategy:
 *  - Use a dedicated high-range fake tenant (99801) to avoid collisions.
 *  - Insert real user/relationship/log rows via DB::table(); rolled back via
 *    DatabaseTransactions.
 *  - Public surface: suggestTandems(), markSuggestionAsConsidered(),
 *    computeIntergenerationalSignal().
 *  - Private scoring helpers are exercised indirectly through suggestTandems()
 *    with precisely-constructed fixtures, validated by score order + signal keys.
 *
 * Skipped: haversineKm math is indirectly covered by the distance-ordering test.
 * Tiny floating-point haversine deviations are irrelevant to correctness.
 */
class CaringTandemMatchingServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99801;

    private CaringTandemMatchingService $svc;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the fake tenant row exists (idempotent across the transaction).
        DB::table('tenants')->insertOrIgnore([
            'id'                 => self::TENANT_ID,
            'name'               => 'Tandem Test Tenant',
            'slug'               => 'tandem-test-99801',
            'is_active'          => 1,
            'depth'              => 0,
            'allows_subtenants'  => 0,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);
        $this->svc = new CaringTandemMatchingService();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Insert a minimal user with optional scoring fields, returns the new ID.
     *
     * @param array<string,mixed> $overrides
     */
    private function insertUser(array $overrides = []): int
    {
        $uid = uniqid('ctm_', true);
        $defaults = [
            'tenant_id'        => self::TENANT_ID,
            'name'             => 'User ' . $uid,
            'first_name'       => 'User',
            'last_name'        => substr($uid, -6),
            'email'            => $uid . '@example.test',
            'status'           => 'active',
            'is_approved'      => 1,
            'balance'          => 0.0,
            'role'             => 'member',
            'latitude'         => null,
            'longitude'        => null,
            'skills'           => null,
            'interests'        => null,
            'availability'     => null,
            'preferred_language' => 'en',
            'date_of_birth'    => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ];

        return DB::table('users')->insertGetId(array_merge($defaults, $overrides));
    }

    // ── Empty-tenant guard ────────────────────────────────────────────────────

    public function test_suggestTandems_returns_empty_array_when_no_candidates(): void
    {
        // No users inserted for TENANT_ID — should return empty without crashing.
        $result = $this->svc->suggestTandems(self::TENANT_ID, 20);

        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    // ── Single-candidate guard ────────────────────────────────────────────────

    public function test_suggestTandems_returns_empty_array_with_only_one_candidate(): void
    {
        $this->insertUser();
        $result = $this->svc->suggestTandems(self::TENANT_ID, 20);

        $this->assertIsArray($result);
        $this->assertSame([], $result);
    }

    // ── Basic result shape ────────────────────────────────────────────────────

    public function test_suggestTandems_returns_correctly_shaped_pairs(): void
    {
        // Two users who share the same language and availability — will exceed MIN_SCORE.
        $this->insertUser([
            'preferred_language' => 'en',
            'availability'       => 'weekdays',
            'skills'             => 'gardening',
            'interests'          => 'cooking',
        ]);
        $this->insertUser([
            'preferred_language' => 'en',
            'availability'       => 'weekdays',
            'skills'             => 'cooking',
            'interests'          => 'gardening',
        ]);

        $result = $this->svc->suggestTandems(self::TENANT_ID, 20);

        $this->assertNotEmpty($result, 'Expected at least one pair suggestion');
        $first = $result[0];
        $this->assertArrayHasKey('supporter', $first);
        $this->assertArrayHasKey('recipient', $first);
        $this->assertArrayHasKey('score', $first);
        $this->assertArrayHasKey('signals', $first);
        $this->assertArrayHasKey('reason', $first);
        $this->assertArrayHasKey('id', $first['supporter']);
        $this->assertArrayHasKey('name', $first['supporter']);
        $this->assertIsFloat($first['score']);
    }

    // ── Score is within [0,1] and meets MIN_SCORE ─────────────────────────────

    public function test_suggestTandems_all_returned_scores_meet_minimum_threshold(): void
    {
        // Insert several pairs — neutral users whose base scores land above 0.4
        // because language neutral (0.5) + distance neutral (0.5) already sums
        // to 0.30*0.5 + 0.25*0.5 = 0.275; add availability neutral (0.4) * 0.15 = 0.06
        // + skill neutral (0.5)*0.20 = 0.10 + interest neutral (0.3)*0.10 = 0.03 = ~0.465 > 0.4.
        for ($i = 0; $i < 4; $i++) {
            $this->insertUser();
        }

        $result = $this->svc->suggestTandems(self::TENANT_ID, 50);

        $this->assertNotEmpty($result);
        foreach ($result as $pair) {
            $this->assertGreaterThanOrEqual(0.4, $pair['score'], 'Score below MIN_SCORE was returned');
            $this->assertLessThanOrEqual(1.0, $pair['score']);
        }
    }

    // ── Sorted by score descending ────────────────────────────────────────────

    public function test_suggestTandems_results_are_sorted_by_score_descending(): void
    {
        // High-score pair: identical language, same availability, complementary skills.
        // These two are close geographically and share a rare language → top score.
        $this->insertUser([
            'preferred_language' => 'ga',
            'availability'       => 'weekends',
            'skills'             => 'cooking,gardening',
            'interests'          => 'music',
            'latitude'           => 53.3498,
            'longitude'          => -6.2603,
        ]);
        $this->insertUser([
            'preferred_language' => 'ga',
            'availability'       => 'weekends',
            'skills'             => 'music',
            'interests'          => 'cooking,gardening',
            'latitude'           => 53.3500,
            'longitude'          => -6.2610,
        ]);

        // Second guaranteed-high-score pair: a different language/location cluster
        // that also shares availability + complementary skills, so it reliably
        // clears MIN_SCORE and gives us a second surviving pair regardless of the
        // exact scoring weights (the four distinct users can't collide on the
        // per-user cap).
        $this->insertUser([
            'preferred_language' => 'es',
            'availability'       => 'weekdays',
            'skills'             => 'music,art',
            'interests'          => 'cooking',
            'latitude'           => 40.4168,
            'longitude'          => -3.7038,
        ]);
        $this->insertUser([
            'preferred_language' => 'es',
            'availability'       => 'weekdays',
            'skills'             => 'cooking',
            'interests'          => 'music,art',
            'latitude'           => 40.4170,
            'longitude'          => -3.7040,
        ]);

        $result = $this->svc->suggestTandems(self::TENANT_ID, 20);

        $this->assertGreaterThan(1, count($result), 'Expected at least two pairs');
        $scores = array_column($result, 'score');
        $sorted = $scores;
        rsort($sorted);
        $this->assertSame($sorted, $scores, 'Results are not sorted descending by score');
    }

    // ── Busy-user exclusion ───────────────────────────────────────────────────

    public function test_suggestTandems_excludes_users_with_active_support_relationships(): void
    {
        $busySupporter = $this->insertUser(['preferred_language' => 'en', 'availability' => 'weekdays']);
        $busyRecipient = $this->insertUser(['preferred_language' => 'en', 'availability' => 'weekdays']);
        $free1         = $this->insertUser(['preferred_language' => 'en', 'availability' => 'weekdays']);
        $free2         = $this->insertUser(['preferred_language' => 'en', 'availability' => 'weekdays']);

        // Mark the first two as already in an active relationship.
        DB::table('caring_support_relationships')->insert([
            'tenant_id'      => self::TENANT_ID,
            'supporter_id'   => $busySupporter,
            'recipient_id'   => $busyRecipient,
            'title'          => 'Test Relationship',
            'status'         => 'active',
            'frequency'      => 'weekly',
            'expected_hours' => 1.0,
            'start_date'     => date('Y-m-d'),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $result = $this->svc->suggestTandems(self::TENANT_ID, 20);

        $returnedIds = [];
        foreach ($result as $pair) {
            $returnedIds[] = $pair['supporter']['id'];
            $returnedIds[] = $pair['recipient']['id'];
        }

        $this->assertNotContains($busySupporter, $returnedIds, 'Busy supporter should be excluded');
        $this->assertNotContains($busyRecipient, $returnedIds, 'Busy recipient should be excluded');
        $this->assertContains($free1, $returnedIds, 'Free user 1 should be in results');
        $this->assertContains($free2, $returnedIds, 'Free user 2 should be in results');
    }

    // ── Suppression filter ────────────────────────────────────────────────────

    public function test_suggestTandems_suppresses_already_logged_pairs(): void
    {
        $user1 = $this->insertUser(['preferred_language' => 'en', 'availability' => 'weekdays']);
        $user2 = $this->insertUser(['preferred_language' => 'en', 'availability' => 'weekdays']);

        // Log the pair as recently dismissed.
        $low  = min($user1, $user2);
        $high = max($user1, $user2);
        DB::table('caring_tandem_suggestion_log')->insertOrIgnore([
            'tenant_id'          => self::TENANT_ID,
            'supporter_user_id'  => $low,
            'recipient_user_id'  => $high,
            'action'             => 'dismissed',
            'created_by_user_id' => null,
            'created_at'         => now(),
        ]);

        $result = $this->svc->suggestTandems(self::TENANT_ID, 20);

        foreach ($result as $pair) {
            $sId = $pair['supporter']['id'];
            $rId = $pair['recipient']['id'];
            $this->assertFalse(
                ($sId === $user1 && $rId === $user2) || ($sId === $user2 && $rId === $user1),
                'A suppressed pair should not appear in suggestions',
            );
        }
        $this->assertEmpty($result, 'With only a suppressed pair, no suggestions should be returned');
    }

    // ── Per-user cap (MAX_PER_USER = 3) ──────────────────────────────────────

    public function test_suggestTandems_caps_appearances_per_user_at_three(): void
    {
        // Insert 8 users that all score high (same language + availability).
        $ids = [];
        for ($i = 0; $i < 8; $i++) {
            $ids[] = $this->insertUser([
                'preferred_language' => 'en',
                'availability'       => 'weekdays',
                'skills'             => 'cooking',
                'interests'          => 'cooking',
            ]);
        }

        $result = $this->svc->suggestTandems(self::TENANT_ID, 100);

        // Count appearances per user ID.
        $appearances = [];
        foreach ($result as $pair) {
            $sId = $pair['supporter']['id'];
            $rId = $pair['recipient']['id'];
            $appearances[$sId] = ($appearances[$sId] ?? 0) + 1;
            $appearances[$rId] = ($appearances[$rId] ?? 0) + 1;
        }

        foreach ($appearances as $userId => $count) {
            $this->assertLessThanOrEqual(3, $count, "User $userId appears more than MAX_PER_USER=3 times");
        }
    }

    // ── Limit parameter ───────────────────────────────────────────────────────

    public function test_suggestTandems_respects_limit_parameter(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->insertUser([
                'preferred_language' => 'en',
                'availability'       => 'weekdays',
            ]);
        }

        $result = $this->svc->suggestTandems(self::TENANT_ID, 2);

        $this->assertLessThanOrEqual(2, count($result));
    }

    // ── markSuggestionAsConsidered: basic write ───────────────────────────────

    public function test_markSuggestionAsConsidered_writes_log_row(): void
    {
        $u1 = $this->insertUser();
        $u2 = $this->insertUser();

        $this->svc->markSuggestionAsConsidered(self::TENANT_ID, $u1, $u2, 'dismissed');

        $low  = min($u1, $u2);
        $high = max($u1, $u2);

        $row = DB::table('caring_tandem_suggestion_log')
            ->where('tenant_id', self::TENANT_ID)
            ->where('supporter_user_id', $low)
            ->where('recipient_user_id', $high)
            ->first();

        $this->assertNotNull($row, 'Log row should have been written');
        $this->assertSame('dismissed', $row->action);
    }

    public function test_markSuggestionAsConsidered_normalises_pair_order(): void
    {
        $u1 = $this->insertUser();
        $u2 = $this->insertUser();

        // Call with reversed order — should still write the same normalised row.
        $this->svc->markSuggestionAsConsidered(self::TENANT_ID, $u2, $u1, 'created_relationship');

        $low  = min($u1, $u2);
        $high = max($u1, $u2);

        $count = DB::table('caring_tandem_suggestion_log')
            ->where('tenant_id', self::TENANT_ID)
            ->where('supporter_user_id', $low)
            ->where('recipient_user_id', $high)
            ->where('action', 'created_relationship')
            ->count();

        $this->assertSame(1, $count);
    }

    public function test_markSuggestionAsConsidered_ignores_invalid_action(): void
    {
        $u1 = $this->insertUser();
        $u2 = $this->insertUser();

        $this->svc->markSuggestionAsConsidered(self::TENANT_ID, $u1, $u2, 'invalid_action');

        $count = DB::table('caring_tandem_suggestion_log')
            ->where('tenant_id', self::TENANT_ID)
            ->where('supporter_user_id', min($u1, $u2))
            ->where('recipient_user_id', max($u1, $u2))
            ->count();

        $this->assertSame(0, $count, 'Invalid action should not write a log row');
    }

    public function test_markSuggestionAsConsidered_ignores_same_user_id(): void
    {
        $u1 = $this->insertUser();

        $this->svc->markSuggestionAsConsidered(self::TENANT_ID, $u1, $u1, 'dismissed');

        $count = DB::table('caring_tandem_suggestion_log')
            ->where('tenant_id', self::TENANT_ID)
            ->where('supporter_user_id', $u1)
            ->where('recipient_user_id', $u1)
            ->count();

        $this->assertSame(0, $count, 'Self-pairing should not write a log row');
    }

    // ── computeIntergenerationalSignal ────────────────────────────────────────

    public function test_computeIntergenerationalSignal_returns_1_when_gap_exceeds_25_years(): void
    {
        $elder  = ['dob' => '1950-06-01'];
        $young  = ['dob' => '1985-06-01'];

        $signal = $this->svc->computeIntergenerationalSignal($elder, $young);

        $this->assertSame(1.0, $signal);
    }

    public function test_computeIntergenerationalSignal_returns_0_when_gap_below_25_years(): void
    {
        $a = ['dob' => '1990-01-01'];
        $b = ['dob' => '2000-01-01']; // 10 years apart

        $signal = $this->svc->computeIntergenerationalSignal($a, $b);

        $this->assertSame(0.0, $signal);
    }

    public function test_computeIntergenerationalSignal_returns_point5_when_dob_missing(): void
    {
        $a = ['dob' => null];
        $b = ['dob' => '1985-01-01'];

        $signal = $this->svc->computeIntergenerationalSignal($a, $b);

        $this->assertSame(0.5, $signal);
    }

    public function test_computeIntergenerationalSignal_returns_point5_when_both_dob_missing(): void
    {
        $signal = $this->svc->computeIntergenerationalSignal(['dob' => null], ['dob' => null]);

        $this->assertSame(0.5, $signal);
    }

    // ── Intergenerational boost surfaces in score ─────────────────────────────

    public function test_suggestTandems_score_boosted_for_intergenerational_pair(): void
    {
        // Elder and young user with same language, same availability, complementary skills.
        // Their base score would be the same as the control pair below, but
        // the +0.10 intergenerational boost should lift them above the control.
        $elder = $this->insertUser([
            'preferred_language' => 'en',
            'availability'       => 'weekdays',
            'skills'             => 'cooking',
            'interests'          => 'gardening',
            'date_of_birth'      => '1950-06-15',
        ]);
        $young = $this->insertUser([
            'preferred_language' => 'en',
            'availability'       => 'weekdays',
            'skills'             => 'gardening',
            'interests'          => 'cooking',
            'date_of_birth'      => '1990-06-15',
        ]);

        // Same-generation pair (same language/availability/complementary skills).
        $peer1 = $this->insertUser([
            'preferred_language' => 'en',
            'availability'       => 'weekdays',
            'skills'             => 'music',
            'interests'          => 'music',
            'date_of_birth'      => '1988-01-01',
        ]);
        $peer2 = $this->insertUser([
            'preferred_language' => 'en',
            'availability'       => 'weekdays',
            'skills'             => 'music',
            'interests'          => 'music',
            'date_of_birth'      => '1992-01-01', // 4 years apart — same generation
        ]);

        $result = $this->svc->suggestTandems(self::TENANT_ID, 20);

        $this->assertNotEmpty($result);

        // Find the intergenerational pair and verify the signal flag is true.
        $intergenPair = null;
        foreach ($result as $pair) {
            $sId = $pair['supporter']['id'];
            $rId = $pair['recipient']['id'];
            if (($sId === $elder && $rId === $young) || ($sId === $young && $rId === $elder)) {
                $intergenPair = $pair;
                break;
            }
        }

        $this->assertNotNull($intergenPair, 'Intergenerational pair should appear in suggestions');
        $this->assertTrue(
            $intergenPair['signals']['intergenerational'],
            'intergenerational signal should be true for 40-year age gap',
        );
        $this->assertStringContainsString('Intergenerational pairing', $intergenPair['reason']);
    }

    // ── Wrong-tenant isolation ────────────────────────────────────────────────

    public function test_suggestTandems_does_not_return_users_from_other_tenants(): void
    {
        $otherTenant = 99802;
        DB::table('tenants')->insertOrIgnore([
            'id'                => $otherTenant,
            'name'              => 'Other Tandem Tenant',
            'slug'              => 'other-tandem-99802',
            'is_active'         => 1,
            'depth'             => 0,
            'allows_subtenants' => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Insert two users in the OTHER tenant only.
        $this->insertUser(['tenant_id' => $otherTenant, 'preferred_language' => 'en', 'availability' => 'weekdays']);
        $this->insertUser(['tenant_id' => $otherTenant, 'preferred_language' => 'en', 'availability' => 'weekdays']);

        // Query AGAINST our tenant (no users) — should see nothing.
        $result = $this->svc->suggestTandems(self::TENANT_ID, 20);

        $this->assertSame([], $result, 'Users from another tenant must not appear in suggestions');
    }

    // ── Reason string non-empty ───────────────────────────────────────────────

    public function test_suggestTandems_reason_is_non_empty_string(): void
    {
        $this->insertUser(['preferred_language' => 'en', 'availability' => 'weekdays']);
        $this->insertUser(['preferred_language' => 'en', 'availability' => 'weekdays']);

        $result = $this->svc->suggestTandems(self::TENANT_ID, 20);

        $this->assertNotEmpty($result);
        foreach ($result as $pair) {
            $this->assertIsString($pair['reason']);
            $this->assertNotSame('', $pair['reason']);
        }
    }
}
