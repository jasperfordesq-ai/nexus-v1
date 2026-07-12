<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Enums\GroupStatus;
use App\Models\User;
use App\Services\GroupAuditService;
use App\Services\GroupQAService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

final class GroupQAServiceTest extends TestCase
{
    use DatabaseTransactions;

    private GroupQAService $service;
    private User $owner;
    private User $author;
    private User $answerAuthor;
    private User $member;
    private User $admin;
    private int $groupId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new GroupQAService();
        $this->owner = $this->user();
        $this->author = $this->user();
        $this->answerAuthor = $this->user();
        $this->member = $this->user();
        $this->admin = $this->user();
        $this->groupId = $this->insertGroup($this->owner);

        $this->membership($this->author);
        $this->membership($this->answerAuthor);
        $this->membership($this->member);
        $this->membership($this->admin, 'admin');
        TenantContext::setById($this->testTenantId);
    }

    public function test_exposes_author_admin_edit_and_delete_operations(): void
    {
        foreach ([
            'updateQuestion',
            'deleteQuestion',
            'updateAnswer',
            'deleteAnswer',
        ] as $method) {
            self::assertTrue(method_exists($this->service, $method), $method);
        }
    }

    public function test_question_and_answer_edit_delete_policy_and_integrity(): void
    {
        $questionId = $this->question($this->author);
        $answerId = $this->answer($questionId, $this->answerAuthor);
        self::assertSame($this->testTenantId, TenantContext::getId());
        $this->assertDatabaseHas('groups', [
            'id' => $this->groupId,
            'tenant_id' => $this->testTenantId,
            'status' => GroupStatus::Active->value,
        ]);

        self::assertNull($this->service->updateQuestion(
            $this->groupId,
            $questionId,
            (int) $this->member->id,
            'Member edit denied',
            'This edit must not persist.',
        ));
        self::assertSame('FORBIDDEN', $this->service->getErrors()[0]['code']);

        self::assertNotNull($this->service->updateQuestion(
            $this->groupId,
            $questionId,
            (int) $this->author->id,
            'Author edit accepted',
            'The author may edit this question.',
        ));
        self::assertNotNull($this->service->updateQuestion(
            $this->groupId,
            $questionId,
            (int) $this->admin->id,
            'Administrator edit accepted',
            'A group administrator may edit this question.',
        ));

        self::assertNull($this->service->updateAnswer(
            $this->groupId,
            $answerId,
            (int) $this->member->id,
            'Member edit denied.',
        ));
        self::assertNotNull($this->service->updateAnswer(
            $this->groupId,
            $answerId,
            (int) $this->answerAuthor->id,
            'Answer author edit accepted.',
        ));
        self::assertNotNull($this->service->updateAnswer(
            $this->groupId,
            $answerId,
            (int) $this->admin->id,
            'Administrator answer edit accepted.',
        ));

        self::assertTrue($this->service->acceptAnswer(
            $this->groupId,
            $answerId,
            (int) $this->author->id,
        ));
        self::assertFalse($this->service->deleteAnswer(
            $this->groupId,
            $answerId,
            (int) $this->member->id,
        ));
        self::assertTrue($this->service->deleteAnswer(
            $this->groupId,
            $answerId,
            (int) $this->answerAuthor->id,
        ));
        self::assertSame(0, (int) DB::table('group_questions')->where('id', $questionId)->value('answer_count'));
        self::assertNull(DB::table('group_questions')->where('id', $questionId)->value('accepted_answer_id'));

        $secondAnswer = $this->answer($questionId, $this->answerAuthor);
        DB::table('group_qa_votes')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $this->member->id,
            'votable_type' => 'answer',
            'votable_id' => $secondAnswer,
            'vote' => 1,
            'created_at' => now(),
        ]);
        self::assertTrue($this->service->deleteQuestion(
            $this->groupId,
            $questionId,
            (int) $this->admin->id,
        ));
        self::assertFalse(DB::table('group_questions')->where('id', $questionId)->exists());
        self::assertFalse(DB::table('group_answers')->where('id', $secondAnswer)->exists());
        self::assertFalse(DB::table('group_qa_votes')
            ->where('votable_type', 'answer')
            ->where('votable_id', $secondAnswer)
            ->exists());

        $audits = DB::table('group_audit_log')
            ->where('tenant_id', $this->testTenantId)
            ->where('group_id', $this->groupId)
            ->whereIn('action', [
                GroupAuditService::ACTION_QA_ANSWER_ACCEPTED,
                GroupAuditService::ACTION_QA_ANSWER_DELETED,
                GroupAuditService::ACTION_QA_QUESTION_DELETED,
            ])
            ->get()
            ->keyBy('action');
        self::assertCount(3, $audits);
        self::assertSame((int) $this->author->id, (int) $audits[GroupAuditService::ACTION_QA_ANSWER_ACCEPTED]->user_id);
        self::assertSame((int) $this->answerAuthor->id, (int) $audits[GroupAuditService::ACTION_QA_ANSWER_DELETED]->user_id);
        self::assertSame((int) $this->admin->id, (int) $audits[GroupAuditService::ACTION_QA_QUESTION_DELETED]->user_id);
    }

    public function test_composite_cursors_never_skip_equal_sort_values(): void
    {
        $createdAt = now()->subHour()->format('Y-m-d H:i:s');
        $questionIds = [];
        foreach ([5, 5, 4, 4, 3] as $votes) {
            $questionIds[] = $this->question($this->author, $votes, $createdAt);
        }

        $newest = $this->collectPages('newest', 2);
        $mostVoted = $this->collectPages('most_voted', 2);

        self::assertCount(5, $newest);
        self::assertCount(5, array_unique($newest));
        self::assertEqualsCanonicalizing($questionIds, $newest);
        self::assertCount(5, $mostVoted);
        self::assertCount(5, array_unique($mostVoted));
        self::assertEqualsCanonicalizing($questionIds, $mostVoted);
    }

    /** @return list<int> */
    private function collectPages(string $sort, int $limit): array
    {
        $ids = [];
        $cursor = null;

        do {
            $page = $this->service->listQuestions($this->groupId, (int) $this->member->id, [
                'sort' => $sort,
                'limit' => $limit,
                'cursor' => $cursor,
            ]);
            self::assertNotNull($page);
            foreach ($page['items'] as $question) {
                $ids[] = (int) $question['id'];
            }
            $cursor = $page['cursor'];
        } while ($page['has_more']);

        return $ids;
    }

    private function user(): User
    {
        return User::factory()->forTenant($this->testTenantId)->create();
    }

    private function insertGroup(User $owner): int
    {
        return (int) DB::table('groups')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'owner_id' => $owner->id,
            'name' => 'Q&A service ' . uniqid('', true),
            'description' => 'Q&A service fixture.',
            'visibility' => 'private',
            'status' => GroupStatus::Active->value,
            'is_active' => true,
            'cached_member_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function membership(User $user, string $role = 'member'): void
    {
        DB::table('group_members')->insert([
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groupId,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function question(User $author, int $votes = 0, ?string $createdAt = null): int
    {
        return (int) DB::table('group_questions')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'group_id' => $this->groupId,
            'user_id' => $author->id,
            'title' => 'Question ' . uniqid('', true),
            'body' => 'Question body.',
            'accepted_answer_id' => null,
            'is_closed' => false,
            'view_count' => 0,
            'vote_count' => $votes,
            'answer_count' => 0,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $createdAt ?? now(),
        ]);
    }

    private function answer(int $questionId, User $author): int
    {
        $answerId = (int) DB::table('group_answers')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'question_id' => $questionId,
            'user_id' => $author->id,
            'body' => 'Answer body.',
            'is_accepted' => false,
            'vote_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('group_questions')->where('id', $questionId)->increment('answer_count');

        return $answerId;
    }
}
