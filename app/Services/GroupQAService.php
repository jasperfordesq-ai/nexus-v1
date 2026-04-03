<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

/**
 * GroupQAService — Q&A system within groups.
 *
 * Features: questions, answers, accept best answer, upvote/downvote.
 */
class GroupQAService
{
    private array $errors = [];

    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * List questions for a group.
     */
    public function listQuestions(int $groupId, array $filters = []): array
    {
        $tenantId = TenantContext::getId();
        $limit = min($filters['limit'] ?? 20, 100);
        $sort = $filters['sort'] ?? 'newest'; // newest, votes, unanswered

        $query = DB::table('group_questions as gq')
            ->join('users as u', 'gq.user_id', '=', 'u.id')
            ->where('gq.group_id', $groupId)
            ->where('gq.tenant_id', $tenantId)
            ->select(
                'gq.id', 'gq.title', 'gq.body', 'gq.accepted_answer_id',
                'gq.is_closed', 'gq.view_count', 'gq.vote_count', 'gq.answer_count',
                'gq.created_at', 'gq.updated_at',
                'u.id as author_id', 'u.name as author_name', 'u.avatar_url as author_avatar'
            );

        switch ($sort) {
            case 'votes':
                $query->orderByDesc('gq.vote_count');
                break;
            case 'unanswered':
                $query->where('gq.answer_count', 0)->orderByDesc('gq.created_at');
                break;
            default:
                $query->orderByDesc('gq.created_at');
        }

        if (!empty($filters['search'])) {
            $query->where('gq.title', 'LIKE', '%' . $filters['search'] . '%');
        }

        $cursor = $filters['cursor'] ?? null;
        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded && is_numeric($decoded)) {
                $query->where('gq.id', '<', (int) $decoded);
            }
        }

        $questions = $query->limit($limit + 1)->get()->toArray();
        $hasMore = count($questions) > $limit;
        if ($hasMore) array_pop($questions);

        $nextCursor = null;
        if ($hasMore && !empty($questions)) {
            $last = end($questions);
            $nextCursor = base64_encode((string) $last->id);
        }

        return [
            'items' => array_map(fn ($q) => self::formatQuestion((array) $q), $questions),
            'cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Format a question row into the expected API response shape.
     */
    private static function formatQuestion(array $q): array
    {
        $q['author'] = [
            'id' => (int) ($q['author_id'] ?? 0),
            'name' => $q['author_name'] ?? 'Unknown',
            'avatar' => $q['author_avatar'] ?? null,
        ];
        $q['has_accepted_answer'] = !empty($q['accepted_answer_id']);
        $q['user_vote'] = 0; // Default — enriched per-request if needed
        unset($q['author_id'], $q['author_name'], $q['author_avatar']);
        return $q;
    }

    private static function formatAnswer(array $a): array
    {
        $a['author'] = [
            'id' => (int) ($a['author_id'] ?? 0),
            'name' => $a['author_name'] ?? 'Unknown',
            'avatar' => $a['author_avatar'] ?? null,
        ];
        $a['user_vote'] = 0;
        unset($a['author_id'], $a['author_name'], $a['author_avatar']);
        return $a;
    }

    /**
     * Get a single question with its answers.
     */
    public function getQuestion(int $questionId): ?array
    {
        $tenantId = TenantContext::getId();

        $question = DB::table('group_questions as gq')
            ->join('users as u', 'gq.user_id', '=', 'u.id')
            ->where('gq.id', $questionId)
            ->where('gq.tenant_id', $tenantId)
            ->select('gq.*', 'u.id as author_id', 'u.name as author_name', 'u.avatar_url as author_avatar')
            ->first();

        if (!$question) return null;

        // Increment view count
        DB::table('group_questions')->where('id', $questionId)->increment('view_count');

        $answers = DB::table('group_answers as ga')
            ->join('users as u', 'ga.user_id', '=', 'u.id')
            ->where('ga.question_id', $questionId)
            ->where('ga.tenant_id', $tenantId)
            ->select('ga.*', 'u.id as author_id', 'u.name as author_name', 'u.avatar_url as author_avatar')
            ->orderByDesc('ga.is_accepted')
            ->orderByDesc('ga.vote_count')
            ->orderBy('ga.created_at')
            ->get()
            ->map(fn ($row) => self::formatAnswer((array) $row))
            ->toArray();

        $result = self::formatQuestion((array) $question);
        $result['answers'] = $answers;
        return $result;
    }

    /**
     * Ask a question.
     */
    public function askQuestion(int $groupId, int $userId, string $title, ?string $body = null): ?array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if (!$this->isMember($groupId, $userId, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'Must be a member'];
            return null;
        }

        if (strlen($title) < 10) {
            $this->errors[] = ['code' => 'VALIDATION', 'message' => 'Title must be at least 10 characters'];
            return null;
        }

        $id = DB::table('group_questions')->insertGetId([
            'tenant_id' => $tenantId,
            'group_id' => $groupId,
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id, 'title' => $title];
    }

    /**
     * Post an answer to a question.
     */
    public function postAnswer(int $questionId, int $userId, string $body): ?array
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $question = DB::table('group_questions')
            ->where('id', $questionId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$question) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Question not found'];
            return null;
        }

        if ($question->is_closed) {
            $this->errors[] = ['code' => 'CLOSED', 'message' => 'Question is closed'];
            return null;
        }

        if (!$this->isMember($question->group_id, $userId, $tenantId)) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'Must be a member'];
            return null;
        }

        $id = DB::table('group_answers')->insertGetId([
            'tenant_id' => $tenantId,
            'question_id' => $questionId,
            'user_id' => $userId,
            'body' => $body,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update answer count
        DB::table('group_questions')
            ->where('id', $questionId)
            ->increment('answer_count');

        return ['id' => $id, 'question_id' => $questionId];
    }

    /**
     * Accept an answer (asker or group admin).
     */
    public function acceptAnswer(int $answerId, int $userId): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        $answer = DB::table('group_answers')
            ->where('id', $answerId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$answer) {
            $this->errors[] = ['code' => 'NOT_FOUND', 'message' => 'Answer not found'];
            return false;
        }

        $question = DB::table('group_questions')
            ->where('id', $answer->question_id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$question) return false;

        // Only asker or admin can accept
        $isAsker = (int) $question->user_id === $userId;
        $isAdmin = $this->isAdmin($question->group_id, $userId, $tenantId);

        if (!$isAsker && !$isAdmin) {
            $this->errors[] = ['code' => 'FORBIDDEN', 'message' => 'Only the asker or admin can accept answers'];
            return false;
        }

        DB::transaction(function () use ($answerId, $answer) {
            // Unaccept previous answer
            DB::table('group_answers')
                ->where('question_id', $answer->question_id)
                ->where('is_accepted', true)
                ->update(['is_accepted' => false]);

            // Accept this answer
            DB::table('group_answers')
                ->where('id', $answerId)
                ->update(['is_accepted' => true, 'updated_at' => now()]);

            DB::table('group_questions')
                ->where('id', $answer->question_id)
                ->update(['accepted_answer_id' => $answerId, 'updated_at' => now()]);
        });

        return true;
    }

    /**
     * Vote on a question or answer.
     */
    public function vote(int $userId, string $type, int $targetId, int $vote): bool
    {
        $this->errors = [];
        $tenantId = TenantContext::getId();

        if (!in_array($type, ['question', 'answer'], true)) {
            $this->errors[] = ['code' => 'INVALID', 'message' => 'Type must be question or answer'];
            return false;
        }

        if (!in_array($vote, [1, -1], true)) {
            $this->errors[] = ['code' => 'INVALID', 'message' => 'Vote must be 1 or -1'];
            return false;
        }

        // Check existing vote (tenant-scoped)
        $existing = DB::table('group_qa_votes')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('votable_type', $type)
            ->where('votable_id', $targetId)
            ->first();

        $table = $type === 'question' ? 'group_questions' : 'group_answers';

        if ($existing) {
            if ((int) $existing->vote === $vote) {
                // Remove vote (toggle off)
                DB::table('group_qa_votes')->where('id', $existing->id)->delete();
                DB::table($table)->where('id', $targetId)->decrement('vote_count', $vote);
                return true;
            }
            // Change vote direction
            DB::table('group_qa_votes')
                ->where('id', $existing->id)
                ->update(['vote' => $vote]);
            DB::table($table)->where('id', $targetId)->increment('vote_count', $vote * 2);
        } else {
            DB::table('group_qa_votes')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'votable_type' => $type,
                'votable_id' => $targetId,
                'vote' => $vote,
                'created_at' => now(),
            ]);
            DB::table($table)->where('id', $targetId)->increment('vote_count', $vote);
        }

        return true;
    }

    private function isMember(int $groupId, int $userId, int $tenantId): bool
    {
        return DB::table('group_members')
            ->join('groups', 'groups.id', '=', 'group_members.group_id')
            ->where('group_members.group_id', $groupId)
            ->where('group_members.user_id', $userId)
            ->where('group_members.status', 'active')
            ->where('groups.tenant_id', $tenantId)
            ->exists();
    }

    private function isAdmin(int $groupId, int $userId, int $tenantId): bool
    {
        return DB::table('group_members')
            ->join('groups', 'groups.id', '=', 'group_members.group_id')
            ->where('group_members.group_id', $groupId)
            ->where('group_members.user_id', $userId)
            ->where('group_members.status', 'active')
            ->whereIn('group_members.role', ['admin', 'owner'])
            ->where('groups.tenant_id', $tenantId)
            ->exists();
    }
}
