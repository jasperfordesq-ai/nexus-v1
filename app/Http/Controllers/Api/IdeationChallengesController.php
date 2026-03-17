<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;


/**
 * IdeationChallengesController — Community ideation challenges and idea submissions.
 */
class IdeationChallengesController extends BaseApiController
{
    protected bool $isV2Api = true;

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

    public function index(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'index');
    }

    public function show(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'show', func_get_args());
    }

    public function store(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'store');
    }

    public function submitIdea(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'submitIdea', func_get_args());
    }

    public function vote(int $id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'vote', func_get_args());
    }

    public function showIdea($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'showIdea', func_get_args());
    }

    public function updateIdea($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateIdea', func_get_args());
    }

    public function updateDraft($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateDraft', func_get_args());
    }

    public function deleteIdea($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteIdea', func_get_args());
    }

    public function voteIdea($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'voteIdea', func_get_args());
    }

    public function updateIdeaStatus($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateIdeaStatus', func_get_args());
    }

    public function comments($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'comments', func_get_args());
    }

    public function addComment($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'addComment', func_get_args());
    }

    public function deleteComment($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteComment', func_get_args());
    }

    public function update($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'update', func_get_args());
    }

    public function destroy($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'destroy', func_get_args());
    }

    public function updateStatus($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateStatus', func_get_args());
    }

    public function ideaDrafts($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'ideaDrafts', func_get_args());
    }

    public function ideas($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'ideas', func_get_args());
    }

    public function toggleFavorite($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'toggleFavorite', func_get_args());
    }

    public function duplicate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'duplicate', func_get_args());
    }

    public function convertToGroup($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'convertToGroup', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateCategory', func_get_args());
    }

    public function deleteCategory($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteCategory', func_get_args());
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
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteTag', func_get_args());
    }

    public function listIdeaMedia($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listIdeaMedia', func_get_args());
    }

    public function addIdeaMedia($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'addIdeaMedia', func_get_args());
    }

    public function deleteIdeaMedia($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteIdeaMedia', func_get_args());
    }

    public function listCampaigns(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listCampaigns');
    }

    public function showCampaign($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'showCampaign', func_get_args());
    }

    public function createCampaign(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'createCampaign');
    }

    public function updateCampaign($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateCampaign', func_get_args());
    }

    public function deleteCampaign($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteCampaign', func_get_args());
    }

    public function linkChallengeToCampaign($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'linkChallengeToCampaign', func_get_args());
    }

    public function unlinkChallengeFromCampaign($id, $challengeId): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'unlinkChallengeFromCampaign', func_get_args());
    }

    public function listTemplates(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listTemplates');
    }

    public function showTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'showTemplate', func_get_args());
    }

    public function createTemplate(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'createTemplate');
    }

    public function updateTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateTemplate', func_get_args());
    }

    public function deleteTemplate($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteTemplate', func_get_args());
    }

    public function getTemplateData($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'getTemplateData', func_get_args());
    }

    public function getOutcome($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'getOutcome', func_get_args());
    }

    public function upsertOutcome($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'upsertOutcome', func_get_args());
    }

    public function outcomesDashboard(): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'outcomesDashboard');
    }

    public function getTeamLinks($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'getTeamLinks', func_get_args());
    }

    public function listChatrooms($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listChatrooms', func_get_args());
    }

    public function createChatroom($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'createChatroom', func_get_args());
    }

    public function deleteChatroom($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteChatroom', func_get_args());
    }

    public function chatroomMessages($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'chatroomMessages', func_get_args());
    }

    public function postChatroomMessage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'postChatroomMessage', func_get_args());
    }

    public function deleteChatroomMessage($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteChatroomMessage', func_get_args());
    }

    public function listTasks($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listTasks', func_get_args());
    }

    public function createTask($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'createTask', func_get_args());
    }

    public function showTask($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'showTask', func_get_args());
    }

    public function updateTask($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'updateTask', func_get_args());
    }

    public function deleteTask($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteTask', func_get_args());
    }

    public function taskStats($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'taskStats', func_get_args());
    }

    public function listDocuments($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'listDocuments', func_get_args());
    }

    public function uploadDocument($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'uploadDocument', func_get_args());
    }

    public function deleteDocument($id): JsonResponse
    {
        return $this->delegate(\Nexus\Controllers\Api\IdeationChallengesApiController::class, 'deleteDocument', func_get_args());
    }
}
