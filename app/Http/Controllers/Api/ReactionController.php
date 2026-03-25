<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\ReactionService;
use App\Services\RealtimeService;
use App\Services\SocialNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * ReactionController — Handles emoji reactions on posts and comments.
 *
 * Endpoints:
 *   POST   /v2/posts/{id}/reactions        — toggle reaction on a post
 *   GET    /v2/posts/{id}/reactions        — get reaction counts for a post
 *   GET    /v2/posts/{id}/reactions/{type}/users — get users who reacted
 *   POST   /v2/comments/{id}/reactions     — toggle reaction on a comment
 *   GET    /v2/comments/{id}/reactions     — get reaction counts for a comment
 */
class ReactionController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly ReactionService $reactionService,
    ) {}

    // ========================================================================
    // Post Reactions
    // ========================================================================

    /**
     * POST /v2/posts/{id}/reactions
     *
     * Toggle a reaction on a post. Body: { "reaction_type": "love" }
     */
    public function togglePostReaction(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('reaction_toggle', 120, 60);

        $reactionType = $this->input('reaction_type');

        if (empty($reactionType) || !in_array($reactionType, ReactionService::VALID_TYPES, true)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'Invalid reaction_type. Valid types: ' . implode(', ', ReactionService::VALID_TYPES),
                'reaction_type',
                400
            );
        }

        try {
            $result = $this->reactionService->toggleReaction($id, 'post', $reactionType, $userId);

            // Notify the post owner (only on 'added', not 'removed' or 'updated')
            if ($result['action'] === 'added') {
                $this->notifyReaction($userId, 'post', $id, $reactionType);
            }

            return $this->respondWithData($result);
        } catch (\Exception $e) {
            return $this->respondWithError('REACTION_ERROR', 'Failed to toggle reaction', null, 500);
        }
    }

    /**
     * GET /v2/posts/{id}/reactions
     *
     * Get reaction counts for a post.
     */
    public function getPostReactions(int $id): JsonResponse
    {
        $userId = $this->getOptionalUserId();

        try {
            $reactions = $this->reactionService->getReactions($id, 'post', $userId);
            return $this->respondWithData($reactions);
        } catch (\Exception $e) {
            return $this->respondWithError('REACTION_ERROR', 'Failed to get reactions', null, 500);
        }
    }

    /**
     * GET /v2/posts/{id}/reactions/{type}/users
     *
     * Get paginated list of users who reacted with a specific type on a post.
     */
    public function getPostReactors(int $id, string $type): JsonResponse
    {
        if (!in_array($type, ReactionService::VALID_TYPES, true)) {
            return $this->respondWithError('VALIDATION_ERROR', 'Invalid reaction type', 'type', 400);
        }

        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 50);

        try {
            $result = $this->reactionService->getReactors($id, 'post', $type, $page, $perPage);
            return $this->respondWithPaginatedCollection(
                $result['users'],
                $result['total'],
                $page,
                $perPage
            );
        } catch (\Exception $e) {
            return $this->respondWithError('REACTION_ERROR', 'Failed to get reactors', null, 500);
        }
    }

    // ========================================================================
    // Comment Reactions
    // ========================================================================

    /**
     * POST /v2/comments/{id}/reactions
     *
     * Toggle a reaction on a comment. Body: { "reaction_type": "love" }
     */
    public function toggleCommentReaction(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('reaction_toggle', 120, 60);

        $reactionType = $this->input('reaction_type');

        if (empty($reactionType) || !in_array($reactionType, ReactionService::VALID_TYPES, true)) {
            return $this->respondWithError(
                'VALIDATION_ERROR',
                'Invalid reaction_type. Valid types: ' . implode(', ', ReactionService::VALID_TYPES),
                'reaction_type',
                400
            );
        }

        try {
            $result = $this->reactionService->toggleReaction($id, 'comment', $reactionType, $userId);

            // Notify the comment owner (only on 'added')
            if ($result['action'] === 'added') {
                $this->notifyReaction($userId, 'comment', $id, $reactionType);
            }

            return $this->respondWithData($result);
        } catch (\Exception $e) {
            return $this->respondWithError('REACTION_ERROR', 'Failed to toggle reaction', null, 500);
        }
    }

    /**
     * GET /v2/comments/{id}/reactions
     *
     * Get reaction counts for a comment.
     */
    public function getCommentReactions(int $id): JsonResponse
    {
        $userId = $this->getOptionalUserId();

        try {
            $reactions = $this->reactionService->getReactions($id, 'comment', $userId);
            return $this->respondWithData($reactions);
        } catch (\Exception $e) {
            return $this->respondWithError('REACTION_ERROR', 'Failed to get reactions', null, 500);
        }
    }

    // ========================================================================
    // Private Helpers
    // ========================================================================

    /**
     * Send a notification + Pusher broadcast when a user reacts to content.
     *
     * Non-critical: wrapped in try-catch so notification failures don't block
     * the reaction response.
     */
    private function notifyReaction(int $actorId, string $targetType, int $targetId, string $reactionType): void
    {
        try {
            $contentOwnerId = SocialNotificationService::getContentOwnerId($targetType, $targetId);

            // Don't notify if reacting to own content
            if (!$contentOwnerId || $contentOwnerId === $actorId) {
                return;
            }

            // Create in-app notification (bell icon)
            SocialNotificationService::notifyLike(
                $contentOwnerId,
                $actorId,
                $targetType,
                $targetId,
                $reactionType
            );

            // Broadcast to Pusher for real-time update
            RealtimeService::broadcastNotification($contentOwnerId, [
                'type' => 'reaction',
                'target_type' => $targetType,
                'target_id' => $targetId,
                'reaction_type' => $reactionType,
                'actor_id' => $actorId,
            ]);
        } catch (\Throwable $e) {
            Log::debug("Reaction notification error (non-critical): " . $e->getMessage());
        }
    }
}
