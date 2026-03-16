<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Services\IdeationChallengeService;
use Illuminate\Http\JsonResponse;

/**
 * IdeationChallengesController — Community ideation challenges and idea submissions.
 */
class IdeationChallengesController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly IdeationChallengeService $challengeService,
    ) {}

    /** GET /api/v2/ideation-challenges */
    public function index(): JsonResponse
    {
        $tenantId = $this->getTenantId();
        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);

        $result = $this->challengeService->getAll($tenantId, $page, $perPage);

        return $this->respondWithPaginatedCollection(
            $result['items'],
            $result['total'],
            $page,
            $perPage
        );
    }

    /** GET /api/v2/ideation-challenges/{id} */
    public function show(int $id): JsonResponse
    {
        $tenantId = $this->getTenantId();

        $challenge = $this->challengeService->getById($id, $tenantId);

        if ($challenge === null) {
            return $this->respondWithError('NOT_FOUND', 'Challenge not found', null, 404);
        }

        return $this->respondWithData($challenge);
    }

    /** POST /api/v2/ideation-challenges */
    public function store(): JsonResponse
    {
        $userId = $this->requireAdmin();
        $tenantId = $this->getTenantId();
        $this->rateLimit('ideation_create', 5, 60);

        $data = $this->getAllInput();

        $challenge = $this->challengeService->create($userId, $tenantId, $data);

        return $this->respondWithData($challenge, null, 201);
    }

    /** POST /api/v2/ideation-challenges/{id}/ideas */
    public function submitIdea(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $this->rateLimit('ideation_submit', 10, 60);

        $data = $this->getAllInput();

        $idea = $this->challengeService->submitIdea($id, $userId, $tenantId, $data);

        if ($idea === null) {
            return $this->respondWithError('NOT_FOUND', 'Challenge not found or closed', null, 404);
        }

        return $this->respondWithData($idea, null, 201);
    }

    /** POST /api/v2/ideation-challenges/{id}/vote */
    public function vote(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $tenantId = $this->getTenantId();
        $this->rateLimit('ideation_vote', 30, 60);

        $ideaId = $this->requireInput('idea_id');

        $result = $this->challengeService->vote($id, (int) $ideaId, $userId, $tenantId);

        if ($result === null) {
            return $this->respondWithError('VOTE_FAILED', 'Unable to vote', null, 404);
        }

        return $this->respondWithData($result);
    }

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


    public function showIdea($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'showIdea', [$id]);
    }


    public function updateIdea($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateIdea', [$id]);
    }


    public function updateDraft($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateDraft', [$id]);
    }


    public function deleteIdea($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteIdea', [$id]);
    }


    public function voteIdea($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'voteIdea', [$id]);
    }


    public function updateIdeaStatus($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateIdeaStatus', [$id]);
    }


    public function comments($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'comments', [$id]);
    }


    public function addComment($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'addComment', [$id]);
    }


    public function deleteComment($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteComment', [$id]);
    }


    public function update($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'update', [$id]);
    }


    public function destroy($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'destroy', [$id]);
    }


    public function updateStatus($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateStatus', [$id]);
    }


    public function ideaDrafts($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'ideaDrafts', [$id]);
    }


    public function ideas($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'ideas', [$id]);
    }


    public function toggleFavorite($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'toggleFavorite', [$id]);
    }


    public function duplicate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'duplicate', [$id]);
    }


    public function convertToGroup($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'convertToGroup', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateCategory', [$id]);
    }


    public function deleteCategory($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteCategory', [$id]);
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
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteTag', [$id]);
    }


    public function listIdeaMedia($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listIdeaMedia', [$id]);
    }


    public function addIdeaMedia($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'addIdeaMedia', [$id]);
    }


    public function deleteIdeaMedia($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteIdeaMedia', [$id]);
    }


    public function listCampaigns(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listCampaigns');
    }


    public function showCampaign($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'showCampaign', [$id]);
    }


    public function createCampaign(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'createCampaign');
    }


    public function updateCampaign($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateCampaign', [$id]);
    }


    public function deleteCampaign($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteCampaign', [$id]);
    }


    public function linkChallengeToCampaign($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'linkChallengeToCampaign', [$id]);
    }


    public function unlinkChallengeFromCampaign($id, $challengeId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'unlinkChallengeFromCampaign', [$id, $challengeId]);
    }


    public function listTemplates(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listTemplates');
    }


    public function showTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'showTemplate', [$id]);
    }


    public function createTemplate(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'createTemplate');
    }


    public function updateTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateTemplate', [$id]);
    }


    public function deleteTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteTemplate', [$id]);
    }


    public function getTemplateData($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'getTemplateData', [$id]);
    }


    public function getOutcome($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'getOutcome', [$id]);
    }


    public function upsertOutcome($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'upsertOutcome', [$id]);
    }


    public function outcomesDashboard(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'outcomesDashboard');
    }


    public function getTeamLinks($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'getTeamLinks', [$id]);
    }


    public function listChatrooms($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listChatrooms', [$id]);
    }


    public function createChatroom($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'createChatroom', [$id]);
    }


    public function deleteChatroom($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteChatroom', [$id]);
    }


    public function chatroomMessages($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'chatroomMessages', [$id]);
    }


    public function postChatroomMessage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'postChatroomMessage', [$id]);
    }


    public function deleteChatroomMessage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteChatroomMessage', [$id]);
    }


    public function listTasks($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listTasks', [$id]);
    }


    public function createTask($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'createTask', [$id]);
    }


    public function showTask($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'showTask', [$id]);
    }


    public function updateTask($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateTask', [$id]);
    }


    public function deleteTask($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteTask', [$id]);
    }


    public function taskStats($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'taskStats', [$id]);
    }


    public function listDocuments($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listDocuments', [$id]);
    }


    public function uploadDocument($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'uploadDocument', [$id]);
    }


    public function deleteDocument($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteDocument', [$id]);
    }

}
