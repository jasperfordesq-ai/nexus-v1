<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\GroupQAService;

/**
 * GroupQAController — Q&A board for groups: questions, answers, voting, and acceptance.
 */
class GroupQAController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly GroupQAService $qaService,
    ) {}

    /**
     * GET /api/v2/groups/{id}/qa
     *
     * List questions for a group with optional sort, cursor pagination, and search.
     */
    public function index(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $filters = [
            'sort' => $this->query('sort', 'newest'),
            'cursor' => $this->query('cursor'),
            'search' => $this->query('q'),
        ];

        $result = $this->qaService->listQuestions($id, $filters);

        return $this->successResponse($result);
    }

    /**
     * POST /api/v2/groups/{id}/qa
     *
     * Ask a new question in a group.
     */
    public function ask(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $title = request()->input('title');
        $body = request()->input('body');

        if (empty($title)) {
            return $this->errorResponse(__('api_controllers_3.group_qa.title_required'), 422);
        }
        if (empty($body)) {
            return $this->errorResponse(__('api_controllers_3.group_qa.body_required'), 422);
        }

        $result = $this->qaService->askQuestion($id, $userId, $title, $body);

        if ($result === null) {
            return $this->errorResponse(__('api_controllers_3.group_qa.failed_create_question'), 400);
        }

        return $this->successResponse($result, 201);
    }

    /**
     * GET /api/v2/groups/{id}/qa/{questionId}
     *
     * Show a single question with its answers.
     */
    public function show(int $id, int $questionId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $result = $this->qaService->getQuestion($questionId);

        if ($result === null) {
            return $this->errorResponse(__('api_controllers_3.group_qa.question_not_found'), 404);
        }

        return $this->successResponse($result);
    }

    /**
     * POST /api/v2/groups/{id}/qa/{questionId}/answers
     *
     * Post an answer to a question.
     */
    public function answer(int $id, int $questionId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $body = request()->input('body');

        if (empty($body)) {
            return $this->errorResponse(__('api_controllers_3.group_qa.body_required'), 422);
        }

        $result = $this->qaService->postAnswer($questionId, $userId, $body);

        if ($result === null) {
            return $this->errorResponse(__('api_controllers_3.group_qa.failed_post_answer'), 400);
        }

        return $this->successResponse($result, 201);
    }

    /**
     * POST /api/v2/groups/{id}/qa/answers/{answerId}/accept
     *
     * Accept an answer as the best answer.
     */
    public function accept(int $id, int $answerId): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $success = $this->qaService->acceptAnswer($answerId, $userId);

        if (!$success) {
            return $this->errorResponse(__('api_controllers_3.group_qa.failed_accept_answer'), 400);
        }

        return $this->successResponse(['message' => __('api_controllers_3.group_qa.answer_accepted')]);
    }

    /**
     * POST /api/v2/groups/{id}/qa/vote
     *
     * Vote on a question or answer.
     * Body: { type: "question"|"answer", target_id: int, vote: "up"|"down" }
     */
    public function vote(int $id): JsonResponse
    {
        $userId = $this->requireUserId();
        if ($userId instanceof JsonResponse) {
            return $userId;
        }

        $type = request()->input('type');
        $targetId = (int) request()->input('target_id');
        $vote = request()->input('vote');

        if (!in_array($type, ['question', 'answer'], true)) {
            return $this->errorResponse(__('api_controllers_3.group_qa.type_must_be_question_or_answer'), 422);
        }
        if ($targetId <= 0) {
            return $this->errorResponse(__('api_controllers_3.group_qa.valid_target_id_required'), 422);
        }
        // Accept both 'up'/'down' strings and 1/-1 integers
        $voteValue = match ($vote) {
            'up', '1', 1 => 1,
            'down', '-1', -1 => -1,
            default => null,
        };
        if ($voteValue === null) {
            return $this->errorResponse(__('api_controllers_3.group_qa.vote_must_be_up_down'), 422);
        }

        $success = $this->qaService->vote($userId, $type, $targetId, $voteValue);

        if (!$success) {
            return $this->errorResponse(__('api_controllers_3.group_qa.failed_record_vote'), 400);
        }

        return $this->successResponse(['message' => __('api_controllers_3.group_qa.vote_recorded')]);
    }
}
