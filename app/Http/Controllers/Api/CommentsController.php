<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\CommentService;

/**
 * CommentsController -- Threaded comments.
 */
class CommentsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly CommentService $commentService,
    ) {}

    /** GET /api/v2/comments */
    public function index(): JsonResponse
    {
        $entityType = $this->query('entity_type', 'post');
        $entityId = $this->queryInt('entity_id');

        if (!$entityId) {
            return $this->respondWithError('VALIDATION_ERROR', 'entity_id is required', 'entity_id');
        }

        $comments = $this->commentService->getForEntity(
            $entityType, $entityId, $this->getTenantId()
        );

        return $this->respondWithData($comments);
    }

    /** POST /api/v2/comments */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('comment_create', 20, 60);

        $data = $this->getAllInput();
        $comment = $this->commentService->create($userId, $this->getTenantId(), $data);

        return $this->respondWithData($comment, null, 201);
    }

    /** PUT /api/v2/comments/{id} */
    public function update(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $body = $this->requireInput('body');

        $comment = $this->commentService->update($id, $userId, $this->getTenantId(), $body);

        if ($comment === null) {
            return $this->respondWithError('NOT_FOUND', 'Comment not found', null, 404);
        }

        return $this->respondWithData($comment);
    }

    /** DELETE /api/v2/comments/{id} */
    public function destroy(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $deleted = $this->commentService->delete($id, $userId, $this->getTenantId());

        if (!$deleted) {
            return $this->respondWithError('NOT_FOUND', 'Comment not found', null, 404);
        }

        return $this->noContent();
    }
}
