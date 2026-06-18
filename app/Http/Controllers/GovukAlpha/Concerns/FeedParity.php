<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Support\FeedItemTables;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Feed — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, $this->intQuery, $this->attachPostMedia,
 * $this->reactionsForFeedItems, $this->commentsForFeedItems,
 * $this->normalizeFeedTargetType, $this->redirectToFeed, self::asStr) and the
 * private FeedService it injects ($this->feedService). New method names MUST be
 * module-prefixed and unique across AlphaController and every sibling trait.
 * Resolve other services via app(SomeService::class).
 *
 * Closes the React-parity gaps the core AlphaController feed coverage missed:
 *   - feedHashtagsDiscovery: trending + search of hashtags
 *     (react-frontend/src/pages/feed/HashtagsDiscoveryPage.tsx).
 *   - feedHashtag: the posts carrying a single hashtag, paginated
 *     (react-frontend/src/pages/feed/HashtagPage.tsx).
 *   - feedItemDetail: the polymorphic /feed/item/{type}/{id} permalink for any
 *     reactable feed item — listing, event, poll, goal, review, volunteer,
 *     challenge, resource, blog, discussion, job, post
 *     (react-frontend/src/pages/feed/PostDetailPage.tsx, polymorphic branch).
 *   - feedItemNotInterested: the soft "not interested" negative-feedback signal
 *     for any feed item (SocialController::notInterested, POST
 *     /v2/feed/posts/{id}/not-interested).
 *   - feedItemReaction: the emoji reaction picker extended to non-post feed
 *     items (FeedCard handleReact / POST /v2/reactions, any reactable type).
 *
 * All methods call the same tenant-scoped services the React v2 API uses
 * (App\Services\FeedService, App\Services\ReactionService) and gate exactly as
 * the core feed methods do. No feed/auth/notification logic is reimplemented.
 */
trait FeedParity
{
    /**
     * Reactable feed-item types the polymorphic permalink + typed reaction
     * accept. Mirrors SocialController::showItem's allowlist and
     * ReactionService::VALID_TARGET_TYPES (minus 'comment', which has its own
     * route on the post permalink). 'volunteering' is the feed alias for
     * 'volunteer' and is normalised before use.
     *
     * @var array<int, string>
     */
    private const FEED_POLYMORPHIC_TYPES = [
        'post', 'listing', 'event', 'poll', 'goal', 'review',
        'volunteer', 'challenge', 'resource', 'blog', 'discussion', 'job',
    ];

    // =====================================================================
    //  Hashtag discovery — trending + search
    // =====================================================================

    /**
     * GET /feed/hashtags — discover trending hashtags and search for more.
     *
     * Mirrors HashtagsDiscoveryPage.tsx: a list of trending tags (post counts)
     * plus a no-JS search box. Trending is the tenant's most-used tags in the
     * recent window; search is an indexed prefix LIKE. Both queries are
     * tenant-scoped and read-only, so this page is open to anonymous viewers
     * (the React page is mounted behind the feed module, which we gate on).
     */
    public function feedHashtagsDiscovery(Request $request, string $tenantSlug): Response
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);

        $tenantId = TenantContext::getId();
        $query = trim(self::asStr($request->query('q')));
        // Strip LIKE wildcard characters so a search term cannot inject a
        // pattern — same guard as FeedSocialController::searchHashtags.
        $searchTerm = $query !== '' ? (string) preg_replace('/[%_]/', '', $query) : '';
        $isSearching = mb_strlen($searchTerm) >= 1;

        $hashtags = [];
        $error = null;
        try {
            if ($isSearching) {
                $hashtags = DB::table('hashtags')
                    ->where('tenant_id', $tenantId)
                    ->where('tag', 'LIKE', mb_strtolower($searchTerm) . '%')
                    ->orderByDesc('post_count')
                    ->limit(50)
                    ->get(['tag', 'post_count'])
                    ->map(fn ($row): array => ['tag' => (string) $row->tag, 'post_count' => (int) $row->post_count])
                    ->all();
            } else {
                $hashtags = DB::table('hashtags')
                    ->where('tenant_id', $tenantId)
                    ->where('last_used_at', '>=', now()->subDays(7))
                    ->where('post_count', '>', 0)
                    ->orderByDesc('post_count')
                    ->limit(50)
                    ->get(['tag', 'post_count'])
                    ->map(fn ($row): array => ['tag' => (string) $row->tag, 'post_count' => (int) $row->post_count])
                    ->all();
            }
        } catch (\Throwable $e) {
            report($e);
            $error = __('govuk_alpha_feed.states.error');
        }

        return $this->view('accessible-frontend::feed-hashtags', [
            'title' => __('govuk_alpha_feed.hashtags.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'feed',
            'hashtags' => $hashtags,
            'searchQuery' => $query,
            'isSearching' => $isSearching,
            'error' => $error,
        ]);
    }

    /**
     * GET /feed/hashtag/{tag} — the posts carrying one hashtag.
     *
     * Mirrors HashtagPage.tsx + FeedSocialController::getHashtagPosts: find the
     * tag (tenant-scoped), gather its non-deleted/non-hidden post ids newest
     * first, then resolve each through FeedService::getItem (which re-applies
     * the per-post visibility gate and returns null for anything the viewer may
     * not see). Offset pagination keeps the no-JS "show more" link simple.
     */
    public function feedHashtag(Request $request, string $tenantSlug, string $tag): Response
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);

        $userId = $this->currentUserId();
        $tenantId = TenantContext::getId();

        $normalizedTag = mb_strtolower(ltrim(trim($tag), '#'));
        $perPage = $this->intQuery($request, 'per_page', 20, 1, 50);
        $page = $this->intQuery($request, 'page', 1, 1, 1000);
        $offset = ($page - 1) * $perPage;

        $items = [];
        $totalCount = 0;
        $hasMore = false;
        $error = null;

        try {
            $hashtag = DB::table('hashtags')
                ->where('tenant_id', $tenantId)
                ->where('tag', $normalizedTag)
                ->first(['id']);

            if ($hashtag !== null) {
                $baseIds = DB::table('post_hashtags as ph')
                    ->join('feed_posts as fp', 'ph.post_id', '=', 'fp.id')
                    ->where('ph.hashtag_id', $hashtag->id)
                    ->where('ph.tenant_id', $tenantId)
                    ->where('fp.tenant_id', $tenantId)
                    ->whereNull('fp.deleted_at')
                    ->where(function ($q) {
                        $q->whereNull('fp.publish_status')->orWhere('fp.publish_status', 'published');
                    })
                    ->where(function ($q) {
                        $q->whereNull('fp.is_hidden')->orWhere('fp.is_hidden', 0);
                    });

                $totalCount = (int) (clone $baseIds)->distinct()->count('fp.id');

                $rows = (clone $baseIds)
                    ->orderByDesc('fp.id')
                    ->offset($offset)
                    ->limit($perPage + 1)
                    ->pluck('fp.id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $hasMore = count($rows) > $perPage;
                if ($hasMore) {
                    $rows = array_slice($rows, 0, $perPage);
                }

                foreach ($rows as $postId) {
                    // FeedService::getItem re-runs FeedItemTables::canView for
                    // posts and returns null for anything not visible.
                    $resolved = $this->feedService->getItem('post', $postId, $userId);
                    if (is_array($resolved)) {
                        $items[] = $resolved;
                    }
                }
                $items = $this->attachPostMedia($items);
            }
        } catch (\Throwable $e) {
            report($e);
            $error = __('govuk_alpha_feed.states.error');
        }

        return $this->view('accessible-frontend::feed-hashtag', [
            'title' => '#' . $normalizedTag,
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'feed',
            'tag' => $normalizedTag,
            'items' => $items,
            'totalCount' => $totalCount,
            'reactionsByTarget' => $this->reactionsForFeedItems($items, $userId),
            'alphaReactions' => self::ALPHA_FEED_REACTIONS,
            'page' => $page,
            'perPage' => $perPage,
            'hasMore' => $hasMore,
            'requiresAuth' => $userId === null,
            'currentUserId' => $userId,
            'error' => $error,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    // =====================================================================
    //  Polymorphic item permalink
    // =====================================================================

    /**
     * GET /feed/item/{type}/{id} — the permalink for any reactable feed item.
     *
     * Mirrors SocialController::showItem + PostDetailPage.tsx (polymorphic
     * branch). The type is whitelisted, the target is visibility-checked
     * (cross-tenant / hidden → 404), and the same FeedService::getFeed
     * entity-scoped query the React API uses populates the card. Posts keep
     * their dedicated permalink (feed.posts.show); this covers everything else
     * — and accepts 'post' too so a deep-link of /feed/item/post/{id} still
     * resolves.
     */
    public function feedItemDetail(Request $request, string $tenantSlug, string $type, int $id): Response
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);

        $userId = $this->currentUserId();
        $targetType = $this->normalizeFeedTargetType($type);

        abort_unless(in_array($targetType, self::FEED_POLYMORPHIC_TYPES, true), 404);
        abort_unless($id > 0 && FeedItemTables::canView($targetType, $id, $userId), 404);

        $item = null;
        try {
            $result = $this->feedService->getFeed($userId, [
                'entity_id' => $id,
                'entity_type' => $targetType,
                'limit' => 1,
            ]);
            $rows = $this->attachPostMedia($result['items'] ?? []);
            $item = $rows[0] ?? null;
        } catch (\Throwable $e) {
            report($e);
        }

        abort_if($item === null, 404);

        $resolvedType = $this->normalizeFeedTargetType((string) ($item['type'] ?? $targetType));
        $resolvedId = (int) ($item['id'] ?? $id);

        $comments = [];
        if ($userId !== null && FeedItemTables::isCommentable($resolvedType)) {
            try {
                $comments = \App\Services\CommentService::getForEntity($resolvedType, $resolvedId, $userId);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $reactionsByTarget = $this->reactionsForFeedItems([$item], $userId);

        return $this->view('accessible-frontend::feed-item', [
            'title' => __('govuk_alpha_feed.item.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'feed',
            'item' => $item,
            'itemType' => $resolvedType,
            'itemId' => $resolvedId,
            'comments' => $comments,
            'itemReactions' => $reactionsByTarget[$resolvedType][$resolvedId] ?? null,
            'alphaReactions' => self::ALPHA_FEED_REACTIONS,
            'isCommentable' => FeedItemTables::isCommentable($resolvedType),
            'requiresAuth' => $userId === null,
            'currentUserId' => $userId,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    // =====================================================================
    //  Not-interested negative signal (any feed item)
    // =====================================================================

    /**
     * POST /feed/items/{type}/{id}/not-interested — record a soft "not
     * interested" signal for a feed item without muting its author.
     *
     * Mirrors SocialController::notInterested: the target type is whitelisted,
     * the target is verified to exist + be visible in this tenant (so the table
     * cannot be poisoned with arbitrary ids), then a feed_hidden row is written
     * (the EdgeRank negative signal reuses that table). Idempotent via
     * insertOrIgnore.
     */
    public function feedItemNotInterested(Request $request, string $tenantSlug, string $type, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return $this->redirectToFeed($request, $tenantSlug, 'auth-required', $type, $id);
        }

        $targetType = $this->normalizeFeedTargetType($type);
        if (!in_array($targetType, self::FEED_POLYMORPHIC_TYPES, true)) {
            $targetType = 'post';
        }

        if ($id <= 0 || !FeedItemTables::canView($targetType, $id, $userId)) {
            return $this->redirectToFeed($request, $tenantSlug, 'not-interested-failed', $targetType, $id);
        }

        $status = 'not-interested-failed';
        try {
            DB::table('feed_hidden')->insertOrIgnore([
                'user_id'     => $userId,
                'tenant_id'   => TenantContext::getId(),
                'target_type' => $targetType,
                'target_id'   => $id,
                'created_at'  => now(),
            ]);
            $status = 'not-interested';
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->redirectToFeed($request, $tenantSlug, $status, $targetType, $id);
    }

    // =====================================================================
    //  Emoji reaction on a non-post feed item
    // =====================================================================

    /**
     * POST /feed/items/{type}/{id}/react — toggle an emoji reaction on any
     * reactable feed item (listing, event, poll, goal, review, volunteer,
     * challenge, resource, blog, discussion, job, post).
     *
     * Mirrors ReactionController::toggle / FeedCard handleReact (POST
     * /v2/reactions). The accessible frontend previously only reacted to posts
     * and comments; this extends the same curated reaction set to typed feed
     * cards. The reaction type is validated against the accessible set AND
     * ReactionService::VALID_TYPES (fail-closed), the target type is whitelisted
     * and visibility-checked, and the member can only react as themselves.
     */
    public function feedItemReaction(Request $request, string $tenantSlug, string $type, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasModule('feed'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return $this->redirectToFeed($request, $tenantSlug, 'auth-required', $type, $id);
        }

        $targetType = $this->normalizeFeedTargetType($type);
        $emoji = self::asStr($request->input('emoji'));

        // Accept only the curated accessible reaction set, which is itself a
        // subset of ReactionService::VALID_TYPES. Fail closed on anything else.
        $reactionValid = array_key_exists($emoji, self::ALPHA_FEED_REACTIONS)
            && in_array($emoji, \App\Services\ReactionService::VALID_TYPES, true);
        $typeValid = in_array($targetType, \App\Services\ReactionService::VALID_TARGET_TYPES, true);

        if (!$reactionValid || !$typeValid || $id <= 0 || !FeedItemTables::canView($targetType, $id, $userId)) {
            return $this->redirectToFeed($request, $tenantSlug, 'reaction-failed', $targetType, $id);
        }

        $status = 'reaction-failed';
        try {
            $result = app(\App\Services\ReactionService::class)->toggleReaction($id, $targetType, $emoji, $userId);
            $status = ($result['action'] ?? '') === 'removed' ? 'reaction-removed' : 'reaction-added';

            // Best-effort author notification, matching the post-reaction path.
            // Reacting to your own content never notifies. The bell/email render
            // resolves in the recipient's locale inside the service.
            if ($status === 'reaction-added') {
                try {
                    $ownerId = \App\Services\SocialNotificationService::getContentOwnerId($targetType, $id);
                    if ($ownerId && $ownerId !== $userId) {
                        \App\Services\SocialNotificationService::notifyLike($ownerId, $userId, $targetType, $id, $emoji);
                    }
                } catch (\Throwable $notifyError) {
                    Log::warning('Alpha typed-item reaction notification failed: ' . $notifyError->getMessage());
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->redirectToFeed($request, $tenantSlug, $status, $targetType, $id);
    }
}
