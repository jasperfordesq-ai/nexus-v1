<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * StoryService — Manages 24-hour disappearing stories (Instagram-style).
 *
 * All queries are tenant-scoped via TenantContext::getId().
 * Uses raw DB queries for performance on high-volume story operations.
 */
class StoryService
{
    /** Maximum active stories per user */
    private const MAX_ACTIVE_STORIES = 30;

    /** Story lifetime in hours */
    private const STORY_LIFETIME_HOURS = 24;

    /** Days after expiry before media files are purged */
    private const MEDIA_RETENTION_DAYS = 30;

    /**
     * Create a new story.
     *
     * @param int $userId
     * @param array $data {media_type, media_url?, thumbnail_url?, text_content?, text_style?, background_color?, background_gradient?, duration?, poll_question?, poll_options?}
     * @return array The created story
     *
     * @throws \RuntimeException if max active stories exceeded
     */
    public function create(int $userId, array $data): array
    {
        $tenantId = TenantContext::getId();

        // Check active story limit
        $activeCount = DB::selectOne(
            'SELECT COUNT(*) as cnt FROM stories WHERE tenant_id = ? AND user_id = ? AND is_active = 1 AND expires_at > NOW()',
            [$tenantId, $userId]
        );

        if (($activeCount->cnt ?? 0) >= self::MAX_ACTIVE_STORIES) {
            throw new \RuntimeException('Maximum active stories limit reached (' . self::MAX_ACTIVE_STORIES . ')');
        }

        $mediaType = $data['media_type'] ?? 'image';
        $duration = min(max((int) ($data['duration'] ?? 5), 3), 30);

        $expiresAt = now()->addHours(self::STORY_LIFETIME_HOURS)->format('Y-m-d H:i:s');

        // Validate poll data
        $pollOptions = null;
        if ($mediaType === 'poll') {
            if (empty($data['poll_question'])) {
                throw new \RuntimeException('Poll question is required for poll stories');
            }
            $options = $data['poll_options'] ?? [];
            if (!is_array($options) || count($options) < 2 || count($options) > 4) {
                throw new \RuntimeException('Poll stories require 2 to 4 options');
            }
            $pollOptions = json_encode(array_values($options));
        }

        $textStyle = null;
        if (!empty($data['text_style']) && is_array($data['text_style'])) {
            $textStyle = json_encode($data['text_style']);
        }

        DB::insert(
            'INSERT INTO stories (tenant_id, user_id, media_type, media_url, thumbnail_url, text_content, text_style, background_color, background_gradient, duration, poll_question, poll_options, expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $tenantId,
                $userId,
                $mediaType,
                $data['media_url'] ?? null,
                $data['thumbnail_url'] ?? null,
                $data['text_content'] ?? null,
                $textStyle,
                $data['background_color'] ?? null,
                $data['background_gradient'] ?? null,
                $duration,
                $data['poll_question'] ?? null,
                $pollOptions,
                $expiresAt,
            ]
        );

        $storyId = DB::getPdo()->lastInsertId();

        return $this->getStoryById((int) $storyId);
    }

    /**
     * Get stories for the story bar (feed view).
     *
     * Returns users who have active stories, grouped by user.
     * Current user's stories first, then connections, then community.
     * For each user: avatar, name, whether current user has viewed ALL their stories.
     *
     * @param int $userId Current authenticated user
     * @return array
     */
    public function getFeedStories(int $userId): array
    {
        $tenantId = TenantContext::getId();

        // Get all users with active stories in this tenant
        $rows = DB::select(
            'SELECT
                s.user_id,
                u.first_name,
                u.last_name,
                u.avatar_url,
                COUNT(s.id) as story_count,
                MAX(s.created_at) as latest_story_at,
                SUM(CASE WHEN sv.id IS NULL THEN 1 ELSE 0 END) as unseen_count
             FROM stories s
             JOIN users u ON u.id = s.user_id
             LEFT JOIN story_views sv ON sv.story_id = s.id AND sv.viewer_id = ?
             WHERE s.tenant_id = ?
               AND s.is_active = 1
               AND s.expires_at > NOW()
             GROUP BY s.user_id, u.first_name, u.last_name, u.avatar_url
             ORDER BY
                (s.user_id = ?) DESC,
                unseen_count DESC,
                latest_story_at DESC',
            [$userId, $tenantId, $userId]
        );

        // Check connections for sorting priority
        $connectionUserIds = DB::select(
            'SELECT CASE WHEN user_id = ? THEN connected_user_id ELSE user_id END as friend_id
             FROM connections
             WHERE (user_id = ? OR connected_user_id = ?)
               AND status = ?
               AND tenant_id = ?',
            [$userId, $userId, $userId, 'accepted', $tenantId]
        );
        $connectedIds = array_map(fn($r) => (int) $r->friend_id, $connectionUserIds);

        $result = [];
        foreach ($rows as $row) {
            $isOwn = (int) $row->user_id === $userId;
            $isConnected = in_array((int) $row->user_id, $connectedIds);

            $result[] = [
                'user_id' => (int) $row->user_id,
                'name' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                'first_name' => $row->first_name ?? '',
                'avatar_url' => $row->avatar_url,
                'story_count' => (int) $row->story_count,
                'has_unseen' => (int) $row->unseen_count > 0,
                'is_own' => $isOwn,
                'is_connected' => $isConnected,
                'latest_at' => $row->latest_story_at,
            ];
        }

        // Sort: own first, then unseen connections, then unseen others, then seen connections, then seen others
        usort($result, function ($a, $b) {
            if ($a['is_own'] !== $b['is_own']) return $a['is_own'] ? -1 : 1;
            if ($a['has_unseen'] !== $b['has_unseen']) return $a['has_unseen'] ? -1 : 1;
            if ($a['is_connected'] !== $b['is_connected']) return $a['is_connected'] ? -1 : 1;
            return strcmp($b['latest_at'] ?? '', $a['latest_at'] ?? '');
        });

        return $result;
    }

    /**
     * Get all active stories for a specific user.
     *
     * @param int $userId The user whose stories to retrieve
     * @param int|null $viewerId The viewer (for seen/unseen status)
     * @return array
     */
    public function getUserStories(int $userId, ?int $viewerId = null): array
    {
        $tenantId = TenantContext::getId();

        $stories = DB::select(
            'SELECT s.*,
                    u.first_name, u.last_name, u.avatar_url,
                    ' . ($viewerId ? 'CASE WHEN sv.id IS NOT NULL THEN 1 ELSE 0 END as is_viewed' : '0 as is_viewed') . '
             FROM stories s
             JOIN users u ON u.id = s.user_id
             ' . ($viewerId ? 'LEFT JOIN story_views sv ON sv.story_id = s.id AND sv.viewer_id = ' . (int) $viewerId : '') . '
             WHERE s.tenant_id = ?
               AND s.user_id = ?
               AND s.is_active = 1
               AND s.expires_at > NOW()
             ORDER BY s.created_at ASC',
            [$tenantId, $userId]
        );

        return array_map(fn($s) => $this->formatStory($s), $stories);
    }

    /**
     * Mark a story as viewed by a user.
     *
     * @param int $storyId
     * @param int $viewerId
     */
    public function viewStory(int $storyId, int $viewerId): void
    {
        $tenantId = TenantContext::getId();

        // Verify story belongs to this tenant and is active
        $story = DB::selectOne(
            'SELECT id, user_id FROM stories WHERE id = ? AND tenant_id = ? AND is_active = 1 AND expires_at > NOW()',
            [$storyId, $tenantId]
        );

        if (!$story) {
            return; // Story not found or expired — silently ignore
        }

        // Don't track self-views
        if ((int) $story->user_id === $viewerId) {
            return;
        }

        // Insert view (ignore duplicate)
        DB::statement(
            'INSERT IGNORE INTO story_views (story_id, viewer_id, viewed_at) VALUES (?, ?, NOW())',
            [$storyId, $viewerId]
        );

        // Increment view count
        DB::update(
            'UPDATE stories SET view_count = view_count + 1 WHERE id = ? AND tenant_id = ?',
            [$storyId, $tenantId]
        );
    }

    /**
     * Get viewers list for a story (only available to story owner).
     *
     * @param int $storyId
     * @param int $ownerId The requesting user (must be story owner)
     * @return array
     *
     * @throws \RuntimeException if not story owner
     */
    public function getViewers(int $storyId, int $ownerId): array
    {
        $tenantId = TenantContext::getId();

        $story = DB::selectOne(
            'SELECT id, user_id FROM stories WHERE id = ? AND tenant_id = ?',
            [$storyId, $tenantId]
        );

        if (!$story) {
            throw new \RuntimeException('Story not found');
        }

        if ((int) $story->user_id !== $ownerId) {
            throw new \RuntimeException('Only the story owner can view the viewers list');
        }

        $viewers = DB::select(
            'SELECT u.id, u.first_name, u.last_name, u.avatar_url, sv.viewed_at
             FROM story_views sv
             JOIN users u ON u.id = sv.viewer_id
             WHERE sv.story_id = ?
             ORDER BY sv.viewed_at DESC',
            [$storyId]
        );

        return array_map(fn($v) => [
            'id' => (int) $v->id,
            'name' => trim(($v->first_name ?? '') . ' ' . ($v->last_name ?? '')),
            'avatar_url' => $v->avatar_url,
            'viewed_at' => $v->viewed_at,
        ], $viewers);
    }

    /**
     * Add a reaction to a story.
     *
     * @param int $storyId
     * @param int $userId
     * @param string $reactionType e.g. 'heart', 'laugh', 'wow', 'fire', 'clap', 'sad'
     */
    public function reactToStory(int $storyId, int $userId, string $reactionType): void
    {
        $tenantId = TenantContext::getId();

        // Verify story belongs to this tenant
        $story = DB::selectOne(
            'SELECT id FROM stories WHERE id = ? AND tenant_id = ? AND is_active = 1 AND expires_at > NOW()',
            [$storyId, $tenantId]
        );

        if (!$story) {
            throw new \RuntimeException('Story not found');
        }

        $allowedReactions = ['heart', 'laugh', 'wow', 'fire', 'clap', 'sad'];
        if (!in_array($reactionType, $allowedReactions)) {
            throw new \RuntimeException('Invalid reaction type');
        }

        DB::insert(
            'INSERT INTO story_reactions (story_id, user_id, reaction_type, created_at) VALUES (?, ?, ?, NOW())',
            [$storyId, $userId, $reactionType]
        );
    }

    /**
     * Delete a story (soft delete by setting is_active = 0).
     *
     * @param int $storyId
     * @param int $userId Must be the story owner
     *
     * @throws \RuntimeException if not owner
     */
    public function deleteStory(int $storyId, int $userId): void
    {
        $tenantId = TenantContext::getId();

        $affected = DB::update(
            'UPDATE stories SET is_active = 0 WHERE id = ? AND user_id = ? AND tenant_id = ?',
            [$storyId, $userId, $tenantId]
        );

        if ($affected === 0) {
            throw new \RuntimeException('Story not found or you are not the owner');
        }
    }

    /**
     * Vote on a poll story.
     *
     * @param int $storyId
     * @param int $userId
     * @param int $optionIndex Zero-based index of the selected option
     * @return array Updated poll results
     *
     * @throws \RuntimeException
     */
    public function votePoll(int $storyId, int $userId, int $optionIndex): array
    {
        $tenantId = TenantContext::getId();

        $story = DB::selectOne(
            'SELECT id, media_type, poll_options FROM stories WHERE id = ? AND tenant_id = ? AND is_active = 1 AND expires_at > NOW()',
            [$storyId, $tenantId]
        );

        if (!$story) {
            throw new \RuntimeException('Story not found');
        }

        if ($story->media_type !== 'poll') {
            throw new \RuntimeException('This story is not a poll');
        }

        $options = json_decode($story->poll_options, true);
        if (!is_array($options) || $optionIndex < 0 || $optionIndex >= count($options)) {
            throw new \RuntimeException('Invalid option index');
        }

        // Check if already voted
        $existing = DB::selectOne(
            'SELECT id FROM story_poll_votes WHERE story_id = ? AND user_id = ?',
            [$storyId, $userId]
        );

        if ($existing) {
            throw new \RuntimeException('You have already voted on this poll');
        }

        DB::insert(
            'INSERT INTO story_poll_votes (story_id, user_id, option_index, created_at) VALUES (?, ?, ?, NOW())',
            [$storyId, $userId, $optionIndex]
        );

        return $this->getPollResults($storyId);
    }

    /**
     * Get poll results for a story.
     *
     * @param int $storyId
     * @return array
     */
    public function getPollResults(int $storyId): array
    {
        $votes = DB::select(
            'SELECT option_index, COUNT(*) as vote_count
             FROM story_poll_votes
             WHERE story_id = ?
             GROUP BY option_index',
            [$storyId]
        );

        $results = [];
        $totalVotes = 0;
        foreach ($votes as $v) {
            $results[(int) $v->option_index] = (int) $v->vote_count;
            $totalVotes += (int) $v->vote_count;
        }

        return [
            'votes' => $results,
            'total_votes' => $totalVotes,
        ];
    }

    /**
     * Create a story highlight.
     *
     * @param int $userId
     * @param string $title
     * @param array $storyIds
     * @return array
     */
    public function createHighlight(int $userId, string $title, array $storyIds = []): array
    {
        $tenantId = TenantContext::getId();

        // Get next display order
        $maxOrder = DB::selectOne(
            'SELECT MAX(display_order) as max_order FROM story_highlights WHERE tenant_id = ? AND user_id = ?',
            [$tenantId, $userId]
        );
        $order = ($maxOrder->max_order ?? 0) + 1;

        // Determine cover URL from first story
        $coverUrl = null;
        if (!empty($storyIds)) {
            $firstStory = DB::selectOne(
                'SELECT media_url, thumbnail_url FROM stories WHERE id = ? AND user_id = ? AND tenant_id = ?',
                [(int) $storyIds[0], $userId, $tenantId]
            );
            if ($firstStory) {
                $coverUrl = $firstStory->thumbnail_url ?? $firstStory->media_url;
            }
        }

        DB::insert(
            'INSERT INTO story_highlights (tenant_id, user_id, title, cover_url, display_order, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$tenantId, $userId, $title, $coverUrl, $order]
        );

        $highlightId = (int) DB::getPdo()->lastInsertId();

        // Add stories to highlight
        foreach ($storyIds as $idx => $sid) {
            DB::insert(
                'INSERT IGNORE INTO story_highlight_items (highlight_id, story_id, display_order) VALUES (?, ?, ?)',
                [$highlightId, (int) $sid, $idx]
            );
        }

        return $this->getHighlightById($highlightId);
    }

    /**
     * Get user's highlights.
     *
     * @param int $userId
     * @return array
     */
    public function getHighlights(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $highlights = DB::select(
            'SELECT h.*,
                    (SELECT COUNT(*) FROM story_highlight_items shi WHERE shi.highlight_id = h.id) as story_count
             FROM story_highlights h
             WHERE h.tenant_id = ? AND h.user_id = ?
             ORDER BY h.display_order ASC',
            [$tenantId, $userId]
        );

        return array_map(fn($h) => [
            'id' => (int) $h->id,
            'title' => $h->title,
            'cover_url' => $h->cover_url,
            'story_count' => (int) $h->story_count,
            'display_order' => (int) $h->display_order,
            'created_at' => $h->created_at,
        ], $highlights);
    }

    /**
     * Add a story to an existing highlight.
     *
     * @param int $highlightId
     * @param int $storyId
     * @param int $userId Must be highlight owner
     *
     * @throws \RuntimeException
     */
    public function addToHighlight(int $highlightId, int $storyId, int $userId): void
    {
        $tenantId = TenantContext::getId();

        $highlight = DB::selectOne(
            'SELECT id FROM story_highlights WHERE id = ? AND user_id = ? AND tenant_id = ?',
            [$highlightId, $userId, $tenantId]
        );

        if (!$highlight) {
            throw new \RuntimeException('Highlight not found or you are not the owner');
        }

        // Get next order
        $maxOrder = DB::selectOne(
            'SELECT MAX(display_order) as max_order FROM story_highlight_items WHERE highlight_id = ?',
            [$highlightId]
        );

        DB::insert(
            'INSERT IGNORE INTO story_highlight_items (highlight_id, story_id, display_order) VALUES (?, ?, ?)',
            [$highlightId, $storyId, ($maxOrder->max_order ?? 0) + 1]
        );
    }

    /**
     * Delete a highlight.
     *
     * @param int $highlightId
     * @param int $userId Must be highlight owner
     *
     * @throws \RuntimeException
     */
    public function deleteHighlight(int $highlightId, int $userId): void
    {
        $tenantId = TenantContext::getId();

        $affected = DB::delete(
            'DELETE FROM story_highlights WHERE id = ? AND user_id = ? AND tenant_id = ?',
            [$highlightId, $userId, $tenantId]
        );

        if ($affected === 0) {
            throw new \RuntimeException('Highlight not found or you are not the owner');
        }
    }

    /**
     * Get stories for a highlight (used when viewing a highlight).
     *
     * @param int $highlightId
     * @param int|null $viewerId
     * @return array
     */
    public function getHighlightStories(int $highlightId, ?int $viewerId = null): array
    {
        $tenantId = TenantContext::getId();

        $stories = DB::select(
            'SELECT s.*,
                    u.first_name, u.last_name, u.avatar_url,
                    ' . ($viewerId ? 'CASE WHEN sv.id IS NOT NULL THEN 1 ELSE 0 END as is_viewed' : '0 as is_viewed') . '
             FROM story_highlight_items shi
             JOIN stories s ON s.id = shi.story_id
             JOIN users u ON u.id = s.user_id
             ' . ($viewerId ? 'LEFT JOIN story_views sv ON sv.story_id = s.id AND sv.viewer_id = ' . (int) $viewerId : '') . '
             WHERE shi.highlight_id = ?
               AND s.tenant_id = ?
             ORDER BY shi.display_order ASC',
            [$highlightId, $tenantId]
        );

        return array_map(fn($s) => $this->formatStory($s), $stories);
    }

    /**
     * Cron job: deactivate expired stories and clean up old media.
     */
    public function cleanupExpired(): void
    {
        // Deactivate expired stories
        $deactivated = DB::update(
            'UPDATE stories SET is_active = 0 WHERE is_active = 1 AND expires_at <= NOW()'
        );

        if ($deactivated > 0) {
            Log::info("StoryService: Deactivated {$deactivated} expired stories");
        }

        // Find stories older than retention period for media cleanup
        $oldStories = DB::select(
            'SELECT id, media_url, thumbnail_url FROM stories
             WHERE is_active = 0
               AND expires_at <= DATE_SUB(NOW(), INTERVAL ? DAY)
               AND (media_url IS NOT NULL OR thumbnail_url IS NOT NULL)',
            [self::MEDIA_RETENTION_DAYS]
        );

        foreach ($oldStories as $story) {
            $this->deleteMediaFile($story->media_url);
            $this->deleteMediaFile($story->thumbnail_url);

            DB::update(
                'UPDATE stories SET media_url = NULL, thumbnail_url = NULL WHERE id = ?',
                [$story->id]
            );
        }

        if (count($oldStories) > 0) {
            Log::info('StoryService: Cleaned up media for ' . count($oldStories) . ' old stories');
        }
    }

    /**
     * Handle story image upload.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param int $tenantId
     * @param int $userId
     * @return string The relative URL of the uploaded file
     */
    public function uploadMedia(\Illuminate\Http\UploadedFile $file, int $tenantId, int $userId): string
    {
        $dir = "uploads/stories/{$tenantId}/{$userId}";
        $filename = uniqid('story_') . '_' . time() . '.' . $file->getClientOriginalExtension();

        $file->move(public_path($dir), $filename);

        return "/{$dir}/{$filename}";
    }

    // ────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ────────────────────────────────────────────────────────────────────────

    private function getStoryById(int $storyId): array
    {
        $story = DB::selectOne(
            'SELECT s.*, u.first_name, u.last_name, u.avatar_url
             FROM stories s
             JOIN users u ON u.id = s.user_id
             WHERE s.id = ?',
            [$storyId]
        );

        if (!$story) {
            return [];
        }

        return $this->formatStory($story);
    }

    private function getHighlightById(int $highlightId): array
    {
        $h = DB::selectOne(
            'SELECT h.*,
                    (SELECT COUNT(*) FROM story_highlight_items shi WHERE shi.highlight_id = h.id) as story_count
             FROM story_highlights h
             WHERE h.id = ?',
            [$highlightId]
        );

        if (!$h) {
            return [];
        }

        return [
            'id' => (int) $h->id,
            'title' => $h->title,
            'cover_url' => $h->cover_url,
            'story_count' => (int) $h->story_count,
            'display_order' => (int) $h->display_order,
            'created_at' => $h->created_at,
        ];
    }

    private function formatStory(object $story): array
    {
        $result = [
            'id' => (int) $story->id,
            'user_id' => (int) $story->user_id,
            'media_type' => $story->media_type,
            'media_url' => $story->media_url,
            'thumbnail_url' => $story->thumbnail_url,
            'text_content' => $story->text_content,
            'text_style' => $story->text_style ? json_decode($story->text_style, true) : null,
            'background_color' => $story->background_color,
            'background_gradient' => $story->background_gradient,
            'duration' => (int) $story->duration,
            'view_count' => (int) $story->view_count,
            'is_viewed' => (bool) ($story->is_viewed ?? false),
            'expires_at' => $story->expires_at,
            'created_at' => $story->created_at,
        ];

        // Include user info if available
        if (isset($story->first_name)) {
            $result['user'] = [
                'id' => (int) $story->user_id,
                'name' => trim(($story->first_name ?? '') . ' ' . ($story->last_name ?? '')),
                'first_name' => $story->first_name ?? '',
                'avatar_url' => $story->avatar_url ?? null,
            ];
        }

        // Include poll data if applicable
        if ($story->media_type === 'poll') {
            $result['poll_question'] = $story->poll_question;
            $result['poll_options'] = $story->poll_options ? json_decode($story->poll_options, true) : [];
            $result['poll_results'] = $this->getPollResults((int) $story->id);
        }

        return $result;
    }

    private function deleteMediaFile(?string $url): void
    {
        if (!$url) {
            return;
        }

        // Convert URL path to filesystem path
        $path = public_path(ltrim($url, '/'));

        if (file_exists($path)) {
            try {
                unlink($path);
            } catch (\Throwable $e) {
                Log::warning("StoryService: Failed to delete media file: {$path}", ['error' => $e->getMessage()]);
            }
        }
    }
}
