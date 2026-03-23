<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use App\Services\StoryService;
use Illuminate\Support\Facades\Log;

/**
 * StoryController — API endpoints for the Stories feature (24-hour disappearing content).
 *
 * Handles story CRUD, viewing, reactions, polls, and highlights.
 * All endpoints require authentication (auth:sanctum middleware).
 */
class StoryController extends BaseApiController
{
    protected bool $isV2Api = true;

    public function __construct(
        private readonly StoryService $storyService,
    ) {}

    /**
     * GET /api/v2/stories — Get story feed (story bar data)
     */
    public function index(): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            $stories = $this->storyService->getFeedStories($userId);
            return $this->respondWithData($stories);
        } catch (\Throwable $e) {
            Log::error('StoryController::index failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('STORIES_FETCH_FAILED', 'Failed to load stories', null, 500);
        }
    }

    /**
     * GET /api/v2/stories/user/{userId} — Get specific user's stories
     */
    public function userStories(int $userId): JsonResponse
    {
        $viewerId = $this->requireAuth();

        try {
            $stories = $this->storyService->getUserStories($userId, $viewerId);
            return $this->respondWithData($stories);
        } catch (\Throwable $e) {
            Log::error('StoryController::userStories failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('STORIES_FETCH_FAILED', 'Failed to load user stories', null, 500);
        }
    }

    /**
     * POST /api/v2/stories — Create a story
     *
     * Accepts multipart/form-data (for image stories) or JSON (for text/poll stories).
     */
    public function store(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('stories.create', 10, 60);

        $request = request();

        $mediaType = $request->input('media_type', 'image');

        $data = [
            'media_type' => $mediaType,
            'text_content' => $request->input('text_content'),
            'background_color' => $request->input('background_color'),
            'background_gradient' => $request->input('background_gradient'),
            'duration' => $request->input('duration', 5),
        ];

        // Handle text_style (may come as JSON string or array)
        $textStyle = $request->input('text_style');
        if (is_string($textStyle)) {
            $decoded = json_decode($textStyle, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data['text_style'] = $decoded;
            }
        } elseif (is_array($textStyle)) {
            $data['text_style'] = $textStyle;
        }

        // Handle poll data
        if ($mediaType === 'poll') {
            $data['poll_question'] = $request->input('poll_question');
            $pollOptions = $request->input('poll_options');
            if (is_string($pollOptions)) {
                $data['poll_options'] = json_decode($pollOptions, true);
            } elseif (is_array($pollOptions)) {
                $data['poll_options'] = $pollOptions;
            }
        }

        // Handle image upload
        if ($mediaType === 'image' && $request->hasFile('media')) {
            $file = $request->file('media');

            // Validate file
            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($file->getSize() > $maxSize) {
                return $this->respondWithError('FILE_TOO_LARGE', 'Image must be less than 10MB', 'media');
            }

            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return $this->respondWithError('INVALID_FILE_TYPE', 'Only JPEG, PNG, GIF, and WebP images are allowed', 'media');
            }

            try {
                $tenantId = $this->getTenantId();
                $data['media_url'] = $this->storyService->uploadMedia($file, $tenantId, $userId);
            } catch (\Throwable $e) {
                Log::error('Story image upload failed', ['error' => $e->getMessage()]);
                return $this->respondWithError('UPLOAD_FAILED', 'Failed to upload image', 'media', 500);
            }
        }

        // Validate: image stories must have a media file, text stories must have content
        if ($mediaType === 'image' && empty($data['media_url'])) {
            return $this->respondWithError('MISSING_MEDIA', 'Image stories require a media file', 'media');
        }
        if ($mediaType === 'text' && empty($data['text_content'])) {
            return $this->respondWithError('MISSING_CONTENT', 'Text stories require content', 'text_content');
        }

        try {
            $story = $this->storyService->create($userId, $data);
            return $this->respondWithData($story, null, 201);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('STORY_CREATE_FAILED', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('StoryController::store failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('STORY_CREATE_FAILED', 'Failed to create story', null, 500);
        }
    }

    /**
     * POST /api/v2/stories/{id}/view — Mark story as viewed
     */
    public function view(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            $this->storyService->viewStory($id, $userId);
            return $this->respondWithData(['viewed' => true]);
        } catch (\Throwable $e) {
            Log::error('StoryController::view failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('VIEW_FAILED', 'Failed to record view', null, 500);
        }
    }

    /**
     * GET /api/v2/stories/{id}/viewers — Get viewers list (owner only)
     */
    public function viewers(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            $viewers = $this->storyService->getViewers($id, $userId);
            return $this->respondWithData($viewers);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('VIEWERS_FETCH_FAILED', $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('StoryController::viewers failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('VIEWERS_FETCH_FAILED', 'Failed to load viewers', null, 500);
        }
    }

    /**
     * POST /api/v2/stories/{id}/react — React to a story
     */
    public function react(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('stories.react', 30, 60);

        $reactionType = request()->input('reaction_type');
        if (empty($reactionType)) {
            return $this->respondWithError('MISSING_REACTION', 'Reaction type is required', 'reaction_type');
        }

        try {
            $this->storyService->reactToStory($id, $userId, $reactionType);
            return $this->respondWithData(['reacted' => true]);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('REACT_FAILED', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('StoryController::react failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('REACT_FAILED', 'Failed to add reaction', null, 500);
        }
    }

    /**
     * DELETE /api/v2/stories/{id} — Delete own story
     */
    public function destroy(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            $this->storyService->deleteStory($id, $userId);
            return $this->respondWithData(['deleted' => true]);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('DELETE_FAILED', $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('StoryController::destroy failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('DELETE_FAILED', 'Failed to delete story', null, 500);
        }
    }

    /**
     * POST /api/v2/stories/{id}/poll/vote — Vote on poll story
     */
    public function pollVote(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('stories.poll_vote', 30, 60);

        $optionIndex = request()->input('option_index');
        if ($optionIndex === null) {
            return $this->respondWithError('MISSING_OPTION', 'Option index is required', 'option_index');
        }

        try {
            $results = $this->storyService->votePoll($id, $userId, (int) $optionIndex);
            return $this->respondWithData($results);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('VOTE_FAILED', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('StoryController::pollVote failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('VOTE_FAILED', 'Failed to submit vote', null, 500);
        }
    }

    /**
     * GET /api/v2/stories/highlights/{userId} — Get user's highlights
     */
    public function highlights(int $userId): JsonResponse
    {
        $this->requireAuth();

        try {
            $highlights = $this->storyService->getHighlights($userId);
            return $this->respondWithData($highlights);
        } catch (\Throwable $e) {
            Log::error('StoryController::highlights failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('HIGHLIGHTS_FETCH_FAILED', 'Failed to load highlights', null, 500);
        }
    }

    /**
     * GET /api/v2/stories/highlights/{id}/stories — Get stories in a highlight
     */
    public function highlightStories(int $id): JsonResponse
    {
        $viewerId = $this->requireAuth();

        try {
            $stories = $this->storyService->getHighlightStories($id, $viewerId);
            return $this->respondWithData($stories);
        } catch (\Throwable $e) {
            Log::error('StoryController::highlightStories failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('HIGHLIGHT_STORIES_FAILED', 'Failed to load highlight stories', null, 500);
        }
    }

    /**
     * POST /api/v2/stories/highlights — Create highlight
     */
    public function createHighlight(): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('stories.highlights.create', 10, 60);

        $title = request()->input('title');
        if (empty($title)) {
            return $this->respondWithError('MISSING_TITLE', 'Highlight title is required', 'title');
        }

        $storyIds = request()->input('story_ids', []);
        if (is_string($storyIds)) {
            $storyIds = json_decode($storyIds, true) ?? [];
        }

        try {
            $highlight = $this->storyService->createHighlight($userId, $title, $storyIds);
            return $this->respondWithData($highlight, null, 201);
        } catch (\Throwable $e) {
            Log::error('StoryController::createHighlight failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('HIGHLIGHT_CREATE_FAILED', 'Failed to create highlight', null, 500);
        }
    }

    /**
     * POST /api/v2/stories/highlights/{id}/items — Add story to highlight
     */
    public function addHighlightItem(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $storyId = request()->input('story_id');
        if (empty($storyId)) {
            return $this->respondWithError('MISSING_STORY_ID', 'Story ID is required', 'story_id');
        }

        try {
            $this->storyService->addToHighlight($id, (int) $storyId, $userId);
            return $this->respondWithData(['added' => true]);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('ADD_ITEM_FAILED', $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('StoryController::addHighlightItem failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('ADD_ITEM_FAILED', 'Failed to add story to highlight', null, 500);
        }
    }

    /**
     * DELETE /api/v2/stories/highlights/{id} — Delete highlight
     */
    public function deleteHighlight(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            $this->storyService->deleteHighlight($id, $userId);
            return $this->respondWithData(['deleted' => true]);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('DELETE_FAILED', $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('StoryController::deleteHighlight failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('DELETE_FAILED', 'Failed to delete highlight', null, 500);
        }
    }
}
