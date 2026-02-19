<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Services\CommentService;
use Nexus\Helpers\UrlHelper;

/**
 * CommentsV2ApiController - V2 API for comments and reactions (React frontend)
 *
 * Used by BlogPostPage, FeedPage, and other content pages for threaded comments.
 *
 * Endpoints:
 * - GET  /api/v2/comments                   - Get comments for a target
 * - POST /api/v2/comments                   - Add a comment (auth required)
 * - PUT  /api/v2/comments/{id}              - Edit a comment (owner only)
 * - DELETE /api/v2/comments/{id}            - Delete a comment (owner only)
 * - POST /api/v2/comments/{id}/reactions    - Toggle reaction on comment
 */
class CommentsV2ApiController extends BaseApiController
{
    protected bool $isV2Api = true;

    /**
     * GET /api/v2/comments?target_type=blog_post&target_id=123
     */
    public function index(): void
    {
        $targetType = $_GET['target_type'] ?? null;
        $targetId = isset($_GET['target_id']) ? (int) $_GET['target_id'] : null;

        if (!$targetType || !$targetId) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'target_type and target_id are required',
                null,
                400
            );
            return;
        }

        $currentUserId = $this->getOptionalUserId() ?? 0;
        $comments = CommentService::fetchComments($targetType, $targetId, $currentUserId);

        $baseUrl = UrlHelper::getBaseUrl();

        // Format comments for V2 response
        $formatted = $this->formatComments($comments, $currentUserId, $baseUrl);

        $this->respondWithData([
            'comments' => $formatted,
            'count' => $this->countAll($formatted),
        ]);
    }

    /**
     * POST /api/v2/comments
     *
     * Body: { target_type, target_id, content, parent_id? }
     */
    public function store(): void
    {
        $userId = $this->getUserId();
        $tenantId = TenantContext::getId();

        $data = $this->getAllInput();
        $targetType = $data['target_type'] ?? null;
        $targetId = isset($data['target_id']) ? (int) $data['target_id'] : null;
        $content = trim($data['content'] ?? '');
        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;

        if (!$targetType || !$targetId) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'target_type and target_id are required',
                null,
                400
            );
            return;
        }

        if (empty($content)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Comment content is required',
                'content',
                400
            );
            return;
        }

        $result = CommentService::addComment(
            $userId,
            $tenantId,
            $targetType,
            $targetId,
            $content,
            $parentId
        );

        if (!$result['success']) {
            $this->respondWithError('COMMENT_ERROR', $result['error'] ?? 'Failed to add comment', null, 400);
            return;
        }

        $baseUrl = UrlHelper::getBaseUrl();
        $comment = $result['comment'];

        $this->respondWithData([
            'id' => (int) $comment['id'],
            'content' => $comment['content'],
            'created_at' => $comment['created_at'],
            'edited' => false,
            'is_own' => true,
            'author' => [
                'id' => (int) $comment['user_id'],
                'name' => $comment['author_name'] ?? 'Unknown',
                'avatar' => $this->resolveAvatar($comment['author_avatar'] ?? null, $baseUrl),
            ],
            'reactions' => (object) [],
            'user_reactions' => [],
            'replies' => [],
        ], null, 201);
    }

    /**
     * PUT /api/v2/comments/{id}
     */
    public function update(int $id): void
    {
        $userId = $this->getUserId();

        $data = $this->getAllInput();
        $content = trim($data['content'] ?? '');

        if (empty($content)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Comment content is required',
                'content',
                400
            );
            return;
        }

        $result = CommentService::editComment($id, $userId, $content);

        if (!$result['success']) {
            $status = ($result['error'] === 'Unauthorized') ? 403 : 400;
            $this->respondWithError('COMMENT_ERROR', $result['error'] ?? 'Failed to edit comment', null, $status);
            return;
        }

        $this->respondWithData([
            'id' => $id,
            'content' => $content,
            'edited' => true,
        ]);
    }

    /**
     * DELETE /api/v2/comments/{id}
     */
    public function destroy(int $id): void
    {
        $userId = $this->getUserId();

        $result = CommentService::deleteComment($id, $userId);

        if (!$result['success']) {
            $status = ($result['error'] === 'Unauthorized') ? 403 : 400;
            $this->respondWithError('COMMENT_ERROR', $result['error'] ?? 'Failed to delete comment', null, $status);
            return;
        }

        $this->respondWithData(['deleted' => true, 'id' => $id]);
    }

    /**
     * POST /api/v2/comments/{id}/reactions
     *
     * Body: { emoji: "heart" | "thumbs_up" | ... }
     */
    public function reactions(int $id): void
    {
        $userId = $this->getUserId();
        $tenantId = TenantContext::getId();

        $data = $this->getAllInput();
        $emoji = $data['emoji'] ?? null;

        if (!$emoji) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Emoji is required',
                'emoji',
                400
            );
            return;
        }

        // Map frontend emoji names to actual emojis
        $emojiMap = [
            'heart' => '❤️',
            'thumbs_up' => '👍',
            'thumbs_down' => '👎',
            'laugh' => '😂',
            'angry' => '😮',
        ];

        $actualEmoji = $emojiMap[$emoji] ?? $emoji;

        $result = CommentService::toggleReaction($userId, $tenantId, $id, $actualEmoji);

        if (!$result['success']) {
            $this->respondWithError('REACTION_ERROR', $result['error'] ?? 'Failed to toggle reaction', null, 400);
            return;
        }

        $this->respondWithData([
            'action' => $result['action'],
            'emoji' => $emoji,
            'reactions' => $result['reactions'] ?? (object) [],
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function formatComments(array $comments, int $currentUserId, string $baseUrl): array
    {
        return array_map(function ($comment) use ($currentUserId, $baseUrl) {
            return [
                'id' => (int) $comment['id'],
                'content' => $comment['content'] ?? '',
                'created_at' => $comment['created_at'],
                'edited' => $comment['is_edited'] ?? false,
                'is_own' => $comment['is_owner'] ?? false,
                'author' => [
                    'id' => (int) ($comment['user_id'] ?? 0),
                    'name' => $comment['author_name'] ?? 'Unknown',
                    'avatar' => $this->resolveAvatar($comment['author_avatar'] ?? null, $baseUrl),
                ],
                'reactions' => !empty($comment['reactions']) ? $comment['reactions'] : (object) [],
                'user_reactions' => $comment['user_reactions'] ?? [],
                'replies' => !empty($comment['replies'])
                    ? $this->formatComments($comment['replies'], $currentUserId, $baseUrl)
                    : [],
            ];
        }, $comments);
    }

    private function countAll(array $comments): int
    {
        $count = count($comments);
        foreach ($comments as $comment) {
            if (!empty($comment['replies'])) {
                $count += $this->countAll($comment['replies']);
            }
        }
        return $count;
    }

    private function resolveAvatar(?string $avatar, string $baseUrl): ?string
    {
        if (!$avatar) {
            return null;
        }
        if (str_starts_with($avatar, 'http')) {
            return $avatar;
        }
        if (str_starts_with($avatar, '/assets/img/defaults/')) {
            return null; // Don't return default avatar placeholder
        }
        return $baseUrl . '/' . ltrim($avatar, '/');
    }
}
