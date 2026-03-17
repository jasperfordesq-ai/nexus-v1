<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\IdeationChallengeService;
use Nexus\Core\TenantContext;

/**
 * IdeationChallengesController — Community ideation challenges and idea submissions.
 *
 * Core CRUD uses Eloquent via IdeationChallengeService; advanced features delegate to legacy.
 */
class IdeationChallengesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly IdeationChallengeService $challengeService,
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

    /** GET /api/v2/ideation-challenges — list challenges with filters + cursor pagination */
    public function index(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('ideation_list', 60, 60);

        $userId = $this->getOptionalUserId();

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
    // DELEGATION — advanced features via legacy
    // ========================================

    /**
     * Delegate to legacy controller via output buffering.
     */
    private function delegate(string $legacyClass, string $method, array $params = []): JsonResponse
    {
        $controller = new $legacyClass();
        ob_start();
        $controller->$method(...$params);
        $output = ob_get_clean();
        $status = http_response_code();
        return response()->json(json_decode($output, true) ?: $output, $status ?: 200);
    }

    public function vote(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'vote', [$id]);
    }

    public function showIdea($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'showIdea', [(int) $id]);
    }

    public function updateIdea($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateIdea', [(int) $id]);
    }

    public function updateDraft($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateDraft', [(int) $id]);
    }

    public function deleteIdea($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteIdea', [(int) $id]);
    }

    public function voteIdea($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'voteIdea', [(int) $id]);
    }

    public function updateIdeaStatus($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateIdeaStatus', [(int) $id]);
    }

    public function comments($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'comments', [(int) $id]);
    }

    public function addComment($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'addComment', [(int) $id]);
    }

    public function deleteComment($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteComment', [(int) $id]);
    }

    public function update($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'update', [(int) $id]);
    }

    public function destroy($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'destroy', [(int) $id]);
    }

    public function updateStatus($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateStatus', [(int) $id]);
    }

    public function ideaDrafts($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'ideaDrafts', [(int) $id]);
    }

    public function toggleFavorite($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'toggleFavorite', [(int) $id]);
    }

    public function duplicate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'duplicate', [(int) $id]);
    }

    public function convertToGroup($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'convertToGroup', [(int) $id]);
    }

    public function listCategories(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listCategories');
    }

    public function createCategory(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'createCategory');
    }

    public function updateCategory($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateCategory', [(int) $id]);
    }

    public function deleteCategory($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteCategory', [(int) $id]);
    }

    public function popularTags(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'popularTags');
    }

    public function listTags(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listTags');
    }

    public function createTag(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'createTag');
    }

    public function deleteTag($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteTag', [(int) $id]);
    }

    public function listIdeaMedia($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listIdeaMedia', [(int) $id]);
    }

    public function addIdeaMedia($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'addIdeaMedia', [(int) $id]);
    }

    public function deleteIdeaMedia($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteIdeaMedia', [(int) $id]);
    }

    public function listCampaigns(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listCampaigns');
    }

    public function showCampaign($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'showCampaign', [(int) $id]);
    }

    public function createCampaign(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'createCampaign');
    }

    public function updateCampaign($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateCampaign', [(int) $id]);
    }

    public function deleteCampaign($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteCampaign', [(int) $id]);
    }

    public function linkChallengeToCampaign($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'linkChallengeToCampaign', [(int) $id]);
    }

    public function unlinkChallengeFromCampaign($id, $challengeId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'unlinkChallengeFromCampaign', [(int) $id, (int) $challengeId]);
    }

    public function listTemplates(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listTemplates');
    }

    public function showTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'showTemplate', [(int) $id]);
    }

    public function createTemplate(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'createTemplate');
    }

    public function updateTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateTemplate', [(int) $id]);
    }

    public function deleteTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteTemplate', [(int) $id]);
    }

    public function getTemplateData($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'getTemplateData', [(int) $id]);
    }

    public function getOutcome($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'getOutcome', [(int) $id]);
    }

    public function upsertOutcome($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'upsertOutcome', [(int) $id]);
    }

    public function outcomesDashboard(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'outcomesDashboard');
    }

    public function getTeamLinks($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'getTeamLinks', [(int) $id]);
    }

    public function listChatrooms($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listChatrooms', [(int) $id]);
    }

    public function createChatroom($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'createChatroom', [(int) $id]);
    }

    public function deleteChatroom($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteChatroom', [(int) $id]);
    }

    public function chatroomMessages($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'chatroomMessages', [(int) $id]);
    }

    public function postChatroomMessage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'postChatroomMessage', [(int) $id]);
    }

    public function deleteChatroomMessage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteChatroomMessage', [(int) $id]);
    }

    public function listTasks($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listTasks', [(int) $id]);
    }

    public function createTask($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'createTask', [(int) $id]);
    }

    public function showTask($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'showTask', [(int) $id]);
    }

    public function updateTask($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateTask', [(int) $id]);
    }

    public function deleteTask($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteTask', [(int) $id]);
    }

    public function taskStats($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'taskStats', [(int) $id]);
    }

    public function listDocuments($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listDocuments', [(int) $id]);
    }

    public function uploadDocument($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'uploadDocument', [(int) $id]);
    }

    public function deleteDocument($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteDocument', [(int) $id]);
    }
}
