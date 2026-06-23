<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Services;

use Tests\Laravel\TestCase;
use App\Services\MunicipalSurveyService;
use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * MunicipalSurveyServiceTest
 *
 * Strategy: exercises the full survey lifecycle via real DB operations:
 *   isAvailable, create (with + without questions), list/getSurveyById,
 *   getActiveSurveys, update, publish (guards + happy-path), close (guards),
 *   hasResponded, submitResponse (guards + happy-path + response_count bump),
 *   getAnalytics (yes_no breakdown + open_text verbatims + daily chart),
 *   exportCsv (header + data rows).
 *
 * All fixture rows are rolled back by DatabaseTransactions.
 * Skipped: none — all paths are exercisable with the test DB.
 */
class MunicipalSurveyServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;
    private const USER_ID   = 1;   // must exist in tenant 2 (seeded fixture user)

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Insert a minimal survey row and return its ID.
     * status defaults to 'draft'.
     */
    private function insertSurvey(array $overrides = []): int
    {
        return DB::table('municipality_surveys')->insertGetId(array_merge([
            'tenant_id'      => self::TENANT_ID,
            'created_by'     => self::USER_ID,
            'title'          => 'Test Survey ' . uniqid('', true),
            'status'         => 'draft',
            'is_anonymous'   => 0,
            'response_count' => 0,
            'created_at'     => now(),
            'updated_at'     => now(),
        ], $overrides));
    }

    /**
     * Insert a question row for the given survey and return its ID.
     */
    private function insertQuestion(int $surveyId, array $overrides = []): int
    {
        return DB::table('municipality_survey_questions')->insertGetId(array_merge([
            'survey_id'     => $surveyId,
            'tenant_id'     => self::TENANT_ID,
            'question_text' => 'How satisfied are you?',
            'question_type' => 'yes_no',
            'is_required'   => 1,
            'sort_order'    => 0,
            'created_at'    => now(),
            'updated_at'    => now(),
        ], $overrides));
    }

    /**
     * Insert a response row for the given survey and return its ID.
     */
    private function insertResponse(int $surveyId, int $userId, array $answers = []): int
    {
        return DB::table('municipality_survey_responses')->insertGetId([
            'survey_id'    => $surveyId,
            'tenant_id'    => self::TENANT_ID,
            'user_id'      => $userId,
            'answers'      => json_encode($answers),
            'submitted_at' => now(),
        ]);
    }

    // ── isAvailable ───────────────────────────────────────────────────────────

    public function test_isAvailable_returns_true_when_tables_exist(): void
    {
        $this->assertTrue(MunicipalSurveyService::isAvailable());
    }

    // ── createSurvey ─────────────────────────────────────────────────────────

    public function test_createSurvey_throws_when_title_is_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/title/');
        MunicipalSurveyService::createSurvey(self::TENANT_ID, self::USER_ID, ['title' => '']);
    }

    public function test_createSurvey_returns_array_with_correct_fields(): void
    {
        $result = MunicipalSurveyService::createSurvey(self::TENANT_ID, self::USER_ID, [
            'title'       => 'Community Feedback',
            'description' => 'Please rate our service.',
            'is_anonymous'=> 0,
        ]);

        $this->assertIsArray($result);
        $this->assertSame('Community Feedback', $result['title']);
        $this->assertSame('draft', $result['status']);
        $this->assertSame(self::TENANT_ID, (int) $result['tenant_id']);
        $this->assertArrayHasKey('questions', $result);
        $this->assertIsArray($result['questions']);
    }

    public function test_createSurvey_inserts_questions_when_provided(): void
    {
        $result = MunicipalSurveyService::createSurvey(self::TENANT_ID, self::USER_ID, [
            'title'     => 'Survey with Qs',
            'questions' => [
                ['question_text' => 'Rate 1-5', 'question_type' => 'likert', 'is_required' => 1],
                ['question_text' => 'Comments?', 'question_type' => 'open_text', 'is_required' => 0],
            ],
        ]);

        $this->assertCount(2, $result['questions']);
        $this->assertSame('Rate 1-5', $result['questions'][0]['question_text']);
        $this->assertSame('open_text', $result['questions'][1]['question_type']);
    }

    public function test_createSurvey_ignores_questions_missing_required_fields(): void
    {
        // A question without question_type is silently skipped
        $result = MunicipalSurveyService::createSurvey(self::TENANT_ID, self::USER_ID, [
            'title'     => 'Partial Q Survey',
            'questions' => [
                ['question_text' => 'No type here'],   // skipped
                ['question_text' => 'Valid', 'question_type' => 'yes_no'],
            ],
        ]);

        $this->assertCount(1, $result['questions']);
    }

    // ── getSurveyById ─────────────────────────────────────────────────────────

    public function test_getSurveyById_returns_null_for_wrong_tenant(): void
    {
        $surveyId = $this->insertSurvey();
        $result = MunicipalSurveyService::getSurveyById($surveyId, 9999);
        $this->assertNull($result);
    }

    public function test_getSurveyById_returns_survey_with_questions_array(): void
    {
        $surveyId = $this->insertSurvey(['title' => 'Fetch Me']);
        $this->insertQuestion($surveyId);

        $result = MunicipalSurveyService::getSurveyById($surveyId, self::TENANT_ID);

        $this->assertNotNull($result);
        $this->assertSame('Fetch Me', $result['title']);
        $this->assertArrayHasKey('questions', $result);
        $this->assertCount(1, $result['questions']);
    }

    // ── listSurveys ───────────────────────────────────────────────────────────

    public function test_listSurveys_returns_survey_for_correct_tenant(): void
    {
        $surveyId = $this->insertSurvey(['title' => 'Listed Survey']);

        $list = MunicipalSurveyService::listSurveys(self::TENANT_ID);
        $ids  = array_column($list, 'id');

        $this->assertContains($surveyId, $ids);
    }

    public function test_listSurveys_filters_by_status(): void
    {
        $draftId = $this->insertSurvey(['status' => 'draft']);
        // Active survey requires a question — use raw insert to bypass guard
        $activeId = $this->insertSurvey(['status' => 'active']);

        $draftList  = MunicipalSurveyService::listSurveys(self::TENANT_ID, 'draft');
        $activeList = MunicipalSurveyService::listSurveys(self::TENANT_ID, 'active');

        $draftIds  = array_column($draftList, 'id');
        $activeIds = array_column($activeList, 'id');

        $this->assertContains($draftId,  $draftIds);
        $this->assertNotContains($activeId, $draftIds);
        $this->assertContains($activeId, $activeIds);
        $this->assertNotContains($draftId, $activeIds);
    }

    // ── getActiveSurveys ──────────────────────────────────────────────────────

    public function test_getActiveSurveys_excludes_expired_surveys(): void
    {
        // Expired: ends_at in the past
        $expiredId = $this->insertSurvey([
            'status'  => 'active',
            'ends_at' => now()->subHour()->toDateTimeString(),
        ]);
        // Not expired: ends_at in the future
        $validId = $this->insertSurvey([
            'status'  => 'active',
            'ends_at' => now()->addDay()->toDateTimeString(),
        ]);

        $active = MunicipalSurveyService::getActiveSurveys(self::TENANT_ID);
        $ids = array_column($active, 'id');

        $this->assertContains($validId, $ids);
        $this->assertNotContains($expiredId, $ids);
    }

    // ── updateSurvey ─────────────────────────────────────────────────────────

    public function test_updateSurvey_throws_when_survey_not_found(): void
    {
        $this->expectException(RuntimeException::class);
        MunicipalSurveyService::updateSurvey(99999999, self::TENANT_ID, ['title' => 'New']);
    }

    public function test_updateSurvey_updates_title_and_replaces_questions(): void
    {
        $surveyId = $this->insertSurvey(['title' => 'Old Title']);
        $this->insertQuestion($surveyId, ['question_text' => 'Old Q']);

        $updated = MunicipalSurveyService::updateSurvey($surveyId, self::TENANT_ID, [
            'title'     => 'New Title',
            'questions' => [
                ['question_text' => 'New Q', 'question_type' => 'single_choice',
                 'options' => ['A', 'B'], 'is_required' => 1],
            ],
        ]);

        $this->assertSame('New Title', $updated['title']);
        $this->assertCount(1, $updated['questions']);
        $this->assertSame('New Q', $updated['questions'][0]['question_text']);
    }

    // ── publishSurvey ─────────────────────────────────────────────────────────

    public function test_publishSurvey_throws_when_survey_has_no_questions(): void
    {
        $surveyId = $this->insertSurvey(['status' => 'draft']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/question/i');
        MunicipalSurveyService::publishSurvey($surveyId, self::TENANT_ID);
    }

    public function test_publishSurvey_throws_when_already_active(): void
    {
        $surveyId = $this->insertSurvey(['status' => 'active']);
        $this->insertQuestion($surveyId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/draft/i');
        MunicipalSurveyService::publishSurvey($surveyId, self::TENANT_ID);
    }

    public function test_publishSurvey_transitions_draft_to_active(): void
    {
        $surveyId = $this->insertSurvey(['status' => 'draft']);
        $this->insertQuestion($surveyId);

        MunicipalSurveyService::publishSurvey($surveyId, self::TENANT_ID);

        $status = DB::table('municipality_surveys')->where('id', $surveyId)->value('status');
        $this->assertSame('active', $status);
    }

    // ── closeSurvey ───────────────────────────────────────────────────────────

    public function test_closeSurvey_throws_when_survey_is_draft(): void
    {
        $surveyId = $this->insertSurvey(['status' => 'draft']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/active/i');
        MunicipalSurveyService::closeSurvey($surveyId, self::TENANT_ID);
    }

    public function test_closeSurvey_transitions_active_to_closed(): void
    {
        $surveyId = $this->insertSurvey(['status' => 'active']);

        MunicipalSurveyService::closeSurvey($surveyId, self::TENANT_ID);

        $status = DB::table('municipality_surveys')->where('id', $surveyId)->value('status');
        $this->assertSame('closed', $status);
    }

    // ── hasResponded ──────────────────────────────────────────────────────────

    public function test_hasResponded_returns_false_when_no_response_exists(): void
    {
        $surveyId = $this->insertSurvey(['status' => 'active']);

        $this->assertFalse(MunicipalSurveyService::hasResponded($surveyId, self::TENANT_ID, 99999));
    }

    public function test_hasResponded_returns_true_after_response_inserted(): void
    {
        $surveyId = $this->insertSurvey(['status' => 'active']);
        $userId   = 77777;
        $this->insertResponse($surveyId, $userId, ['1' => 'Yes']);

        $this->assertTrue(MunicipalSurveyService::hasResponded($surveyId, self::TENANT_ID, $userId));
    }

    // ── submitResponse ────────────────────────────────────────────────────────

    public function test_submitResponse_throws_when_survey_is_not_active(): void
    {
        $surveyId = $this->insertSurvey(['status' => 'draft']);

        $this->expectException(RuntimeException::class);
        MunicipalSurveyService::submitResponse($surveyId, self::TENANT_ID, 1, []);
    }

    public function test_submitResponse_throws_when_required_question_missing(): void
    {
        $surveyId  = $this->insertSurvey(['status' => 'active']);
        $questionId = $this->insertQuestion($surveyId, ['is_required' => 1]);

        $this->expectException(InvalidArgumentException::class);
        MunicipalSurveyService::submitResponse($surveyId, self::TENANT_ID, 2, []);
    }

    public function test_submitResponse_inserts_response_and_increments_count(): void
    {
        $surveyId   = $this->insertSurvey(['status' => 'active']);
        $questionId = $this->insertQuestion($surveyId, [
            'question_text' => 'Good service?',
            'question_type' => 'yes_no',
            'is_required'   => 1,
        ]);

        // Use a unique user id that hasn't responded yet
        $userId = 55551;
        MunicipalSurveyService::submitResponse(
            $surveyId,
            self::TENANT_ID,
            $userId,
            [(string) $questionId => 'Yes']
        );

        $count = DB::table('municipality_survey_responses')
            ->where('survey_id', $surveyId)
            ->where('user_id', $userId)
            ->count();
        $this->assertSame(1, $count);

        $responseCount = DB::table('municipality_surveys')
            ->where('id', $surveyId)
            ->value('response_count');
        $this->assertSame(1, (int) $responseCount);
    }

    public function test_submitResponse_throws_when_user_already_responded(): void
    {
        $surveyId   = $this->insertSurvey(['status' => 'active']);
        $questionId = $this->insertQuestion($surveyId, ['is_required' => 1]);
        $userId     = 55552;

        MunicipalSurveyService::submitResponse(
            $surveyId,
            self::TENANT_ID,
            $userId,
            [(string) $questionId => 'Yes']
        );

        $this->expectException(RuntimeException::class);
        MunicipalSurveyService::submitResponse(
            $surveyId,
            self::TENANT_ID,
            $userId,
            [(string) $questionId => 'No']
        );
    }

    // ── getAnalytics ──────────────────────────────────────────────────────────

    public function test_getAnalytics_returns_correct_structure(): void
    {
        $surveyId = $this->insertSurvey(['status' => 'active']);

        $analytics = MunicipalSurveyService::getAnalytics($surveyId, self::TENANT_ID);

        $this->assertArrayHasKey('survey_id', $analytics);
        $this->assertArrayHasKey('response_count', $analytics);
        $this->assertArrayHasKey('daily_chart', $analytics);
        $this->assertArrayHasKey('questions', $analytics);
        $this->assertSame($surveyId, $analytics['survey_id']);
        $this->assertSame(0, $analytics['response_count']);
    }

    public function test_getAnalytics_tallies_yes_no_answers(): void
    {
        $surveyId   = $this->insertSurvey(['status' => 'active']);
        $questionId = $this->insertQuestion($surveyId, [
            'question_text' => 'Do you agree?',
            'question_type' => 'yes_no',
            'is_required'   => 0,
        ]);

        // Insert two Yes, one No responses
        foreach ([11, 12] as $uid) {
            DB::table('municipality_survey_responses')->insertOrIgnore([
                'survey_id'    => $surveyId,
                'tenant_id'    => self::TENANT_ID,
                'user_id'      => $uid,
                'answers'      => json_encode([(string) $questionId => 'Yes']),
                'submitted_at' => now(),
            ]);
        }
        DB::table('municipality_survey_responses')->insertOrIgnore([
            'survey_id'    => $surveyId,
            'tenant_id'    => self::TENANT_ID,
            'user_id'      => 13,
            'answers'      => json_encode([(string) $questionId => 'No']),
            'submitted_at' => now(),
        ]);

        $analytics = MunicipalSurveyService::getAnalytics($surveyId, self::TENANT_ID);

        $this->assertSame(3, $analytics['response_count']);
        $qData = $analytics['questions'][0];
        $this->assertSame('yes_no', $qData['question_type']);
        $this->assertArrayHasKey('breakdown', $qData);

        $byOption = [];
        foreach ($qData['breakdown'] as $row) {
            $byOption[$row['option']] = $row;
        }
        $this->assertSame(2, $byOption['Yes']['count']);
        $this->assertSame(1, $byOption['No']['count']);
        $this->assertEqualsWithDelta(66.7, $byOption['Yes']['percentage'], 0.1);
    }

    public function test_getAnalytics_collects_open_text_verbatims(): void
    {
        $surveyId   = $this->insertSurvey(['status' => 'active']);
        $questionId = $this->insertQuestion($surveyId, [
            'question_text' => 'Any comments?',
            'question_type' => 'open_text',
            'is_required'   => 0,
        ]);

        DB::table('municipality_survey_responses')->insertOrIgnore([
            'survey_id'    => $surveyId,
            'tenant_id'    => self::TENANT_ID,
            'user_id'      => 21,
            'answers'      => json_encode([(string) $questionId => 'Great service!']),
            'submitted_at' => now(),
        ]);

        $analytics  = MunicipalSurveyService::getAnalytics($surveyId, self::TENANT_ID);
        $qData      = $analytics['questions'][0];

        $this->assertSame('open_text', $qData['question_type']);
        $this->assertArrayHasKey('verbatims', $qData);
        $this->assertContains('Great service!', $qData['verbatims']);
        $this->assertSame(1, $qData['answer_count']);
    }

    public function test_getAnalytics_throws_when_survey_not_found(): void
    {
        $this->expectException(RuntimeException::class);
        MunicipalSurveyService::getAnalytics(99999999, self::TENANT_ID);
    }

    // ── exportCsv ─────────────────────────────────────────────────────────────

    public function test_exportCsv_returns_header_and_data_rows(): void
    {
        $surveyId   = $this->insertSurvey(['status' => 'active', 'title' => 'CSV Survey']);
        $questionId = $this->insertQuestion($surveyId, [
            'question_text' => 'Rate us',
            'question_type' => 'single_choice',
            'options'       => json_encode(['Good', 'Bad']),
            'is_required'   => 0,
        ]);
        DB::table('municipality_survey_responses')->insertOrIgnore([
            'survey_id'    => $surveyId,
            'tenant_id'    => self::TENANT_ID,
            'user_id'      => 31,
            'answers'      => json_encode([(string) $questionId => 'Good']),
            'submitted_at' => now(),
        ]);

        $csv   = MunicipalSurveyService::exportCsv($surveyId, self::TENANT_ID);
        $lines = explode("\n", trim($csv));

        // At least a header + one data row
        $this->assertGreaterThanOrEqual(2, count($lines));
        $this->assertStringContainsString('response_id', $lines[0]);
        $this->assertStringContainsString('submitted_at', $lines[0]);
        $this->assertStringContainsString('Rate us', $lines[0]);
        $this->assertStringContainsString('Good', $lines[1]);
    }

    public function test_exportCsv_throws_when_survey_not_found(): void
    {
        $this->expectException(RuntimeException::class);
        MunicipalSurveyService::exportCsv(99999999, self::TENANT_ID);
    }
}
