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
            return $this->errorResponse('Title is required', 422);
        }
        if (empty($body)) {
            return $this->errorResponse('Body is required', 422);
        }

        $result = $this->qaService->askQuestion($id, $userId, $title, $body);

        if ($result === null) {
            return $this->errorResponse('Failed to create question', 400);
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
            return $this->errorResponse('Question not found', 404);
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
            return $this->errorResponse('Body is required', 422);
        }

        $result = $this->qaService->postAnswer($questionId, $userId, $body);

        if ($result === null) {
            return $this->errorResponse('Failed to post answer', 400);
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
            return $this->errorResponse('Failed to accept answer', 400);
        }

        return $this->successResponse(['message' => 'Answer accepted']);
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
            return $this->errorResponse('Type must be "question" or "answer"', 422);
        }
        if ($targetId <= 0) {
            return $this->errorResponse('A valid target_id is required', 422);
        }
        if (!in_array($vote, ['up', 'down'], true)) {
            return $this->errorResponse('Vote must be "up" or "down"', 422);
        }

        $result = $this->qaService->vote($userId, $type, $targetId, $vote);

        if ($result === null) {
            return $this->errorResponse('Failed to record vote', 400);
        }

        return $this->successResponse($result);
    }
}
