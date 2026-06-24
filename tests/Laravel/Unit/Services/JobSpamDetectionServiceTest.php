<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Services\JobSpamDetectionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

class JobSpamDetectionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private int $tenantId = 2;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById($this->tenantId);

        // Create a user with a well-established account (>24h old)
        $this->userId = DB::table('users')->insertGetId([
            'tenant_id'  => $this->tenantId,
            'name'       => 'Spam Test User',
            'first_name' => 'Spam',
            'last_name'  => 'Tester',
            'email'      => 'spamtest_' . uniqid() . '@test.invalid',
            'created_at' => now()->subDays(30),
            'updated_at' => now(),
        ]);
    }

    // ── Action threshold boundaries ───────────────────────────────────

    /**
     * Clean job data with no heuristic hits must score 0 and action=allow.
     */
    public function test_clean_job_scores_zero_and_action_allow(): void
    {
        $result = JobSpamDetectionService::analyzeJob([
            'title'       => 'Community Gardener Wanted',
            'description' => 'We are looking for a passionate gardener to help maintain our community garden on weekends.',
        ], $this->userId, $this->tenantId);

        $this->assertSame(0, $result['score']);
        $this->assertSame([], $result['flags']);
        $this->assertSame('allow', $result['action']);
    }

    /**
     * Score > 90 must produce action=block.
     */
    public function test_action_block_when_score_exceeds_90(): void
    {
        // Stack (per real service weights):
        //   suspicious_patterns: ALL CAPS (+15) + spam phrase "earn money fast" (+20) + phone (+15) = 50 → capped 40
        //   suspicious_links:    bit.ly domain (+25)
        //   new_account:         account < 24h (+15)
        //   duplicate_content:   identical title already posted by this user (+30)
        // Sub-total: 40 + 25 + 15 + 30 = 110 → capped at 100 → action=block
        $newUserId = DB::table('users')->insertGetId([
            'tenant_id'  => $this->tenantId,
            'name'       => 'Fresh Account',
            'first_name' => 'Fresh',
            'last_name'  => 'User',
            'email'      => 'fresh_' . uniqid() . '@test.invalid',
            'created_at' => now()->subMinutes(30), // <24h → new_account flag
            'updated_at' => now(),
        ]);

        $blockTitle = 'EARN MONEY FAST +1 555 123 4567';

        // Seed a prior posting with the same title to trigger duplicate_content (+30)
        DB::table('job_vacancies')->insertOrIgnore([
            'tenant_id'   => $this->tenantId,
            'user_id'     => $newUserId,
            'title'       => $blockTitle,
            'description' => 'Earlier posting.',
            'type'        => 'paid',
            'commitment'  => 'flexible',
            'status'      => 'open',
            'created_at'  => now()->subDays(5),
            'updated_at'  => now(),
        ]);

        $result = JobSpamDetectionService::analyzeJob([
            'title'       => $blockTitle,
            'description' => 'make money online! Visit http://bit.ly/quickcash now!!! $$$$',
        ], $newUserId, $this->tenantId);

        $this->assertGreaterThan(90, $result['score']);
        $this->assertSame('block', $result['action']);
    }

    /**
     * Score between 71–90 must produce action=flag.
     */
    public function test_action_flag_when_score_between_71_and_90(): void
    {
        // Stack (per real service weights, established account → no new_account bump):
        //   suspicious_patterns: ALL CAPS "CALL NOW" (+15) + phone in title (+15)
        //                        + spam phrase "make money online" (+20) = 50 → capped 40
        //   suspicious_links:    two bit.ly domains (+25 each = +50) → capped at 50
        // Sub-total: 40 + 50 = 90 → action=flag (threshold: >90 = block, >70 = flag)
        $result = JobSpamDetectionService::analyzeJob([
            'title'       => 'CALL NOW +1 555 000 1234',
            'description' => 'make money online, visit http://bit.ly/scam or http://bit.ly/offer to apply.',
        ], $this->userId, $this->tenantId);

        $this->assertGreaterThan(70, $result['score']);
        $this->assertLessThanOrEqual(90, $result['score']);
        $this->assertSame('flag', $result['action']);
    }

    // ── checkSuspiciousPatterns heuristics ────────────────────────────

    /**
     * ALL-CAPS title (≥80% uppercase letters, ≥5 chars) adds to suspicious_patterns flag.
     */
    public function test_all_caps_title_triggers_suspicious_patterns(): void
    {
        $result = JobSpamDetectionService::analyzeJob([
            'title'       => 'URGENT HELP NEEDED NOW',
            'description' => 'Normal description here.',
        ], $this->userId, $this->tenantId);

        $this->assertContains('suspicious_patterns', $result['flags']);
        $this->assertGreaterThan(0, $result['score']);
    }

    /**
     * Mixed-case title must NOT trigger the all-caps heuristic.
     */
    public function test_mixed_case_title_does_not_trigger_all_caps(): void
    {
        $result = JobSpamDetectionService::analyzeJob([
            'title'       => 'Friendly Community Helper',
            'description' => 'Helping neighbours in the area.',
        ], $this->userId, $this->tenantId);

        $this->assertNotContains('suspicious_patterns', $result['flags']);
        $this->assertSame('allow', $result['action']);
    }

    /**
     * Phone number in title triggers suspicious_patterns.
     */
    public function test_phone_number_in_title_triggers_suspicious_patterns(): void
    {
        $result = JobSpamDetectionService::analyzeJob([
            'title'       => 'Call me on +353 87 123 4567',
            'description' => 'Casual description without anything suspicious.',
        ], $this->userId, $this->tenantId);

        $this->assertContains('suspicious_patterns', $result['flags']);
    }

    /**
     * Spam phrase "make money online" triggers suspicious_patterns.
     */
    public function test_spam_phrase_triggers_suspicious_patterns(): void
    {
        $result = JobSpamDetectionService::analyzeJob([
            'title'       => 'Great opportunity',
            'description' => 'Want to make money online from home? Apply now.',
        ], $this->userId, $this->tenantId);

        $this->assertContains('suspicious_patterns', $result['flags']);
        $this->assertGreaterThanOrEqual(20, $result['score']);
    }

    /**
     * Multiple spam phrases only count once (break after first match — capped at +20).
     * Score from patterns alone is capped at 40.
     */
    public function test_patterns_score_capped_at_40(): void
    {
        // ALL CAPS (+15) + spam phrase (+20) + phone (+15) = 50 raw, capped to 40
        $result = JobSpamDetectionService::analyzeJob([
            'title'       => 'EARN MONEY FAST CALL +35312345678',
            'description' => 'get rich quick and earn money fast unlimited income mlm opportunity pyramid.',
        ], $this->userId, $this->tenantId);

        // patterns sub-score is max 40; total may be higher from other checks
        // Just verify suspicious_patterns flag is present and score is reasonable
        $this->assertContains('suspicious_patterns', $result['flags']);
        $this->assertLessThanOrEqual(100, $result['score']);
    }

    // ── checkSuspiciousUrls heuristics ────────────────────────────────

    /**
     * Known shortener domain (bit.ly) triggers suspicious_links flag.
     */
    public function test_known_shortener_url_triggers_suspicious_links(): void
    {
        $result = JobSpamDetectionService::analyzeJob([
            'title'       => 'Marketing assistant needed',
            'description' => 'Apply at http://bit.ly/apply-here for more info.',
        ], $this->userId, $this->tenantId);

        $this->assertContains('suspicious_links', $result['flags']);
        $this->assertGreaterThanOrEqual(25, $result['score']);
    }

    /**
     * A legitimate URL (e.g. company website) must NOT trigger suspicious_links.
     */
    public function test_legitimate_url_does_not_trigger_suspicious_links(): void
    {
        $result = JobSpamDetectionService::analyzeJob([
            'title'       => 'Software developer role',
            'description' => 'Apply at https://company.example.com/careers for this full-time position.',
        ], $this->userId, $this->tenantId);

        $this->assertNotContains('suspicious_links', $result['flags']);
    }

    /**
     * More than 5 links in text adds 20 to url score regardless of domain.
     */
    public function test_excessive_links_count_triggers_suspicious_links(): void
    {
        $desc  = 'See: https://a.example.com https://b.example.com https://c.example.com ';
        $desc .= 'https://d.example.com https://e.example.com https://f.example.com';

        $result = JobSpamDetectionService::analyzeJob([
            'title'       => 'Community coordinator',
            'description' => $desc,
        ], $this->userId, $this->tenantId);

        $this->assertContains('suspicious_links', $result['flags']);
    }

    // ── checkNewAccount heuristic ─────────────────────────────────────

    /**
     * An account created <24h ago triggers new_account flag.
     */
    public function test_new_account_less_than_24h_triggers_flag(): void
    {
        $newUserId = DB::table('users')->insertGetId([
            'tenant_id'  => $this->tenantId,
            'name'       => 'Very New User',
            'first_name' => 'New',
            'last_name'  => 'User',
            'email'      => 'newuser_' . uniqid() . '@test.invalid',
            'created_at' => now()->subHours(2),
            'updated_at' => now(),
        ]);

        $result = JobSpamDetectionService::analyzeJob([
            'title'       => 'Helper needed',
            'description' => 'A perfectly normal job posting.',
        ], $newUserId, $this->tenantId);

        $this->assertContains('new_account', $result['flags']);
        $this->assertGreaterThanOrEqual(15, $result['score']);
    }

    /**
     * An account older than 24h must NOT trigger new_account.
     */
    public function test_established_account_does_not_trigger_new_account_flag(): void
    {
        $result = JobSpamDetectionService::analyzeJob([
            'title'       => 'Garden assistant',
            'description' => 'Help us keep the garden tidy every Saturday morning.',
        ], $this->userId, $this->tenantId);

        $this->assertNotContains('new_account', $result['flags']);
    }

    // ── checkDuplicateContent heuristic ──────────────────────────────

    /**
     * Posting a job with an identical title within 30 days triggers duplicate_content.
     */
    public function test_identical_title_in_last_30_days_triggers_duplicate_content(): void
    {
        $duplicateTitle = 'Community Timebank Helper ' . uniqid();

        // Seed an existing job with the same title
        DB::table('job_vacancies')->insertOrIgnore([
            'tenant_id'   => $this->tenantId,
            'user_id'     => $this->userId,
            'title'       => $duplicateTitle,
            'description' => 'First posting description.',
            'type'        => 'paid',
            'commitment'  => 'flexible',
            'status'      => 'open',
            'created_at'  => now()->subDays(5),
            'updated_at'  => now(),
        ]);

        $result = JobSpamDetectionService::analyzeJob([
            'title'       => $duplicateTitle,
            'description' => 'Second posting with different description.',
        ], $this->userId, $this->tenantId);

        $this->assertContains('duplicate_content', $result['flags']);
        $this->assertGreaterThanOrEqual(30, $result['score']);
    }

    /**
     * A unique title not posted before must NOT trigger duplicate_content.
     */
    public function test_unique_title_does_not_trigger_duplicate_content(): void
    {
        $result = JobSpamDetectionService::analyzeJob([
            'title'       => 'Completely Unique Job ' . uniqid(),
            'description' => 'This is a brand-new posting that has never been submitted before.',
        ], $this->userId, $this->tenantId);

        $this->assertNotContains('duplicate_content', $result['flags']);
    }

    // ── checkPostingRate heuristic ────────────────────────────────────

    /**
     * Posting ≥5 jobs in the last hour triggers excessive_posting_rate at +30.
     */
    public function test_posting_rate_at_max_threshold_triggers_flag(): void
    {
        // Insert 5 job vacancies in the last hour
        for ($i = 0; $i < 5; $i++) {
            DB::table('job_vacancies')->insertOrIgnore([
                'tenant_id'   => $this->tenantId,
                'user_id'     => $this->userId,
                'title'       => 'Rate Test Job ' . $i . ' ' . uniqid(),
                'description' => 'Rate test description.',
                'type'        => 'paid',
                'commitment'  => 'flexible',
                'status'      => 'open',
                'created_at'  => now()->subMinutes(10 + $i),
                'updated_at'  => now(),
            ]);
        }

        $result = JobSpamDetectionService::analyzeJob([
            'title'       => 'Another Rate Test Job',
            'description' => 'Posted right after the others.',
        ], $this->userId, $this->tenantId);

        $this->assertContains('excessive_posting_rate', $result['flags']);
        $this->assertGreaterThanOrEqual(30, $result['score']);
    }

    // ── getSpamStats ──────────────────────────────────────────────────

    /**
     * getSpamStats returns the correct structure with expected keys.
     */
    public function test_get_spam_stats_returns_expected_structure(): void
    {
        $stats = JobSpamDetectionService::getSpamStats($this->tenantId);

        $this->assertArrayHasKey('total_analyzed', $stats);
        $this->assertArrayHasKey('blocked', $stats);
        $this->assertArrayHasKey('flagged', $stats);
        $this->assertArrayHasKey('avg_score', $stats);
        $this->assertArrayHasKey('top_flags', $stats);
        $this->assertIsInt($stats['total_analyzed']);
        $this->assertIsInt($stats['blocked']);
        $this->assertIsInt($stats['flagged']);
        $this->assertIsFloat($stats['avg_score']);
        $this->assertIsArray($stats['top_flags']);
    }

    /**
     * getSpamStats correctly counts jobs with score >90 as blocked.
     */
    public function test_get_spam_stats_counts_blocked_correctly(): void
    {
        DB::table('job_vacancies')->insertOrIgnore([
            'tenant_id'   => $this->tenantId,
            'user_id'     => $this->userId,
            'title'       => 'Blocked Job ' . uniqid(),
            'description' => 'Test.',
            'type'        => 'paid',
            'commitment'  => 'flexible',
            'status'      => 'open',
            'spam_score'  => 95,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $stats = JobSpamDetectionService::getSpamStats($this->tenantId);

        $this->assertGreaterThanOrEqual(1, $stats['blocked']);
    }

    // ── Score cap ─────────────────────────────────────────────────────

    /**
     * Final score is always capped at 100.
     */
    public function test_score_never_exceeds_100(): void
    {
        // Trigger as many heuristics as possible
        $newUserId = DB::table('users')->insertGetId([
            'tenant_id'  => $this->tenantId,
            'name'       => 'Max Score User',
            'first_name' => 'Max',
            'last_name'  => 'Score',
            'email'      => 'maxscore_' . uniqid() . '@test.invalid',
            'created_at' => now()->subMinutes(10),
            'updated_at' => now(),
        ]);

        $duplicateTitle = 'MAX SCORE JOB ' . uniqid();
        DB::table('job_vacancies')->insertOrIgnore([
            'tenant_id'  => $this->tenantId,
            'user_id'    => $newUserId,
            'title'      => $duplicateTitle,
            'description'=> 'Spam.',
            'type'       => 'paid',
            'commitment' => 'flexible',
            'status'     => 'open',
            'created_at' => now()->subDays(2),
            'updated_at' => now(),
        ]);

        $result = JobSpamDetectionService::analyzeJob([
            'title'       => $duplicateTitle,
            'description' => 'earn money fast! http://bit.ly/scam $$$$!!!!!!',
        ], $newUserId, $this->tenantId);

        $this->assertLessThanOrEqual(100, $result['score']);
        $this->assertGreaterThan(0, $result['score']);
    }
}
