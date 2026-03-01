<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\IdeationChallengeService;
use Nexus\Core\ApiErrorCodes;
use Nexus\Core\TenantContext;

/**
 * IdeationChallengesApiController - RESTful API v2 for ideation challenges
 *
 * Provides full CRUD operations for challenges, ideas, votes, and comments
 * with standardized v2 response format.
 *
 * Endpoints:
 * - GET    /api/v2/ideation-challenges              - List challenges
 * - POST   /api/v2/ideation-challenges              - Create challenge (admin)
 * - GET    /api/v2/ideation-challenges/{id}         - Get challenge
 * - PUT    /api/v2/ideation-challenges/{id}         - Update challenge (admin)
 * - DELETE /api/v2/ideation-challenges/{id}         - Delete challenge (admin)
 * - PUT    /api/v2/ideation-challenges/{id}/status  - Change status (admin)
 * - POST   /api/v2/ideation-challenges/{id}/favorite - Toggle favorite (auth)
 * - POST   /api/v2/ideation-challenges/{id}/duplicate - Duplicate challenge (admin)
 * - GET    /api/v2/ideation-challenges/{id}/ideas   - List ideas
 * - POST   /api/v2/ideation-challenges/{id}/ideas   - Submit idea (auth)
 * - GET    /api/v2/ideation-ideas/{id}              - Get idea
 * - PUT    /api/v2/ideation-ideas/{id}              - Update idea (owner)
 * - DELETE /api/v2/ideation-ideas/{id}              - Delete idea (owner/admin)
 * - POST   /api/v2/ideation-ideas/{id}/vote         - Toggle vote (auth)
 * - PUT    /api/v2/ideation-ideas/{id}/status       - Set idea status (admin)
 * - GET    /api/v2/ideation-ideas/{id}/comments     - List comments
 * - POST   /api/v2/ideation-ideas/{id}/comments     - Add comment (auth)
 * - DELETE /api/v2/ideation-comments/{id}           - Delete comment (owner/admin)
 *
 * @package Nexus\Controllers\Api
 */
class IdeationChallengesApiController extends BaseApiController
{
    /** Mark as v2 API for correct headers */
    protected bool $isV2Api = true;

    private function checkFeature(): void
    {
        if (!TenantContext::hasFeature('ideation_challenges')) {
            $this->respondWithError('FEATURE_DISABLED', 'Ideation Challenges module is not enabled for this community', null, 403);
        }
    }

    // ============================================
    // CHALLENGE ENDPOINTS
    // ============================================

    /**
     * GET /api/v2/ideation-challenges
     *
     * List challenges with optional filtering and cursor-based pagination.
     *
     * Query Parameters:
     * - status: string ('draft', 'open', 'voting', 'closed') - optional
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     */
    public function index(): void
    {
        $this->checkFeature();
        // Optional auth — allow unauthenticated browsing
        $userId = $this->getOptionalUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
            'user_id' => $userId,
        ];

        $status = $this->query('status');
        if ($status) {
            $filters['status'] = $status;
        }

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = IdeationChallengeService::getAllChallenges($filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * POST /api/v2/ideation-challenges
     *
     * Create a new challenge (admin only).
     */
    public function store(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_create', 10, 60);

        $data = $this->getAllInput();

        $challengeId = IdeationChallengeService::createChallenge($userId, $data);

        if ($challengeId === null) {
            $errors = IdeationChallengeService::getErrors();
            $status = $this->resolveErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
        }

        $challenge = IdeationChallengeService::getChallengeById($challengeId, $userId);

        $this->respondWithData($challenge, null, 201);
    }

    /**
     * GET /api/v2/ideation-challenges/{id}
     *
     * Get a single challenge by ID.
     */
    public function show(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getOptionalUserId();

        $challenge = IdeationChallengeService::getChallengeById($id, $userId);

        if (!$challenge) {
            $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                'Challenge not found',
                null,
                404
            );
        }

        // Track view (fire-and-forget)
        IdeationChallengeService::incrementViews($id);

        $this->respondWithData($challenge);
    }

    /**
     * PUT /api/v2/ideation-challenges/{id}
     *
     * Update a challenge (admin only).
     */
    public function update(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_update', 20, 60);

        $data = $this->getAllInput();

        $success = IdeationChallengeService::updateChallenge($id, $userId, $data);

        if (!$success) {
            $errors = IdeationChallengeService::getErrors();
            $status = $this->resolveErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
        }

        $challenge = IdeationChallengeService::getChallengeById($id, $userId);

        $this->respondWithData($challenge);
    }

    /**
     * DELETE /api/v2/ideation-challenges/{id}
     *
     * Delete a challenge (admin only).
     */
    public function destroy(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_delete', 10, 60);

        $success = IdeationChallengeService::deleteChallenge($id, $userId);

        if (!$success) {
            $errors = IdeationChallengeService::getErrors();
            $status = $this->resolveErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
        }

        $this->noContent();
    }

    /**
     * PUT /api/v2/ideation-challenges/{id}/status
     *
     * Change challenge status (admin only).
     * Body: { "status": "open" | "voting" | "closed" }
     */
    public function updateStatus(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_status', 20, 60);

        $newStatus = $this->input('status');

        if (empty($newStatus)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Status is required',
                'status',
                400
            );
        }

        $success = IdeationChallengeService::updateChallengeStatus($id, $userId, $newStatus);

        if (!$success) {
            $errors = IdeationChallengeService::getErrors();
            $status = $this->resolveErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
        }

        $challenge = IdeationChallengeService::getChallengeById($id, $userId);

        $this->respondWithData($challenge);
    }

    /**
     * POST /api/v2/ideation-challenges/{id}/favorite
     *
     * Toggle favorite on a challenge (authenticated users).
     */
    public function toggleFavorite(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_favorite', 30, 60);

        $result = IdeationChallengeService::toggleFavorite($id, $userId);

        $errors = IdeationChallengeService::getErrors();
        if (!empty($errors)) {
            $status = $this->resolveErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
        }

        $this->respondWithData($result);
    }

    /**
     * POST /api/v2/ideation-challenges/{id}/duplicate
     *
     * Duplicate a challenge as a draft copy (admin only).
     */
    public function duplicate(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_duplicate', 10, 60);

        $newId = IdeationChallengeService::duplicateChallenge($id, $userId);

        if ($newId === null) {
            $errors = IdeationChallengeService::getErrors();
            $status = $this->resolveErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
        }

        $challenge = IdeationChallengeService::getChallengeById($newId, $userId);

        $this->respondWithData($challenge, null, 201);
    }

    // ============================================
    // IDEA ENDPOINTS
    // ============================================

    /**
     * GET /api/v2/ideation-challenges/{id}/ideas
     *
     * List ideas for a challenge.
     *
     * Query Parameters:
     * - sort: string ('votes' | 'newest') - default 'votes'
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     */
    public function ideas(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getOptionalUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
            'sort' => $this->query('sort', 'votes'),
            'user_id' => $userId,
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = IdeationChallengeService::getIdeas($id, $filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * POST /api/v2/ideation-challenges/{id}/ideas
     *
     * Submit an idea (authenticated users).
     * Body: { "title": "string", "description": "string" }
     */
    public function submitIdea(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_submit_idea', 10, 60);

        $data = $this->getAllInput();

        $ideaId = IdeationChallengeService::submitIdea($id, $userId, $data);

        if ($ideaId === null) {
            $errors = IdeationChallengeService::getErrors();
            $status = $this->resolveErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
        }

        $idea = IdeationChallengeService::getIdeaById($ideaId, $userId);

        $this->respondWithData($idea, null, 201);
    }

    /**
     * GET /api/v2/ideation-ideas/{id}
     *
     * Get a single idea by ID.
     */
    public function showIdea(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getOptionalUserId();

        $idea = IdeationChallengeService::getIdeaById($id, $userId);

        if (!$idea) {
            $this->respondWithError(
                ApiErrorCodes::RESOURCE_NOT_FOUND,
                'Idea not found',
                null,
                404
            );
        }

        $this->respondWithData($idea);
    }

    /**
     * PUT /api/v2/ideation-ideas/{id}
     *
     * Update an idea (owner only, challenge must be open).
     */
    public function updateIdea(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_update_idea', 20, 60);

        $data = $this->getAllInput();

        $success = IdeationChallengeService::updateIdea($id, $userId, $data);

        if (!$success) {
            $errors = IdeationChallengeService::getErrors();
            $status = $this->resolveErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
        }

        $idea = IdeationChallengeService::getIdeaById($id, $userId);

        $this->respondWithData($idea);
    }

    /**
     * DELETE /api/v2/ideation-ideas/{id}
     *
     * Delete an idea (owner or admin).
     */
    public function deleteIdea(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_delete_idea', 10, 60);

        $success = IdeationChallengeService::deleteIdea($id, $userId);

        if (!$success) {
            $errors = IdeationChallengeService::getErrors();
            $status = $this->resolveErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
        }

        $this->noContent();
    }

    /**
     * POST /api/v2/ideation-ideas/{id}/vote
     *
     * Toggle vote on an idea (authenticated users).
     */
    public function voteIdea(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_vote', 30, 60);

        $result = IdeationChallengeService::voteIdea($id, $userId);

        if ($result === null) {
            $errors = IdeationChallengeService::getErrors();
            $status = $this->resolveErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
        }

        $this->respondWithData($result);
    }

    /**
     * PUT /api/v2/ideation-ideas/{id}/status
     *
     * Update idea status (admin only: shortlisted/winner).
     * Body: { "status": "submitted" | "shortlisted" | "winner" | "withdrawn" }
     */
    public function updateIdeaStatus(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_idea_status', 20, 60);

        $newStatus = $this->input('status');

        if (empty($newStatus)) {
            $this->respondWithError(
                ApiErrorCodes::VALIDATION_REQUIRED_FIELD,
                'Status is required',
                'status',
                400
            );
        }

        $success = IdeationChallengeService::updateIdeaStatus($id, $userId, $newStatus);

        if (!$success) {
            $errors = IdeationChallengeService::getErrors();
            $status = $this->resolveErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
        }

        $idea = IdeationChallengeService::getIdeaById($id, $userId);

        $this->respondWithData($idea);
    }

    // ============================================
    // COMMENT ENDPOINTS
    // ============================================

    /**
     * GET /api/v2/ideation-ideas/{id}/comments
     *
     * List comments for an idea.
     *
     * Query Parameters:
     * - cursor: string (pagination cursor)
     * - per_page: int (default 20, max 100)
     */
    public function comments(int $id): void
    {
        $this->checkFeature();
        $this->getOptionalUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = IdeationChallengeService::getComments($id, $filters);

        $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /**
     * POST /api/v2/ideation-ideas/{id}/comments
     *
     * Add a comment to an idea (authenticated users).
     * Body: { "body": "string" }
     */
    public function addComment(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_comment', 20, 60);

        $body = $this->input('body', '');

        $commentId = IdeationChallengeService::addComment($id, $userId, $body);

        if ($commentId === null) {
            $errors = IdeationChallengeService::getErrors();
            $status = $this->resolveErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
        }

        // Fetch the comment with author info
        $comments = IdeationChallengeService::getComments($id, ['limit' => 1]);
        $comment = !empty($comments['items']) ? $comments['items'][0] : ['id' => $commentId];

        $this->respondWithData($comment, null, 201);
    }

    /**
     * DELETE /api/v2/ideation-comments/{id}
     *
     * Delete a comment (owner or admin).
     */
    public function deleteComment(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_delete_comment', 10, 60);

        $success = IdeationChallengeService::deleteComment($id, $userId);

        if (!$success) {
            $errors = IdeationChallengeService::getErrors();
            $status = $this->resolveErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
        }

        $this->noContent();
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Resolve HTTP status code from error array
     */
    private function resolveErrorStatus(array $errors): int
    {
        foreach ($errors as $error) {
            $code = $error['code'] ?? '';
            if ($code === ApiErrorCodes::RESOURCE_NOT_FOUND) {
                return 404;
            }
            if ($code === ApiErrorCodes::RESOURCE_FORBIDDEN) {
                return 403;
            }
            if ($code === ApiErrorCodes::RESOURCE_CONFLICT) {
                return 409;
            }
        }
        return 422;
    }
}
