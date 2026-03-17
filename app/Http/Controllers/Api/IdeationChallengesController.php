<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\IdeationChallengeService;
use App\Services\ChallengeTagService;
use App\Services\IdeaMediaService;
use App\Services\IdeaTeamConversionService;
use App\Services\TeamDocumentService;
use App\Services\ChallengeOutcomeService;
use Nexus\Core\TenantContext;
use Nexus\Core\ApiErrorCodes;
use Nexus\Services\ChallengeCategoryService;
use Nexus\Services\GroupChatroomService;
use Nexus\Services\TeamTaskService;
use Nexus\Services\CampaignService;
use Nexus\Services\ChallengeTemplateService;

/**
 * IdeationChallengesController — Community ideation challenges and idea submissions.
 *
 * Core CRUD uses Eloquent via IdeationChallengeService.
 * All other methods call legacy static services directly (no ob_start delegation).
 * All methods are now fully native — no legacy delegation remaining.
 */
class IdeationChallengesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly IdeationChallengeService $challengeService,
        private readonly ChallengeTagService $challengeTagService,
        private readonly IdeaMediaService $ideaMediaService,
        private readonly IdeaTeamConversionService $ideaTeamConversionService,
        private readonly TeamDocumentService $teamDocumentService,
        private readonly ChallengeOutcomeService $challengeOutcomeService,
    ) {}

    /**
     * Ensure the ideation_challenges feature is enabled.
     */
    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('ideation_challenges')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', 'Ideation Challenges module is not enabled for this community', null, 403)
            );
        }
    }

    /**
     * Resolve HTTP status from error array.
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

    // ========================================
    // CHALLENGE ENDPOINTS (already native)
    // ========================================

    /** GET /api/v2/ideation-challenges — list challenges with filters + cursor pagination */
    public function index(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('ideation_list', 60, 60);

        // Optional auth — allow unauthenticated browsing
        $this->getOptionalUserId();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('status')) {
            $filters['status'] = $this->query('status');
        }
        if ($this->query('category_id')) {
            $filters['category_id'] = (int) $this->query('category_id');
        }
        if ($this->query('search')) {
            $filters['search'] = $this->query('search');
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->challengeService->getAll($filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /** GET /api/v2/ideation-challenges/{id} — single challenge detail */
    public function show(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('ideation_show', 120, 60);

        $challenge = $this->challengeService->getById($id);

        if (!$challenge) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Challenge not found', null, 404);
        }

        return $this->respondWithData($challenge);
    }

    /** POST /api/v2/ideation-challenges — create a new challenge */
    public function store(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_create', 10, 60);

        $data = $this->getAllInput();
        $challengeId = $this->challengeService->create($userId, $data);

        $challenge = $this->challengeService->getById($challengeId);

        return $this->respondWithData($challenge, null, 201);
    }

    /** GET /api/v2/ideation-challenges/{id}/ideas — list ideas for a challenge */
    public function ideas(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('ideation_ideas', 60, 60);

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
            'sort'  => $this->query('sort', 'votes'),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->challengeService->getIdeas($id, $filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /** POST /api/v2/ideation-challenges/{id}/ideas — submit idea to a challenge */
    public function submitIdea(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_submit_idea', 10, 60);

        $data = $this->getAllInput();
        $ideaId = $this->challengeService->submitIdea($id, $userId, $data);

        return $this->respondWithData(['id' => $ideaId], null, 201);
    }

    // ========================================
    // CHALLENGE MANAGEMENT — now native
    // ========================================

    /** PUT /api/v2/ideation-challenges/{id} */
    public function update($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_update', 20, 60);

        $data = $this->getAllInput();
        $success = \Nexus\Services\IdeationChallengeService::updateChallenge((int) $id, $userId, $data);

        if (!$success) {
            $errors = \Nexus\Services\IdeationChallengeService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $challenge = \Nexus\Services\IdeationChallengeService::getChallengeById((int) $id, $userId);
        return $this->respondWithData($challenge);
    }

    /** DELETE /api/v2/ideation-challenges/{id} */
    public function destroy($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_delete', 10, 60);

        $success = \Nexus\Services\IdeationChallengeService::deleteChallenge((int) $id, $userId);

        if (!$success) {
            $errors = \Nexus\Services\IdeationChallengeService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->noContent();
    }

    /** PUT /api/v2/ideation-challenges/{id}/status */
    public function updateStatus($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_status', 20, 60);

        $newStatus = $this->input('status');

        if (empty($newStatus)) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'Status is required', 'status', 400);
        }

        $success = \Nexus\Services\IdeationChallengeService::updateChallengeStatus((int) $id, $userId, $newStatus);

        if (!$success) {
            $errors = \Nexus\Services\IdeationChallengeService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $challenge = \Nexus\Services\IdeationChallengeService::getChallengeById((int) $id, $userId);
        return $this->respondWithData($challenge);
    }

    /** POST /api/v2/ideation-challenges/{id}/favorite */
    public function toggleFavorite($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_favorite', 30, 60);

        $result = \Nexus\Services\IdeationChallengeService::toggleFavorite((int) $id, $userId);

        $errors = \Nexus\Services\IdeationChallengeService::getErrors();
        if (!empty($errors)) {
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->respondWithData($result);
    }

    /** POST /api/v2/ideation-challenges/{id}/duplicate */
    public function duplicate($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_duplicate', 10, 60);

        $newId = \Nexus\Services\IdeationChallengeService::duplicateChallenge((int) $id, $userId);

        if ($newId === null) {
            $errors = \Nexus\Services\IdeationChallengeService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $challenge = \Nexus\Services\IdeationChallengeService::getChallengeById($newId, $userId);
        return $this->respondWithData($challenge, null, 201);
    }

    // ========================================
    // IDEA ENDPOINTS — now native
    // ========================================

    /** GET /api/v2/ideation-ideas/{id} */
    public function showIdea($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getOptionalUserId();

        $idea = \Nexus\Services\IdeationChallengeService::getIdeaById((int) $id, $userId);

        if (!$idea) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Idea not found', null, 404);
        }

        return $this->respondWithData($idea);
    }

    /** PUT /api/v2/ideation-ideas/{id} */
    public function updateIdea($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_update_idea', 20, 60);

        $data = $this->getAllInput();
        $success = \Nexus\Services\IdeationChallengeService::updateIdea((int) $id, $userId, $data);

        if (!$success) {
            $errors = \Nexus\Services\IdeationChallengeService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $idea = \Nexus\Services\IdeationChallengeService::getIdeaById((int) $id, $userId);
        return $this->respondWithData($idea);
    }

    /** PUT /api/v2/ideation-ideas/{id}/draft */
    public function updateDraft($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_update_draft', 20, 60);

        $data = $this->getAllInput();
        $success = \Nexus\Services\IdeationChallengeService::updateDraftIdea((int) $id, $userId, $data);

        if (!$success) {
            $errors = \Nexus\Services\IdeationChallengeService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->respondWithData(['success' => true]);
    }

    /** DELETE /api/v2/ideation-ideas/{id} */
    public function deleteIdea($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_delete_idea', 10, 60);

        $success = \Nexus\Services\IdeationChallengeService::deleteIdea((int) $id, $userId);

        if (!$success) {
            $errors = \Nexus\Services\IdeationChallengeService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->noContent();
    }

    /** POST /api/v2/ideation-ideas/{id}/vote */
    public function voteIdea($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_vote', 30, 60);

        $result = \Nexus\Services\IdeationChallengeService::voteIdea((int) $id, $userId);

        if ($result === null) {
            $errors = \Nexus\Services\IdeationChallengeService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->respondWithData($result);
    }

    /** PUT /api/v2/ideation-ideas/{id}/status */
    public function updateIdeaStatus($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_idea_status', 20, 60);

        $newStatus = $this->input('status');

        if (empty($newStatus)) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'Status is required', 'status', 400);
        }

        $success = \Nexus\Services\IdeationChallengeService::updateIdeaStatus((int) $id, $userId, $newStatus);

        if (!$success) {
            $errors = \Nexus\Services\IdeationChallengeService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $idea = \Nexus\Services\IdeationChallengeService::getIdeaById((int) $id, $userId);
        return $this->respondWithData($idea);
    }

    /** GET /api/v2/ideation-challenges/{id}/ideas/drafts */
    public function ideaDrafts($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();

        $drafts = \Nexus\Services\IdeationChallengeService::getUserDrafts((int) $id, $userId);
        return $this->respondWithData($drafts);
    }

    // ========================================
    // COMMENT ENDPOINTS — now native
    // ========================================

    /** GET /api/v2/ideation-ideas/{id}/comments */
    public function comments($id): JsonResponse
    {
        $this->ensureFeature();

        $filters = [
            'limit' => $this->queryInt('per_page', 20, 1, 100),
        ];

        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = \Nexus\Services\IdeationChallengeService::getComments((int) $id, $filters);

        return $this->respondWithCollection(
            $result['items'],
            $result['cursor'],
            $filters['limit'],
            $result['has_more']
        );
    }

    /** POST /api/v2/ideation-ideas/{id}/comments */
    public function addComment($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_comment', 20, 60);

        $body = $this->input('body', '');
        $commentId = \Nexus\Services\IdeationChallengeService::addComment((int) $id, $userId, $body);

        if ($commentId === null) {
            $errors = \Nexus\Services\IdeationChallengeService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $comments = \Nexus\Services\IdeationChallengeService::getComments((int) $id, ['limit' => 1]);
        $comment = !empty($comments['items']) ? $comments['items'][0] : ['id' => $commentId];

        return $this->respondWithData($comment, null, 201);
    }

    /** DELETE /api/v2/ideation-comments/{id} */
    public function deleteComment($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_delete_comment', 10, 60);

        $success = \Nexus\Services\IdeationChallengeService::deleteComment((int) $id, $userId);

        if (!$success) {
            $errors = \Nexus\Services\IdeationChallengeService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->noContent();
    }

    // ========================================
    // IDEA → GROUP CONVERSION — now native
    // ========================================

    /** POST /api/v2/ideation-ideas/{id}/convert-to-group */
    public function convertToGroup($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_convert_group', 5, 60);

        $options = $this->getAllInput();
        $result = $this->ideaTeamConversionService->convert((int) $id, $userId, $options);

        if (!$result) {
            $errors = $this->ideaTeamConversionService->getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->respondWithData($result, null, 201);
    }

    // ========================================
    // CATEGORY ENDPOINTS — now native
    // ========================================

    /** GET /api/v2/ideation-categories */
    public function listCategories(): JsonResponse
    {
        $this->ensureFeature();
        $categories = ChallengeCategoryService::getAll();
        return $this->respondWithData($categories);
    }

    /** POST /api/v2/ideation-categories */
    public function createCategory(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_category', 20, 60);

        $data = $this->getAllInput();
        $id = ChallengeCategoryService::create($userId, $data);

        if ($id === null) {
            $errors = ChallengeCategoryService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $category = ChallengeCategoryService::getById($id);
        return $this->respondWithData($category, null, 201);
    }

    /** PUT /api/v2/ideation-categories/{id} */
    public function updateCategory($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_category', 20, 60);

        $data = $this->getAllInput();
        $success = ChallengeCategoryService::update((int) $id, $userId, $data);

        if (!$success) {
            $errors = ChallengeCategoryService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $category = ChallengeCategoryService::getById((int) $id);
        return $this->respondWithData($category);
    }

    /** DELETE /api/v2/ideation-categories/{id} */
    public function deleteCategory($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_category', 10, 60);

        $success = ChallengeCategoryService::delete((int) $id, $userId);

        if (!$success) {
            $errors = ChallengeCategoryService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->noContent();
    }

    // ========================================
    // TAG ENDPOINTS — now native
    // ========================================

    /** GET /api/v2/ideation-tags/popular */
    public function popularTags(): JsonResponse
    {
        $this->ensureFeature();
        $tags = \Nexus\Services\IdeationChallengeService::getAllTags();
        return $this->respondWithData($tags);
    }

    /** GET /api/v2/ideation-tags */
    public function listTags(): JsonResponse
    {
        $this->ensureFeature();
        $tagType = $this->query('type');
        $tags = $this->challengeTagService->getAll($tagType);
        return $this->respondWithData($tags);
    }

    /** POST /api/v2/ideation-tags */
    public function createTag(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_tag', 30, 60);

        $data = $this->getAllInput();
        $id = $this->challengeTagService->create($userId, $data);

        if ($id === null) {
            $errors = $this->challengeTagService->getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $tag = $this->challengeTagService->getById($id);
        return $this->respondWithData($tag, null, 201);
    }

    /** DELETE /api/v2/ideation-tags/{id} */
    public function deleteTag($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_tag', 10, 60);

        $success = $this->challengeTagService->delete((int) $id, $userId);

        if (!$success) {
            $errors = $this->challengeTagService->getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->noContent();
    }

    // ========================================
    // IDEA MEDIA ENDPOINTS — now native
    // ========================================

    /** GET /api/v2/ideation-ideas/{id}/media */
    public function listIdeaMedia($id): JsonResponse
    {
        $this->ensureFeature();
        $media = $this->ideaMediaService->getMediaForIdea((int) $id);
        return $this->respondWithData($media);
    }

    /** POST /api/v2/ideation-ideas/{id}/media */
    public function addIdeaMedia($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_media', 20, 60);

        $data = $this->getAllInput();
        $mediaId = $this->ideaMediaService->addMedia((int) $id, $userId, $data);

        if ($mediaId === null) {
            $errors = $this->ideaMediaService->getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->respondWithData(['id' => $mediaId], null, 201);
    }

    /** DELETE /api/v2/ideation-media/{id} */
    public function deleteIdeaMedia($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_media', 10, 60);

        $success = $this->ideaMediaService->deleteMedia((int) $id, $userId);

        if (!$success) {
            $errors = $this->ideaMediaService->getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->noContent();
    }

    // ========================================
    // CAMPAIGN ENDPOINTS — now native
    // ========================================

    /** GET /api/v2/ideation-campaigns */
    public function listCampaigns(): JsonResponse
    {
        $this->ensureFeature();

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
        return $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    /** GET /api/v2/ideation-campaigns/{id} */
    public function showCampaign($id): JsonResponse
    {
        $this->ensureFeature();
        $campaign = CampaignService::getById((int) $id);

        if (!$campaign) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Campaign not found', null, 404);
        }

        return $this->respondWithData($campaign);
    }

    /** POST /api/v2/ideation-campaigns */
    public function createCampaign(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_campaign', 10, 60);

        $data = $this->getAllInput();
        $id = CampaignService::create($userId, $data);

        if ($id === null) {
            $errors = CampaignService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $campaign = CampaignService::getById($id);
        return $this->respondWithData($campaign, null, 201);
    }

    /** PUT /api/v2/ideation-campaigns/{id} */
    public function updateCampaign($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_campaign', 20, 60);

        $data = $this->getAllInput();
        $success = CampaignService::update((int) $id, $userId, $data);

        if (!$success) {
            $errors = CampaignService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $campaign = CampaignService::getById((int) $id);
        return $this->respondWithData($campaign);
    }

    /** DELETE /api/v2/ideation-campaigns/{id} */
    public function deleteCampaign($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_campaign', 10, 60);

        $success = CampaignService::delete((int) $id, $userId);

        if (!$success) {
            $errors = CampaignService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->noContent();
    }

    /** POST /api/v2/ideation-campaigns/{id}/challenges */
    public function linkChallengeToCampaign($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_campaign', 20, 60);

        $challengeId = $this->inputInt('challenge_id');
        $sortOrder = $this->inputInt('sort_order', 0);

        if (!$challengeId || $challengeId <= 0) {
            return $this->respondWithError('VALIDATION_REQUIRED_FIELD', 'challenge_id is required', 'challenge_id', 400);
        }

        $success = CampaignService::linkChallenge((int) $id, $challengeId, $userId, $sortOrder);

        if (!$success) {
            $errors = CampaignService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->respondWithData(['linked' => true], null, 201);
    }

    /** DELETE /api/v2/ideation-campaigns/{id}/challenges/{challengeId} */
    public function unlinkChallengeFromCampaign($id, $challengeId): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_campaign', 20, 60);

        $success = CampaignService::unlinkChallenge((int) $id, (int) $challengeId, $userId);

        if (!$success) {
            $errors = CampaignService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->noContent();
    }

    // ========================================
    // TEMPLATE ENDPOINTS — now native
    // ========================================

    /** GET /api/v2/ideation-templates */
    public function listTemplates(): JsonResponse
    {
        $this->ensureFeature();
        $templates = ChallengeTemplateService::getAll();
        return $this->respondWithData($templates);
    }

    /** GET /api/v2/ideation-templates/{id} */
    public function showTemplate($id): JsonResponse
    {
        $this->ensureFeature();
        $template = ChallengeTemplateService::getById((int) $id);

        if (!$template) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Template not found', null, 404);
        }

        return $this->respondWithData($template);
    }

    /** POST /api/v2/ideation-templates */
    public function createTemplate(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_template', 10, 60);

        $data = $this->getAllInput();
        $id = ChallengeTemplateService::create($userId, $data);

        if ($id === null) {
            $errors = ChallengeTemplateService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $template = ChallengeTemplateService::getById($id);
        return $this->respondWithData($template, null, 201);
    }

    /** PUT /api/v2/ideation-templates/{id} */
    public function updateTemplate($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_template', 20, 60);

        $data = $this->getAllInput();
        $success = ChallengeTemplateService::update((int) $id, $userId, $data);

        if (!$success) {
            $errors = ChallengeTemplateService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $template = ChallengeTemplateService::getById((int) $id);
        return $this->respondWithData($template);
    }

    /** DELETE /api/v2/ideation-templates/{id} */
    public function deleteTemplate($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_template', 10, 60);

        $success = ChallengeTemplateService::delete((int) $id, $userId);

        if (!$success) {
            $errors = ChallengeTemplateService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->noContent();
    }

    /** GET /api/v2/ideation-templates/{id}/data */
    public function getTemplateData($id): JsonResponse
    {
        $this->ensureFeature();
        $data = ChallengeTemplateService::getTemplateData((int) $id);

        if ($data === null) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Template not found', null, 404);
        }

        return $this->respondWithData($data);
    }

    // ========================================
    // OUTCOME / IMPACT TRACKING — now native
    // ========================================

    /** GET /api/v2/ideation-challenges/{id}/outcome */
    public function getOutcome($id): JsonResponse
    {
        $this->ensureFeature();
        $outcome = $this->challengeOutcomeService->getForChallenge((int) $id);
        return $this->respondWithData($outcome);
    }

    /** PUT /api/v2/ideation-challenges/{id}/outcome */
    public function upsertOutcome($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('ideation_outcome', 10, 60);

        $data = $this->getAllInput();
        $outcomeId = $this->challengeOutcomeService->upsert((int) $id, $userId, $data);

        if ($outcomeId === null) {
            $errors = $this->challengeOutcomeService->getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $outcome = $this->challengeOutcomeService->getForChallenge((int) $id);
        return $this->respondWithData($outcome);
    }

    /** GET /api/v2/ideation-outcomes/dashboard */
    public function outcomesDashboard(): JsonResponse
    {
        $this->ensureFeature();
        $dashboard = $this->challengeOutcomeService->getDashboard();
        return $this->respondWithData($dashboard);
    }

    // ========================================
    // TEAM LINKS — now native
    // ========================================

    /** GET /api/v2/ideation-challenges/{id}/team-links */
    public function getTeamLinks($id): JsonResponse
    {
        $this->ensureFeature();
        $links = $this->ideaTeamConversionService->getLinksForChallenge((int) $id);
        return $this->respondWithData($links);
    }

    // ========================================
    // GROUP CHATROOM ENDPOINTS — now native
    // ========================================

    /** GET /api/v2/groups/{id}/chatrooms */
    public function listChatrooms($id): JsonResponse
    {
        $this->ensureFeature();
        $chatrooms = GroupChatroomService::getChatrooms((int) $id);
        return $this->respondWithData($chatrooms);
    }

    /** POST /api/v2/groups/{id}/chatrooms */
    public function createChatroom($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('group_chatroom', 10, 60);

        $data = $this->getAllInput();
        $chatroomId = GroupChatroomService::create((int) $id, $userId, $data);

        if ($chatroomId === null) {
            $errors = GroupChatroomService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $chatroom = GroupChatroomService::getById($chatroomId);
        return $this->respondWithData($chatroom, null, 201);
    }

    /** DELETE /api/v2/group-chatrooms/{id} */
    public function deleteChatroom($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('group_chatroom', 10, 60);

        $success = GroupChatroomService::delete((int) $id, $userId);

        if (!$success) {
            $errors = GroupChatroomService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->noContent();
    }

    /** GET /api/v2/group-chatrooms/{id}/messages */
    public function chatroomMessages($id): JsonResponse
    {
        $this->ensureFeature();
        $filters = [
            'limit' => $this->queryInt('per_page', 50, 1, 100),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = GroupChatroomService::getMessages((int) $id, $filters);
        return $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    /** POST /api/v2/group-chatrooms/{id}/messages */
    public function postChatroomMessage($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('chatroom_message', 30, 60);

        $body = $this->input('body', '');
        $messageId = GroupChatroomService::postMessage((int) $id, $userId, $body);

        if ($messageId === null) {
            $errors = GroupChatroomService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->respondWithData(['id' => $messageId], null, 201);
    }

    /** DELETE /api/v2/group-chatroom-messages/{id} */
    public function deleteChatroomMessage($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('chatroom_message', 10, 60);

        $success = GroupChatroomService::deleteMessage((int) $id, $userId);

        if (!$success) {
            $errors = GroupChatroomService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->noContent();
    }

    // ========================================
    // TEAM TASK ENDPOINTS — now native
    // ========================================

    /** GET /api/v2/groups/{id}/tasks */
    public function listTasks($id): JsonResponse
    {
        $this->ensureFeature();
        $filters = [
            'limit' => $this->queryInt('per_page', 50, 1, 100),
        ];
        if ($this->query('status')) {
            $filters['status'] = $this->query('status');
        }
        if ($this->query('assigned_to')) {
            $filters['assigned_to'] = (int) $this->query('assigned_to');
        }
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = TeamTaskService::getTasks((int) $id, $filters);
        return $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    /** POST /api/v2/groups/{id}/tasks */
    public function createTask($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('team_task', 20, 60);

        $data = $this->getAllInput();
        $taskId = TeamTaskService::create((int) $id, $userId, $data);

        if ($taskId === null) {
            $errors = TeamTaskService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $task = TeamTaskService::getById($taskId);
        return $this->respondWithData($task, null, 201);
    }

    /** GET /api/v2/team-tasks/{id} */
    public function showTask($id): JsonResponse
    {
        $this->ensureFeature();
        $task = TeamTaskService::getById((int) $id);

        if (!$task) {
            return $this->respondWithError('RESOURCE_NOT_FOUND', 'Task not found', null, 404);
        }

        return $this->respondWithData($task);
    }

    /** PUT /api/v2/team-tasks/{id} */
    public function updateTask($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('team_task', 30, 60);

        $data = $this->getAllInput();
        $success = TeamTaskService::update((int) $id, $userId, $data);

        if (!$success) {
            $errors = TeamTaskService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        $task = TeamTaskService::getById((int) $id);
        return $this->respondWithData($task);
    }

    /** DELETE /api/v2/team-tasks/{id} */
    public function deleteTask($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('team_task', 10, 60);

        $success = TeamTaskService::delete((int) $id, $userId);

        if (!$success) {
            $errors = TeamTaskService::getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->noContent();
    }

    /** GET /api/v2/groups/{id}/task-stats */
    public function taskStats($id): JsonResponse
    {
        $this->ensureFeature();
        $stats = TeamTaskService::getStats((int) $id);
        return $this->respondWithData($stats);
    }

    // ========================================
    // TEAM DOCUMENT ENDPOINTS — now native (except upload)
    // ========================================

    /** GET /api/v2/groups/{id}/documents */
    public function listDocuments($id): JsonResponse
    {
        $this->ensureFeature();
        $filters = [
            'limit' => $this->queryInt('per_page', 50, 1, 100),
        ];
        if ($this->query('cursor')) {
            $filters['cursor'] = $this->query('cursor');
        }

        $result = $this->teamDocumentService->getDocuments((int) $id, $filters);
        return $this->respondWithCollection($result['items'], $result['cursor'], $filters['limit'], $result['has_more']);
    }

    /**
     * POST /api/v2/groups/{id}/documents
     * Multipart upload with 'file' field.
     */
    public function uploadDocument($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('team_document', 10, 60);

        /** @var \Illuminate\Http\Request $request */
        $request = request();
        $file = $request->file('file');
        $title = $request->input('title');

        // Build the fileData array in the same shape TeamDocumentService::upload expects ($_FILES format)
        $fileData = [];
        if ($file) {
            $fileData = [
                'name' => $file->getClientOriginalName(),
                'type' => $file->getMimeType(),
                'tmp_name' => $file->getRealPath(),
                'error' => $file->getError(),
                'size' => $file->getSize(),
            ];
        }

        $docId = $this->teamDocumentService->upload((int) $id, $userId, $fileData, $title);

        if ($docId === null) {
            $errors = $this->teamDocumentService->getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->respondWithData(['id' => $docId], null, 201);
    }

    /** DELETE /api/v2/team-documents/{id} */
    public function deleteDocument($id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->getUserId();
        $this->rateLimit('team_document', 10, 60);

        $success = $this->teamDocumentService->delete((int) $id, $userId);

        if (!$success) {
            $errors = $this->teamDocumentService->getErrors();
            return $this->respondWithErrors($errors, $this->resolveErrorStatus($errors));
        }

        return $this->noContent();
    }

    // ========================================
    // Legacy vote endpoint (kept for backward compatibility)
    // ========================================

    /** POST /api/v2/ideation-challenges/{id}/vote */
    public function vote(int $id): JsonResponse
    {
        return $this->voteIdea($id);
    }

}
