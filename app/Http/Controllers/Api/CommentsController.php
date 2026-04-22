<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\User;
use App\Services\CommentService;
use Illuminate\Http\JsonResponse;

/**
 * CommentsController — Eloquent-powered threaded comments with reactions.
 *
 * Fully migrated from legacy delegation to Eloquent via CommentService.
 */
class CommentsController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly CommentService $commentService,
    ) {}

    // -----------------------------------------------------------------
    //  GET /api/v2/comments?target_type=...&target_id=...
    // -----------------------------------------------------------------

    public function index(): JsonResponse
    {
        $targetType = $this->query('target_type') ?? $this->query('commentable_type');
        $targetId = $this->queryInt('target_id') ?? $this->queryInt('commentable_id');

        if (! $targetType || ! $targetId) {
            return $this->respondWithError(
                'VALIDATION_REQUIRED_FIELD',
                __('api.target_type_and_id_required'),
                null,
                400
            );
        }

        $currentUserId = $this->getOptionalUserId() ?? 0;
        $comments = $this->commentService->getForEntity($targetType, $targetId, $currentUserId);
        $count = $this->commentService->countAll($comments);

        return $this->respondWithData([
            'comments' => $comments,
            'count'    => $count,
        ]);
    }

    // -----------------------------------------------------------------
    //  POST /api/v2/comments
    // -----------------------------------------------------------------

    public function store(): JsonResponse
    {
        $userId = $this->getUserId();
        // Rate-limit comment creation per user to block spam/flame floods.
        $this->rateLimit('comments_create', 30, 60);
        $tenantId = $this->getTenantId();

        $data = $this->getAllInput();
        $targetType = $data['target_type'] ?? null;
        $targetId = isset($data['target_id']) ? (int) $data['target_id'] : null;
        $content = trim($data['content'] ?? '');

        if (! $targetType || ! $targetId) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', __('api.target_type_and_id_required'), null, 400);
        }

        if (empty($content)) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', __('api.comment_text_required'), 'content', 400);
        }

        if (mb_strlen($content) > 10000) {
            return $this->respondWithError('VALIDATION_INVALID_VALUE', __('api.comment_too_long'), 'content', 422);
        }

        $comment = $this->commentService->create($targetType, $targetId, $userId, $tenantId, $data);

        $comment->load('user:id,first_name,last_name,avatar_url');
        $user = $comment->user;

        return $this->respondWithData([
            'id'             => $comment->id,
            'content'        => $comment->content,
            'created_at'     => $comment->created_at?->toIso8601String(),
            'edited'         => false,
            'is_own'         => true,
            'author'         => [
                'id'     => $userId,
                'name'   => $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : 'Unknown',
                'avatar' => $user->avatar_url ?? null,
            ],
            'reactions'      => (object) [],
            'user_reactions' => [],
            'replies'        => [],
        ], null, 201);
    }

    // -----------------------------------------------------------------
    //  PUT /api/v2/comments/{id}
    // -----------------------------------------------------------------

    public function update(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('comments_edit', 30, 60);

        $content = trim($this->input('content', ''));

        if (empty($content)) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', __('api.comment_text_required'), 'content', 400);
        }

        $updated = $this->commentService->update($id, $userId, $content);

        if (! $updated) {
            return $this->respondWithError('RESOURCE_FORBIDDEN', __('api.cannot_edit_comment'), null, 403);
        }

        return $this->respondWithData([
            'id'      => $id,
            'content' => $content,
            'edited'  => true,
        ]);
    }

    // -----------------------------------------------------------------
    //  DELETE /api/v2/comments/{id}
    // -----------------------------------------------------------------

    public function destroy(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $this->rateLimit('comments_delete', 30, 60);

        $deleted = $this->commentService->delete($id, $userId);

        if (! $deleted) {
            return $this->respondWithError('RESOURCE_FORBIDDEN', __('api.cannot_delete_comment'), null, 403);
        }

        return $this->respondWithData(['deleted' => true, 'id' => $id]);
    }

    // -----------------------------------------------------------------
    //  POST /api/v2/comments/{id}/reactions
    // -----------------------------------------------------------------

    public function reactions(int $id): JsonResponse
    {
        $userId = $this->getUserId();
        $tenantId = $this->getTenantId();

        $emoji = $this->input('emoji');

        if (! $emoji) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', __('api.emoji_required'), 'emoji', 400);
        }

        // Map frontend emoji names to actual emojis
        $emojiMap = [
            'heart'       => "\u{2764}\u{FE0F}",
            'thumbs_up'   => "\u{1F44D}",
            'thumbs_down' => "\u{1F44E}",
            'laugh'       => "\u{1F602}",
            'angry'       => "\u{1F62E}",
        ];

        $actualEmoji = $emojiMap[$emoji] ?? $emoji;

        $result = $this->commentService->toggleReaction($userId, $tenantId, $id, $actualEmoji);

        // Notify comment author on reaction add (not on remove/update)
        if ($result['action'] === 'added') {
            try {
                $comment = Comment::find($id);
                if ($comment && (int) $comment->tenant_id === TenantContext::getId() && (int) $comment->user_id !== $userId) {
                    $reactor = User::find($userId);
                    $recipient = User::find((int) $comment->user_id);
                    LocaleContext::withLocale($recipient, function () use ($reactor, $comment) {
                        $reactorName = $reactor ? trim(($reactor->first_name ?? '') . ' ' . ($reactor->last_name ?? '')) : __('emails.common.fallback_someone');
                        $link = $comment->target_type && $comment->target_id
                            ? "/{$comment->target_type}s/{$comment->target_id}"
                            : null;
                        $message = __('api_controllers_3.comments.reaction', ['name' => $reactorName]);
                        Notification::createNotification((int) $comment->user_id, $message, $link, 'reaction');
                    });
                }
            } catch (\Throwable $e) {
                \Log::warning('Comment reaction notification failed', ['comment' => $id, 'reactor' => $userId, 'error' => $e->getMessage()]);
            }
        }

        return $this->respondWithData([
            'action'    => $result['action'],
            'emoji'     => $emoji,
            'reactions' => $result['reactions'],
        ]);
    }
}
