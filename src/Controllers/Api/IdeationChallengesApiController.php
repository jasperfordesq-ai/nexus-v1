<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Nexus\Controllers\Api;

use Nexus\Services\IdeationChallengeService;
use Nexus\Services\ChallengeCategoryService;
use Nexus\Services\ChallengeTagService;
use Nexus\Services\IdeaMediaService;
use Nexus\Services\IdeaTeamConversionService;
use Nexus\Services\GroupChatroomService;
use Nexus\Services\TeamTaskService;
use Nexus\Services\TeamDocumentService;
use Nexus\Services\CampaignService;
use Nexus\Services\ChallengeTemplateService;
use Nexus\Services\ChallengeOutcomeService;
use Nexus\Core\ApiErrorCodes;
use Nexus\Core\TenantContext;

/**
 * IdeationChallengesApiController - RESTful API v2 for ideation challenges
 *
 * Provides full CRUD operations for challenges, ideas, votes, and comments
 * with standardized v2 response format.
 *
 * Endpoints (see routes.php for full list):
 *
 * Challenges:       CRUD + status + favorite + duplicate
 * Ideas:            CRUD + vote + status + convert-to-group + media
 * Comments:         List + add + delete
 * Categories:       CRUD (admin)
 * Tags:             List + create + delete (admin)
 * Campaigns:        CRUD + link/unlink challenges (admin)
 * Templates:        CRUD + get-data (admin)
 * Outcomes:         Get + upsert + dashboard (admin)
 * Team Links:       List per challenge
 * Group Chatrooms:  List + create + delete + messages
 * Team Tasks:       CRUD + stats
 * Team Documents:   List + upload + delete
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
     * - status: string ('draft', 'open', 'voting', 'evaluating', 'closed', 'archived')
     * - category_id: int - filter by category
     * - favorites: '1' - show only favorites (auth required)
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

        $categoryId = $this->query('category_id');
        if ($categoryId) {
            $filters['category_id'] = (int)$categoryId;
        }

        if ($this->query('favorites') === '1' && $userId) {
            $filters['favorites_only'] = true;
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
    // IDEA → GROUP CONVERSION
    // ============================================

    /**
     * POST /api/v2/ideation-ideas/{id}/convert-to-group
     *
     * Convert a shortlisted or winning idea into a Group.
     * Requires authenticated user who is admin or the idea creator.
     * Body (optional): { "name": "string", "description": "string", "visibility": "public"|"private" }
     */
    public function convertToGroup(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_convert_group', 5, 60);

        $options = $this->getAllInput();

        $result = IdeaTeamConversionService::convert($id, $userId, $options);

        if (!$result) {
            $errors = IdeaTeamConversionService::getErrors();
            $status = $this->resolveErrorStatus($errors);
            $this->respondWithErrors($errors, $status);
            return;
        }

        $this->respondWithData($result, null, 201);
    }

    // ============================================
    // CATEGORY ENDPOINTS
    // ============================================

    /**
     * GET /api/v2/ideation-categories
     */
    public function listCategories(): void
    {
        $this->checkFeature();
        $categories = ChallengeCategoryService::getAll();
        $this->respondWithData($categories);
    }

    /**
     * POST /api/v2/ideation-categories
     */
    public function createCategory(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_category', 20, 60);

        $data = $this->getAllInput();
        $id = ChallengeCategoryService::create($userId, $data);

        if ($id === null) {
            $errors = ChallengeCategoryService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $category = ChallengeCategoryService::getById($id);
        $this->respondWithData($category, null, 201);
    }

    /**
     * PUT /api/v2/ideation-categories/{id}
     */
    public function updateCategory(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_category', 20, 60);

        $data = $this->getAllInput();
        $success = ChallengeCategoryService::update($id, $userId, $data);

        if (!$success) {
            $errors = ChallengeCategoryService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $category = ChallengeCategoryService::getById($id);
        $this->respondWithData($category);
    }

    /**
     * DELETE /api/v2/ideation-categories/{id}
     */
    public function deleteCategory(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_category', 10, 60);

        $success = ChallengeCategoryService::delete($id, $userId);

        if (!$success) {
            $errors = ChallengeCategoryService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $this->noContent();
    }

    // ============================================
    // TAG ENDPOINTS
    // ============================================

    /**
     * GET /api/v2/ideation-tags
     * Query: ?type=interest|skill|general
     */
    public function listTags(): void
    {
        $this->checkFeature();
        $tagType = $this->query('type');
        $tags = ChallengeTagService::getAll($tagType);
        $this->respondWithData($tags);
    }

    /**
     * POST /api/v2/ideation-tags
     */
    public function createTag(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_tag', 30, 60);

        $data = $this->getAllInput();
        $id = ChallengeTagService::create($userId, $data);

        if ($id === null) {
            $errors = ChallengeTagService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $tag = ChallengeTagService::getById($id);
        $this->respondWithData($tag, null, 201);
    }

    /**
     * DELETE /api/v2/ideation-tags/{id}
     */
    public function deleteTag(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_tag', 10, 60);

        $success = ChallengeTagService::delete($id, $userId);

        if (!$success) {
            $errors = ChallengeTagService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $this->noContent();
    }

    // ============================================
    // IDEA MEDIA ENDPOINTS
    // ============================================

    /**
     * GET /api/v2/ideation-ideas/{id}/media
     */
    public function listIdeaMedia(int $id): void
    {
        $this->checkFeature();
        $media = IdeaMediaService::getMediaForIdea($id);
        $this->respondWithData($media);
    }

    /**
     * POST /api/v2/ideation-ideas/{id}/media
     */
    public function addIdeaMedia(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_media', 20, 60);

        $data = $this->getAllInput();
        $mediaId = IdeaMediaService::addMedia($id, $userId, $data);

        if ($mediaId === null) {
            $errors = IdeaMediaService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $this->respondWithData(['id' => $mediaId], null, 201);
    }

    /**
     * DELETE /api/v2/ideation-media/{id}
     */
    public function deleteIdeaMedia(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_media', 10, 60);

        $success = IdeaMediaService::deleteMedia($id, $userId);

        if (!$success) {
            $errors = IdeaMediaService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $this->noContent();
    }

    // ============================================
    // CAMPAIGN ENDPOINTS
    // ============================================

    /**
     * GET /api/v2/ideation-campaigns
     */
    public function listCampaigns(): void
    {
        $this->checkFeature();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];
        if ($this->query('status')) {
            $filters['status'] = $this->query('status');
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = CampaignService::getAll($filters);
        $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    /**
     * GET /api/v2/ideation-campaigns/{id}
     */
    public function showCampaign(int $id): void
    {
        $this->checkFeature();
        $campaign = CampaignService::getById($id);

        if (!$campaign) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Campaign not found', null, 404);
            return;
        }

        $this->respondWithData($campaign);
    }

    /**
     * POST /api/v2/ideation-campaigns
     */
    public function createCampaign(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_campaign', 10, 60);

        $data = $this->getAllInput();
        $id = CampaignService::create($userId, $data);

        if ($id === null) {
            $errors = CampaignService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $campaign = CampaignService::getById($id);
        $this->respondWithData($campaign, null, 201);
    }

    /**
     * PUT /api/v2/ideation-campaigns/{id}
     */
    public function updateCampaign(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_campaign', 20, 60);

        $data = $this->getAllInput();
        $success = CampaignService::update($id, $userId, $data);

        if (!$success) {
            $errors = CampaignService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $campaign = CampaignService::getById($id);
        $this->respondWithData($campaign);
    }

    /**
     * DELETE /api/v2/ideation-campaigns/{id}
     */
    public function deleteCampaign(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_campaign', 10, 60);

        $success = CampaignService::delete($id, $userId);

        if (!$success) {
            $errors = CampaignService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $this->noContent();
    }

    /**
     * POST /api/v2/ideation-campaigns/{id}/challenges
     * Body: { "challenge_id": int, "sort_order": int }
     */
    public function linkChallengeToCompaign(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_campaign', 20, 60);

        $challengeId = (int)$this->input('challenge_id', 0);
        $sortOrder = (int)$this->input('sort_order', 0);

        if ($challengeId <= 0) {
            $this->respondWithError(ApiErrorCodes::VALIDATION_REQUIRED_FIELD, 'challenge_id is required', 'challenge_id', 400);
            return;
        }

        $success = CampaignService::linkChallenge($id, $challengeId, $userId, $sortOrder);

        if (!$success) {
            $errors = CampaignService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $this->respondWithData(['linked' => true], null, 201);
    }

    /**
     * DELETE /api/v2/ideation-campaigns/{id}/challenges/{challengeId}
     */
    public function unlinkChallengeFromCampaign(int $id, int $challengeId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_campaign', 20, 60);

        $success = CampaignService::unlinkChallenge($id, $challengeId, $userId);

        if (!$success) {
            $errors = CampaignService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $this->noContent();
    }

    // ============================================
    // TEMPLATE ENDPOINTS
    // ============================================

    /**
     * GET /api/v2/ideation-templates
     */
    public function listTemplates(): void
    {
        $this->checkFeature();
        $templates = ChallengeTemplateService::getAll();
        $this->respondWithData($templates);
    }

    /**
     * GET /api/v2/ideation-templates/{id}
     */
    public function showTemplate(int $id): void
    {
        $this->checkFeature();
        $template = ChallengeTemplateService::getById($id);

        if (!$template) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Template not found', null, 404);
            return;
        }

        $this->respondWithData($template);
    }

    /**
     * POST /api/v2/ideation-templates
     */
    public function createTemplate(): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_template', 10, 60);

        $data = $this->getAllInput();
        $id = ChallengeTemplateService::create($userId, $data);

        if ($id === null) {
            $errors = ChallengeTemplateService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $template = ChallengeTemplateService::getById($id);
        $this->respondWithData($template, null, 201);
    }

    /**
     * PUT /api/v2/ideation-templates/{id}
     */
    public function updateTemplate(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_template', 20, 60);

        $data = $this->getAllInput();
        $success = ChallengeTemplateService::update($id, $userId, $data);

        if (!$success) {
            $errors = ChallengeTemplateService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $template = ChallengeTemplateService::getById($id);
        $this->respondWithData($template);
    }

    /**
     * DELETE /api/v2/ideation-templates/{id}
     */
    public function deleteTemplate(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_template', 10, 60);

        $success = ChallengeTemplateService::delete($id, $userId);

        if (!$success) {
            $errors = ChallengeTemplateService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $this->noContent();
    }

    /**
     * GET /api/v2/ideation-templates/{id}/data
     * Returns pre-fill data to start a challenge from template
     */
    public function getTemplateData(int $id): void
    {
        $this->checkFeature();
        $data = ChallengeTemplateService::getTemplateData($id);

        if ($data === null) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Template not found', null, 404);
            return;
        }

        $this->respondWithData($data);
    }

    // ============================================
    // OUTCOME / IMPACT TRACKING ENDPOINTS
    // ============================================

    /**
     * GET /api/v2/ideation-challenges/{id}/outcome
     */
    public function getOutcome(int $id): void
    {
        $this->checkFeature();
        $outcome = ChallengeOutcomeService::getForChallenge($id);
        $this->respondWithData($outcome);
    }

    /**
     * PUT /api/v2/ideation-challenges/{id}/outcome
     */
    public function upsertOutcome(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('ideation_outcome', 10, 60);

        $data = $this->getAllInput();
        $outcomeId = ChallengeOutcomeService::upsert($id, $userId, $data);

        if ($outcomeId === null) {
            $errors = ChallengeOutcomeService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $outcome = ChallengeOutcomeService::getForChallenge($id);
        $this->respondWithData($outcome);
    }

    /**
     * GET /api/v2/ideation-outcomes/dashboard
     */
    public function outcomesDashboard(): void
    {
        $this->checkFeature();
        $dashboard = ChallengeOutcomeService::getDashboard();
        $this->respondWithData($dashboard);
    }

    // ============================================
    // TEAM LINKS (conversion tracking)
    // ============================================

    /**
     * GET /api/v2/ideation-challenges/{id}/team-links
     */
    public function getTeamLinks(int $id): void
    {
        $this->checkFeature();
        $links = IdeaTeamConversionService::getLinksForChallenge($id);
        $this->respondWithData($links);
    }

    // ============================================
    // GROUP CHATROOM ENDPOINTS
    // ============================================

    /**
     * GET /api/v2/groups/{id}/chatrooms
     */
    public function listChatrooms(int $groupId): void
    {
        $this->checkFeature();
        $chatrooms = GroupChatroomService::getChatrooms($groupId);
        $this->respondWithData($chatrooms);
    }

    /**
     * POST /api/v2/groups/{id}/chatrooms
     */
    public function createChatroom(int $groupId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('group_chatroom', 10, 60);

        $data = $this->getAllInput();
        $id = GroupChatroomService::create($groupId, $userId, $data);

        if ($id === null) {
            $errors = GroupChatroomService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $chatroom = GroupChatroomService::getById($id);
        $this->respondWithData($chatroom, null, 201);
    }

    /**
     * DELETE /api/v2/group-chatrooms/{id}
     */
    public function deleteChatroom(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('group_chatroom', 10, 60);

        $success = GroupChatroomService::delete($id, $userId);

        if (!$success) {
            $errors = GroupChatroomService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $this->noContent();
    }

    /**
     * GET /api/v2/group-chatrooms/{id}/messages
     */
    public function chatroomMessages(int $id): void
    {
        $this->checkFeature();
        $filters = [
            'limit' => $this->queryInt('per_page', 50, 1, 100),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = GroupChatroomService::getMessages($id, $filters);
        $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    /**
     * POST /api/v2/group-chatrooms/{id}/messages
     */
    public function postChatroomMessage(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('chatroom_message', 30, 60);

        $body = $this->input('body', '');
        $messageId = GroupChatroomService::postMessage($id, $userId, $body);

        if ($messageId === null) {
            $errors = GroupChatroomService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $this->respondWithData(['id' => $messageId], null, 201);
    }

    /**
     * DELETE /api/v2/group-chatroom-messages/{id}
     */
    public function deleteChatroomMessage(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('chatroom_message', 10, 60);

        $success = GroupChatroomService::deleteMessage($id, $userId);

        if (!$success) {
            $errors = GroupChatroomService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $this->noContent();
    }

    // ============================================
    // TEAM TASK ENDPOINTS
    // ============================================

    /**
     * GET /api/v2/groups/{id}/tasks
     */
    public function listTasks(int $groupId): void
    {
        $this->checkFeature();
        $filters = [
            'limit' => $this->queryInt('per_page', 50, 1, 100),
        ];
        if ($this->query('status')) {
            $filters['status'] = $this->query('status');
        }
        if ($this->query('assigned_to')) {
            $filters['assigned_to'] = (int)$this->query('assigned_to');
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = TeamTaskService::getTasks($groupId, $filters);
        $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    /**
     * POST /api/v2/groups/{id}/tasks
     */
    public function createTask(int $groupId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('team_task', 20, 60);

        $data = $this->getAllInput();
        $id = TeamTaskService::create($groupId, $userId, $data);

        if ($id === null) {
            $errors = TeamTaskService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $task = TeamTaskService::getById($id);
        $this->respondWithData($task, null, 201);
    }

    /**
     * GET /api/v2/team-tasks/{id}
     */
    public function showTask(int $id): void
    {
        $this->checkFeature();
        $task = TeamTaskService::getById($id);

        if (!$task) {
            $this->respondWithError(ApiErrorCodes::RESOURCE_NOT_FOUND, 'Task not found', null, 404);
            return;
        }

        $this->respondWithData($task);
    }

    /**
     * PUT /api/v2/team-tasks/{id}
     */
    public function updateTask(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('team_task', 30, 60);

        $data = $this->getAllInput();
        $success = TeamTaskService::update($id, $userId, $data);

        if (!$success) {
            $errors = TeamTaskService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $task = TeamTaskService::getById($id);
        $this->respondWithData($task);
    }

    /**
     * DELETE /api/v2/team-tasks/{id}
     */
    public function deleteTask(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('team_task', 10, 60);

        $success = TeamTaskService::delete($id, $userId);

        if (!$success) {
            $errors = TeamTaskService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $this->noContent();
    }

    /**
     * GET /api/v2/groups/{id}/task-stats
     */
    public function taskStats(int $groupId): void
    {
        $this->checkFeature();
        $stats = TeamTaskService::getStats($groupId);
        $this->respondWithData($stats);
    }

    // ============================================
    // TEAM DOCUMENT ENDPOINTS
    // ============================================

    /**
     * GET /api/v2/groups/{id}/documents
     */
    public function listDocuments(int $groupId): void
    {
        $this->checkFeature();
        $filters = [
            'limit' => $this->queryInt('per_page', 50, 1, 100),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = TeamDocumentService::getDocuments($groupId, $filters);
        $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    /**
     * POST /api/v2/groups/{id}/documents
     * Multipart upload with 'file' field
     */
    public function uploadDocument(int $groupId): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('team_document', 10, 60);

        $fileData = $_FILES['file'] ?? [];
        $title = $_POST['title'] ?? null;

        $id = TeamDocumentService::upload($groupId, $userId, $fileData, $title);

        if ($id === null) {
            $errors = TeamDocumentService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
        }

        $this->respondWithData(['id' => $id], null, 201);
    }

    /**
     * DELETE /api/v2/team-documents/{id}
     */
    public function deleteDocument(int $id): void
    {
        $this->checkFeature();
        $userId = $this->getUserId();
        $this->verifyCsrf();
        $this->rateLimit('team_document', 10, 60);

        $success = TeamDocumentService::delete($id, $userId);

        if (!$success) {
            $errors = TeamDocumentService::getErrors();
            $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
            return;
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
