<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use App\Models\JobVacancy;
use App\Models\User;
use App\Services\JobSpamDetectionService;
use Mockery;
use Tests\Laravel\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class JobSpamDetectionServiceTest extends TestCase
{
    private $jobVacancyAlias;
    private $userAlias;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jobVacancyAlias = Mockery::mock('alias:' . JobVacancy::class);
        $this->userAlias = Mockery::mock('alias:' . User::class);
    }

    // ── analyzeJob: Duplicate Content ────────────────────────────

    public function test_analyzeJob_flags_duplicate_title(): void
    {
        // Duplicate check: title match returns true
        $duplicateQuery = Mockery::mock();
        $duplicateQuery->shouldReceive('where')->with('tenant_id', 2)->andReturnSelf();
        $duplicateQuery->shouldReceive('where')->with('user_id', 1)->andReturnSelf();
        $duplicateQuery->shouldReceive('where')->with('created_at', '>=', Mockery::any())->andReturnSelf();
        $duplicateQuery->shouldReceive('where')->with('title', 'Existing Job')->andReturnSelf();
        $duplicateQuery->shouldReceive('exists')->andReturn(true);

        // Rate limit check: no recent posts
        $rateQuery = Mockery::mock();
        $rateQuery->shouldReceive('where')->with('tenant_id', 2)->andReturnSelf();
        $rateQuery->shouldReceive('where')->with('user_id', 1)->andReturnSelf();
        $rateQuery->shouldReceive('where')->with('created_at', '>=', Mockery::any())->andReturnSelf();
        $rateQuery->shouldReceive('count')->andReturn(0);

        // New account check
        $userMock = (object) ['created_at' => now()->subDays(30)];

        $userQuery = Mockery::mock();
        $userQuery->shouldReceive('first')->andReturn($userMock);

        $this->jobVacancyAlias->shouldReceive('where')->with('tenant_id', 2)->andReturn($duplicateQuery, $rateQuery);
        $this->userAlias->shouldReceive('where')->with('id', 1)->andReturn($userQuery);

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'Existing Job', 'description' => 'Unique description here'],
            1,
            2
        );

        $this->assertContains('duplicate_content', $result['flags']);
        $this->assertGreaterThanOrEqual(30, $result['score']);
    }

    public function test_analyzeJob_flags_duplicate_description(): void
    {
        // Duplicate check: title not matched but description matched
        $duplicateQuery = Mockery::mock();
        $duplicateQuery->shouldReceive('where')->andReturnSelf();
        $duplicateQuery->shouldReceive('exists')->andReturn(false, true);   // title not duplicate, description duplicate
        $duplicateQuery->shouldReceive('count')->andReturn(0);

        // Rate limit check
        $rateQuery = Mockery::mock();
        $rateQuery->shouldReceive('where')->andReturnSelf();
        $rateQuery->shouldReceive('count')->andReturn(0);

        // New account check
        $userMock = (object) ['created_at' => now()->subDays(30)];
        $userQuery = Mockery::mock();
        $userQuery->shouldReceive('first')->andReturn($userMock);

        $this->jobVacancyAlias->shouldReceive('where')->with('tenant_id', 2)->andReturn($duplicateQuery, $rateQuery);
        $this->userAlias->shouldReceive('where')->with('id', 1)->andReturn($userQuery);

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'Unique Title', 'description' => 'Duplicate description text'],
            1,
            2
        );

        $this->assertContains('duplicate_content', $result['flags']);
    }

    public function test_analyzeJob_no_duplicate_flag_for_fresh_content(): void
    {
        // No duplicates found
        $duplicateQuery = Mockery::mock();
        $duplicateQuery->shouldReceive('where')->andReturnSelf();
        $duplicateQuery->shouldReceive('exists')->andReturn(false);
        $duplicateQuery->shouldReceive('count')->andReturn(0);

        $rateQuery = Mockery::mock();
        $rateQuery->shouldReceive('where')->andReturnSelf();
        $rateQuery->shouldReceive('count')->andReturn(0);

        $userMock = (object) ['created_at' => now()->subDays(30)];
        $userQuery = Mockery::mock();
        $userQuery->shouldReceive('first')->andReturn($userMock);

        $this->jobVacancyAlias->shouldReceive('where')->with('tenant_id', 2)->andReturn($duplicateQuery, $rateQuery);
        $this->userAlias->shouldReceive('where')->with('id', 1)->andReturn($userQuery);

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'Unique Title', 'description' => 'Unique description'],
            1,
            2
        );

        $this->assertNotContains('duplicate_content', $result['flags']);
    }

    // ── analyzeJob: Suspicious URLs ─────────────────────────────

    public function test_analyzeJob_flags_suspicious_domains(): void
    {
        $this->setupCleanSpamMocks();

        $result = JobSpamDetectionService::analyzeJob(
            [
                'title' => 'Great Job',
                'description' => 'Apply at http://bit.ly/fakejob for details',
            ],
            1,
            2
        );

        $this->assertContains('suspicious_links', $result['flags']);
        $this->assertGreaterThanOrEqual(25, $result['score']);
    }

    public function test_analyzeJob_flags_excessive_links(): void
    {
        $this->setupCleanSpamMocks();

        $links = implode(' ', array_map(
            fn ($i) => "https://example{$i}.com/job",
            range(1, 6)
        ));

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'Job Post', 'description' => "Apply here: {$links}"],
            1,
            2
        );

        $this->assertContains('suspicious_links', $result['flags']);
    }

    public function test_analyzeJob_no_url_flag_for_clean_text(): void
    {
        $this->setupCleanSpamMocks();

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'Job Post', 'description' => 'A simple job with no links'],
            1,
            2
        );

        $this->assertNotContains('suspicious_links', $result['flags']);
    }

    // ── analyzeJob: Rate Limiting ───────────────────────────────

    public function test_analyzeJob_flags_excessive_posting_rate(): void
    {
        // Duplicate check: clean
        $duplicateQuery = Mockery::mock();
        $duplicateQuery->shouldReceive('where')->andReturnSelf();
        $duplicateQuery->shouldReceive('exists')->andReturn(false);
        $duplicateQuery->shouldReceive('count')->andReturn(0);

        // Rate limit: >= 5 posts in last hour
        $rateQuery = Mockery::mock();
        $rateQuery->shouldReceive('where')->andReturnSelf();
        $rateQuery->shouldReceive('count')->andReturn(5);

        // Old account
        $userMock = (object) ['created_at' => now()->subDays(60)];
        $userQuery = Mockery::mock();
        $userQuery->shouldReceive('first')->andReturn($userMock);

        $this->jobVacancyAlias->shouldReceive('where')->with('tenant_id', 2)->andReturn($duplicateQuery, $rateQuery);
        $this->userAlias->shouldReceive('where')->with('id', 1)->andReturn($userQuery);

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'Normal Job', 'description' => 'Normal description'],
            1,
            2
        );

        $this->assertContains('excessive_posting_rate', $result['flags']);
        $this->assertGreaterThanOrEqual(30, $result['score']);
    }

    public function test_analyzeJob_moderate_rate_adds_lower_score(): void
    {
        $duplicateQuery = Mockery::mock();
        $duplicateQuery->shouldReceive('where')->andReturnSelf();
        $duplicateQuery->shouldReceive('exists')->andReturn(false);
        $duplicateQuery->shouldReceive('count')->andReturn(0);

        // 3 posts in last hour triggers moderate rate
        $rateQuery = Mockery::mock();
        $rateQuery->shouldReceive('where')->andReturnSelf();
        $rateQuery->shouldReceive('count')->andReturn(3);

        $userMock = (object) ['created_at' => now()->subDays(60)];
        $userQuery = Mockery::mock();
        $userQuery->shouldReceive('first')->andReturn($userMock);

        $this->jobVacancyAlias->shouldReceive('where')->with('tenant_id', 2)->andReturn($duplicateQuery, $rateQuery);
        $this->userAlias->shouldReceive('where')->with('id', 1)->andReturn($userQuery);

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'Normal Job', 'description' => 'Normal description'],
            1,
            2
        );

        $this->assertContains('excessive_posting_rate', $result['flags']);
        // Score from moderate rate is 15 (less than max 30)
        $this->assertGreaterThanOrEqual(15, $result['score']);
    }

    public function test_analyzeJob_no_rate_flag_for_low_volume(): void
    {
        $this->setupCleanSpamMocks();

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'Normal Job', 'description' => 'Normal description'],
            1,
            2
        );

        $this->assertNotContains('excessive_posting_rate', $result['flags']);
    }

    // ── analyzeJob: Suspicious Patterns ─────────────────────────

    public function test_analyzeJob_flags_all_caps_title(): void
    {
        $this->setupCleanSpamMocks();

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'AMAZING JOB OPPORTUNITY NOW', 'description' => 'Normal description'],
            1,
            2
        );

        $this->assertContains('suspicious_patterns', $result['flags']);
    }

    public function test_analyzeJob_flags_excessive_special_characters(): void
    {
        $this->setupCleanSpamMocks();

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => '***$$$GREAT!!!JOB$$$***', 'description' => 'Normal description'],
            1,
            2
        );

        $this->assertContains('suspicious_patterns', $result['flags']);
    }

    public function test_analyzeJob_flags_phone_number_in_title(): void
    {
        $this->setupCleanSpamMocks();

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'Call +1 555-123-4567 for job', 'description' => 'Normal description'],
            1,
            2
        );

        $this->assertContains('suspicious_patterns', $result['flags']);
    }

    public function test_analyzeJob_flags_spam_phrases(): void
    {
        $this->setupCleanSpamMocks();

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'Work Opportunity', 'description' => 'Earn money fast from home today'],
            1,
            2
        );

        $this->assertContains('suspicious_patterns', $result['flags']);
    }

    public function test_analyzeJob_no_pattern_flag_for_normal_content(): void
    {
        $this->setupCleanSpamMocks();

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'Software Developer', 'description' => 'We are looking for a developer to join our team.'],
            1,
            2
        );

        $this->assertNotContains('suspicious_patterns', $result['flags']);
    }

    // ── analyzeJob: New Account ─────────────────────────────────

    public function test_analyzeJob_flags_new_account(): void
    {
        $duplicateQuery = Mockery::mock();
        $duplicateQuery->shouldReceive('where')->andReturnSelf();
        $duplicateQuery->shouldReceive('exists')->andReturn(false);
        $duplicateQuery->shouldReceive('count')->andReturn(0);

        $rateQuery = Mockery::mock();
        $rateQuery->shouldReceive('where')->andReturnSelf();
        $rateQuery->shouldReceive('count')->andReturn(0);

        // Account created 2 hours ago (< 24 hours)
        $userMock = (object) ['created_at' => now()->subHours(2)];
        $userQuery = Mockery::mock();
        $userQuery->shouldReceive('first')->andReturn($userMock);

        $this->jobVacancyAlias->shouldReceive('where')->with('tenant_id', 2)->andReturn($duplicateQuery, $rateQuery);
        $this->userAlias->shouldReceive('where')->with('id', 1)->andReturn($userQuery);

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'Normal Job', 'description' => 'Normal description'],
            1,
            2
        );

        $this->assertContains('new_account', $result['flags']);
        $this->assertGreaterThanOrEqual(15, $result['score']);
    }

    public function test_analyzeJob_no_new_account_flag_for_old_account(): void
    {
        $this->setupCleanSpamMocks();

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'Normal Job', 'description' => 'Normal description'],
            1,
            2
        );

        $this->assertNotContains('new_account', $result['flags']);
    }

    public function test_analyzeJob_no_new_account_flag_when_user_not_found(): void
    {
        $duplicateQuery = Mockery::mock();
        $duplicateQuery->shouldReceive('where')->andReturnSelf();
        $duplicateQuery->shouldReceive('exists')->andReturn(false);
        $duplicateQuery->shouldReceive('count')->andReturn(0);

        $rateQuery = Mockery::mock();
        $rateQuery->shouldReceive('where')->andReturnSelf();
        $rateQuery->shouldReceive('count')->andReturn(0);

        // User not found
        $userQuery = Mockery::mock();
        $userQuery->shouldReceive('first')->andReturn(null);

        $this->jobVacancyAlias->shouldReceive('where')->with('tenant_id', 2)->andReturn($duplicateQuery, $rateQuery);
        $this->userAlias->shouldReceive('where')->with('id', 1)->andReturn($userQuery);

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'Normal Job', 'description' => 'Normal description'],
            1,
            2
        );

        $this->assertNotContains('new_account', $result['flags']);
    }

    // ── analyzeJob: Action Thresholds ───────────────────────────

    public function test_analyzeJob_returns_allow_for_low_score(): void
    {
        $this->setupCleanSpamMocks();

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'Normal Job', 'description' => 'A great opportunity for volunteers'],
            1,
            2
        );

        $this->assertSame('allow', $result['action']);
        $this->assertLessThanOrEqual(70, $result['score']);
    }

    public function test_analyzeJob_returns_block_for_very_high_score(): void
    {
        // Trigger multiple flags to get score > 90:
        // duplicate (30) + suspicious URL (25) + rate limit (30) + spam phrases (20) = 105 -> capped at 100
        $duplicateQuery = Mockery::mock();
        $duplicateQuery->shouldReceive('where')->andReturnSelf();
        $duplicateQuery->shouldReceive('exists')->andReturn(true); // duplicate title: +30

        // Rate limit: excessive
        $rateQuery = Mockery::mock();
        $rateQuery->shouldReceive('where')->andReturnSelf();
        $rateQuery->shouldReceive('count')->andReturn(5); // +30

        // New account
        $userMock = (object) ['created_at' => now()->subHours(1)];
        $userQuery = Mockery::mock();
        $userQuery->shouldReceive('first')->andReturn($userMock);

        $this->jobVacancyAlias->shouldReceive('where')->with('tenant_id', 2)->andReturn($duplicateQuery, $rateQuery);
        $this->userAlias->shouldReceive('where')->with('id', 1)->andReturn($userQuery);

        $result = JobSpamDetectionService::analyzeJob(
            [
                'title' => 'EXISTING JOB',
                'description' => 'Earn money fast at http://bit.ly/scam now!!!!!!',
            ],
            1,
            2
        );

        $this->assertSame('block', $result['action']);
        $this->assertGreaterThan(90, $result['score']);
    }

    public function test_analyzeJob_score_capped_at_100(): void
    {
        // Trigger as many flags as possible
        $duplicateQuery = Mockery::mock();
        $duplicateQuery->shouldReceive('where')->andReturnSelf();
        $duplicateQuery->shouldReceive('exists')->andReturn(true);

        $rateQuery = Mockery::mock();
        $rateQuery->shouldReceive('where')->andReturnSelf();
        $rateQuery->shouldReceive('count')->andReturn(10);

        $userMock = (object) ['created_at' => now()->subMinutes(5)];
        $userQuery = Mockery::mock();
        $userQuery->shouldReceive('first')->andReturn($userMock);

        $this->jobVacancyAlias->shouldReceive('where')->with('tenant_id', 2)->andReturn($duplicateQuery, $rateQuery);
        $this->userAlias->shouldReceive('where')->with('id', 1)->andReturn($userQuery);

        $result = JobSpamDetectionService::analyzeJob(
            [
                'title' => 'EXISTING JOB!!!$$$',
                'description' => 'Earn money fast http://bit.ly/x http://getrichquick.com/y http://freemoney.com/z http://a.com http://b.com http://c.com',
            ],
            1,
            2
        );

        $this->assertLessThanOrEqual(100, $result['score']);
    }

    public function test_analyzeJob_returns_correct_structure(): void
    {
        $this->setupCleanSpamMocks();

        $result = JobSpamDetectionService::analyzeJob(
            ['title' => 'Test Job', 'description' => 'Simple job description'],
            1,
            2
        );

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('flags', $result);
        $this->assertArrayHasKey('action', $result);
        $this->assertIsInt($result['score']);
        $this->assertIsArray($result['flags']);
        $this->assertContains($result['action'], ['allow', 'flag', 'block']);
    }

    // ── getSpamStats ────────────────────────────────────────────

    public function test_getSpamStats_returns_all_expected_keys(): void
    {
        $totalQuery = Mockery::mock();
        $totalQuery->shouldReceive('whereNotNull')->with('spam_score')->andReturnSelf();
        $totalQuery->shouldReceive('count')->andReturn(100);

        $blockedQuery = Mockery::mock();
        $blockedQuery->shouldReceive('where')->with('spam_score', '>', 90)->andReturnSelf();
        $blockedQuery->shouldReceive('count')->andReturn(5);

        $flaggedQuery = Mockery::mock();
        $flaggedQuery->shouldReceive('where')->with('spam_score', '>', 70)->andReturnSelf();
        $flaggedQuery->shouldReceive('where')->with('spam_score', '<=', 90)->andReturnSelf();
        $flaggedQuery->shouldReceive('count')->andReturn(10);

        $avgQuery = Mockery::mock();
        $avgQuery->shouldReceive('whereNotNull')->with('spam_score')->andReturnSelf();
        $avgQuery->shouldReceive('avg')->with('spam_score')->andReturn(35.7);

        $flagsQuery = Mockery::mock();
        $flagsQuery->shouldReceive('whereNotNull')->with('spam_flags')->andReturnSelf();
        $flagsQuery->shouldReceive('pluck')->with('spam_flags')->andReturn(collect([
            '["suspicious_links","new_account"]',
            '["suspicious_links"]',
            '["duplicate_content","suspicious_links"]',
        ]));

        $this->jobVacancyAlias->shouldReceive('where')
            ->with('tenant_id', 2)
            ->andReturn($totalQuery, $blockedQuery, $flaggedQuery, $avgQuery, $flagsQuery);

        $result = JobSpamDetectionService::getSpamStats(2);

        $this->assertArrayHasKey('total_analyzed', $result);
        $this->assertArrayHasKey('blocked', $result);
        $this->assertArrayHasKey('flagged', $result);
        $this->assertArrayHasKey('avg_score', $result);
        $this->assertArrayHasKey('top_flags', $result);
        $this->assertSame(100, $result['total_analyzed']);
        $this->assertSame(5, $result['blocked']);
        $this->assertSame(10, $result['flagged']);
        $this->assertSame(35.7, $result['avg_score']);
    }

    public function test_getSpamStats_aggregates_flag_counts_correctly(): void
    {
        $totalQuery = Mockery::mock();
        $totalQuery->shouldReceive('whereNotNull')->andReturnSelf();
        $totalQuery->shouldReceive('count')->andReturn(3);

        $blockedQuery = Mockery::mock();
        $blockedQuery->shouldReceive('where')->andReturnSelf();
        $blockedQuery->shouldReceive('count')->andReturn(0);

        $flaggedQuery = Mockery::mock();
        $flaggedQuery->shouldReceive('where')->andReturnSelf();
        $flaggedQuery->shouldReceive('count')->andReturn(0);

        $avgQuery = Mockery::mock();
        $avgQuery->shouldReceive('whereNotNull')->andReturnSelf();
        $avgQuery->shouldReceive('avg')->andReturn(25.0);

        $flagsQuery = Mockery::mock();
        $flagsQuery->shouldReceive('whereNotNull')->andReturnSelf();
        $flagsQuery->shouldReceive('pluck')->andReturn(collect([
            '["suspicious_links","new_account"]',
            '["suspicious_links"]',
            '["duplicate_content","suspicious_links"]',
        ]));

        $this->jobVacancyAlias->shouldReceive('where')
            ->with('tenant_id', 2)
            ->andReturn($totalQuery, $blockedQuery, $flaggedQuery, $avgQuery, $flagsQuery);

        $result = JobSpamDetectionService::getSpamStats(2);

        // suspicious_links appears 3 times, new_account 1, duplicate_content 1
        $this->assertSame(3, $result['top_flags']['suspicious_links']);
        $this->assertSame(1, $result['top_flags']['new_account']);
        $this->assertSame(1, $result['top_flags']['duplicate_content']);
    }

    public function test_getSpamStats_returns_zeros_when_no_data(): void
    {
        $queryMock = Mockery::mock();
        $queryMock->shouldReceive('whereNotNull')->andReturnSelf();
        $queryMock->shouldReceive('where')->andReturnSelf();
        $queryMock->shouldReceive('count')->andReturn(0);
        $queryMock->shouldReceive('avg')->andReturn(null);
        $queryMock->shouldReceive('pluck')->andReturn(collect([]));

        $this->jobVacancyAlias->shouldReceive('where')->with('tenant_id', 2)->andReturn($queryMock);

        $result = JobSpamDetectionService::getSpamStats(2);

        $this->assertSame(0, $result['total_analyzed']);
        $this->assertSame(0, $result['blocked']);
        $this->assertSame(0, $result['flagged']);
        $this->assertSame(0.0, $result['avg_score']);
        $this->assertSame([], $result['top_flags']);
    }

    // ── Helpers ──────────────────────────────────────────────────

    /**
     * Set up mocks for a clean job posting (no duplicates, no rate issues, old account).
     * This helper covers the common case where only pattern/URL checks are under test.
     */
    private function setupCleanSpamMocks(): void
    {
        // Duplicate check: no matches
        $duplicateQuery = Mockery::mock();
        $duplicateQuery->shouldReceive('where')->andReturnSelf();
        $duplicateQuery->shouldReceive('exists')->andReturn(false);
        $duplicateQuery->shouldReceive('count')->andReturn(0);

        // Rate limit: no recent posts
        $rateQuery = Mockery::mock();
        $rateQuery->shouldReceive('where')->andReturnSelf();
        $rateQuery->shouldReceive('count')->andReturn(0);

        // Old account (well past 24h threshold)
        $userMock = (object) ['created_at' => now()->subDays(60)];
        $userQuery = Mockery::mock();
        $userQuery->shouldReceive('first')->andReturn($userMock);

        $this->jobVacancyAlias->shouldReceive('where')->with('tenant_id', 2)->andReturn($duplicateQuery, $rateQuery);
        $this->userAlias->shouldReceive('where')->with('id', 1)->andReturn($userQuery);
    }
}
