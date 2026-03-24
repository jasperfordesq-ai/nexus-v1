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

        // Validate audience
        $audience = $request->input('audience', 'everyone');
        if (!in_array($audience, ['everyone', 'connections', 'close_friends'])) {
            $audience = 'everyone';
        }

        $data = [
            'media_type' => $mediaType,
            'text_content' => $request->input('text_content'),
            'background_color' => $request->input('background_color'),
            'background_gradient' => $request->input('background_gradient'),
            'duration' => $request->input('duration', 5),
            'audience' => $audience,
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

        // Handle video upload
        if ($mediaType === 'video' && $request->hasFile('media')) {
            $file = $request->file('media');

            $maxSize = 50 * 1024 * 1024; // 50MB
            if ($file->getSize() > $maxSize) {
                return $this->respondWithError('FILE_TOO_LARGE', 'Video must be less than 50MB', 'media');
            }

            $allowedMimes = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
            if (!in_array($file->getMimeType(), $allowedMimes)) {
                return $this->respondWithError('INVALID_FILE_TYPE', 'Only MP4, WebM, OGG, and MOV videos are allowed', 'media');
            }

            try {
                $tenantId = $this->getTenantId();
                $data['media_url'] = $this->storyService->uploadMedia($file, $tenantId, $userId);
                $data['video_duration'] = $request->input('video_duration');
            } catch (\Throwable $e) {
                Log::error('Story video upload failed', ['error' => $e->getMessage()]);
                return $this->respondWithError('UPLOAD_FAILED', 'Failed to upload video', 'media', 500);
            }
        }

        // Validate: image/video stories must have a media file, text stories must have content
        if (in_array($mediaType, ['image', 'video']) && empty($data['media_url'])) {
            return $this->respondWithError('MISSING_MEDIA', ucfirst($mediaType) . ' stories require a media file', 'media');
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
     * POST /api/v2/stories/{id}/reply — Reply to a story via DM
     */
    public function reply(int $id): JsonResponse
    {
        $userId = $this->requireAuth();
        $this->rateLimit('stories.reply', 30, 60);

        $body = trim(request()->input('body', ''));
        if (empty($body)) {
            return $this->respondWithError('MISSING_BODY', 'Reply message is required', 'body');
        }

        try {
            $result = $this->storyService->replyToStory($id, $userId, $body);
            return $this->respondWithData($result, null, 201);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('REPLY_FAILED', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('StoryController::reply failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('REPLY_FAILED', 'Failed to send reply', null, 500);
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

    /**
     * GET /api/v2/stories/archive — Get own archived stories
     */
    public function archive(): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            $limit = min((int) request()->input('limit', 50), 100);
            $offset = max((int) request()->input('offset', 0), 0);
            $stories = $this->storyService->getArchivedStories($userId, $limit, $offset);
            return $this->respondWithData($stories);
        } catch (\Throwable $e) {
            Log::error('StoryController::archive failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('ARCHIVE_FETCH_FAILED', 'Failed to load archived stories', null, 500);
        }
    }

    /**
     * GET /api/v2/stories/close-friends — Get own close friends list
     */
    public function closeFriends(): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            $friends = $this->storyService->getCloseFriends($userId);
            return $this->respondWithData($friends);
        } catch (\Throwable $e) {
            Log::error('StoryController::closeFriends failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('CLOSE_FRIENDS_FAILED', 'Failed to load close friends', null, 500);
        }
    }

    /**
     * POST /api/v2/stories/close-friends — Add close friend
     */
    public function addCloseFriend(): JsonResponse
    {
        $userId = $this->requireAuth();

        $friendId = request()->input('friend_id');
        if (empty($friendId)) {
            return $this->respondWithError('MISSING_FRIEND_ID', 'Friend ID is required', 'friend_id');
        }

        try {
            $this->storyService->addCloseFriend($userId, (int) $friendId);
            return $this->respondWithData(['added' => true]);
        } catch (\Throwable $e) {
            Log::error('StoryController::addCloseFriend failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('ADD_FRIEND_FAILED', 'Failed to add close friend', null, 500);
        }
    }

    /**
     * DELETE /api/v2/stories/close-friends/{friendId} — Remove close friend
     */
    public function removeCloseFriend(int $friendId): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            $this->storyService->removeCloseFriend($userId, $friendId);
            return $this->respondWithData(['removed' => true]);
        } catch (\Throwable $e) {
            Log::error('StoryController::removeCloseFriend failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('REMOVE_FRIEND_FAILED', 'Failed to remove close friend', null, 500);
        }
    }

    /**
     * POST /api/v2/stories/{id}/analytics — Track analytics event
     */
    public function trackAnalytics(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $eventType = request()->input('event_type');
        if (empty($eventType)) {
            return $this->respondWithError('MISSING_EVENT', 'Event type is required', 'event_type');
        }

        $watchDuration = request()->input('watch_duration_ms');

        try {
            $this->storyService->trackAnalytics($id, $userId, $eventType, $watchDuration ? (int) $watchDuration : null);
            return $this->respondWithData(['tracked' => true]);
        } catch (\Throwable $e) {
            // Silently fail analytics — don't break UX
            return $this->respondWithData(['tracked' => false]);
        }
    }

    /**
     * GET /api/v2/stories/{id}/analytics — Get story analytics (owner only)
     */
    public function getAnalytics(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            $analytics = $this->storyService->getStoryAnalytics($id, $userId);
            return $this->respondWithData($analytics);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('ANALYTICS_FAILED', $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('StoryController::getAnalytics failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('ANALYTICS_FAILED', 'Failed to load analytics', null, 500);
        }
    }

    /**
     * PUT /api/v2/stories/highlights/{id} — Update highlight title
     */
    public function updateHighlight(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $title = request()->input('title');
        if (empty($title)) {
            return $this->respondWithError('MISSING_TITLE', 'Highlight title is required', 'title');
        }

        try {
            $highlight = $this->storyService->updateHighlight($id, $userId, $title);
            return $this->respondWithData($highlight);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('UPDATE_FAILED', $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('StoryController::updateHighlight failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('UPDATE_FAILED', 'Failed to update highlight', null, 500);
        }
    }

    /**
     * DELETE /api/v2/stories/highlights/{id}/items/{storyId} — Remove story from highlight
     */
    public function removeHighlightItem(int $id, int $storyId): JsonResponse
    {
        $userId = $this->requireAuth();

        try {
            $this->storyService->removeFromHighlight($id, $storyId, $userId);
            return $this->respondWithData(['removed' => true]);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('REMOVE_FAILED', $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('StoryController::removeHighlightItem failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('REMOVE_FAILED', 'Failed to remove story from highlight', null, 500);
        }
    }

    /**
     * POST /api/v2/stories/{id}/stickers — Save stickers for a story
     */
    public function saveStickers(int $id): JsonResponse
    {
        $userId = $this->requireAuth();

        $stickers = request()->input('stickers', []);
        if (!is_array($stickers)) {
            return $this->respondWithError('INVALID_STICKERS', 'Stickers must be an array', 'stickers');
        }

        try {
            $this->storyService->saveStickers($id, $userId, $stickers);
            return $this->respondWithData(['saved' => true]);
        } catch (\RuntimeException $e) {
            return $this->respondWithError('SAVE_FAILED', $e->getMessage(), null, 403);
        } catch (\Throwable $e) {
            Log::error('StoryController::saveStickers failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('SAVE_FAILED', 'Failed to save stickers', null, 500);
        }
    }

    /**
     * PUT /api/v2/stories/highlights/reorder — Reorder highlights
     */
    public function reorderHighlights(): JsonResponse
    {
        $userId = $this->requireAuth();

        $order = request()->input('order', []);
        if (empty($order) || !is_array($order)) {
            return $this->respondWithError('MISSING_ORDER', 'Order array is required', 'order');
        }

        try {
            $this->storyService->reorderHighlights($userId, $order);
            return $this->respondWithData(['reordered' => true]);
        } catch (\Throwable $e) {
            Log::error('StoryController::reorderHighlights failed', ['error' => $e->getMessage()]);
            return $this->respondWithError('REORDER_FAILED', 'Failed to reorder highlights', null, 500);
        }
    }
}
