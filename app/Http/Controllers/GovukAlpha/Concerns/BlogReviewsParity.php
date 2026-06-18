<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\GovukAlpha\Concerns;

use App\Core\TenantContext;
use App\Services\BlogService;
use App\Services\CommentService;
use App\Services\ReactionService;
use App\Services\ReviewService;
use App\Services\SocialNotificationService;
use App\Support\FeedItemTables;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Blog & reviews — accessible (GOV.UK) frontend parity methods.
 *
 * Composed into AlphaController. Trait methods may call the controller's
 * private helpers ($this->view, $this->currentUserId, $this->assertTenantSlug,
 * $this->allowed, self::asStr). New method names MUST be module-prefixed and
 * unique across AlphaController and every sibling trait. Resolve services via
 * app(SomeService::class) rather than the constructor.
 *
 * What this trait adds (the React frontend is the source of truth):
 *   - A rich blog comment thread page (edit / delete / reply-to-specific /
 *     emoji reactions) — the existing /blog/{slug} page only renders a flat
 *     read-only thread with a single add-comment form.
 *   - A social interaction panel (like/react + "who liked this" likers page)
 *     on a blog post, mirroring SocialInteractionPanel + LikersModal.
 *   - A paginated reviews list (Received / Given) with cursor "load more",
 *     mirroring the ReviewsPage tabs that fetch 20 at a time.
 *   - Review moderation (comment + react on a review itself), mirroring the
 *     SocialInteractionPanel embedded on each review card.
 *
 * All money/auth/notification logic is delegated to the same services the
 * React API controllers call (CommentService, ReactionService, ReviewService).
 */
trait BlogReviewsParity
{
    /**
     * Curated accessible reaction set for blog/comment/review surfaces.
     * Mirrors React's AVAILABLE_REACTIONS (useSocialInteractions.ts) and is a
     * strict subset of ReactionService::VALID_TYPES. type => emoji glyph.
     */
    private const ALPHA_BLOGREVIEWS_REACTIONS = [
        'like'      => "\u{1F44D}", // thumbs up
        'love'      => "\u{2764}\u{FE0F}", // heart
        'laugh'     => "\u{1F602}", // tears of joy
        'wow'       => "\u{1F62E}", // astonished
        'sad'       => "\u{1F622}", // crying
        'celebrate' => "\u{1F389}", // party popper
    ];

    // =====================================================================
    // Blog post — comment thread (edit / delete / reply / reactions)
    // =====================================================================

    /**
     * Full comment thread for a blog post with edit / delete / reply-to-specific
     * and emoji reactions per comment. Mirrors CommentsSection embedded in the
     * React blog post detail. Comments require auth (matches the React panel).
     */
    public function blogReviewsPostComments(Request $request, string $tenantSlug, string $slug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('blog'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $post = app(BlogService::class)->getBySlug($slug);
        abort_if($post === null, 404);

        $postId = (int) ($post['id'] ?? 0);

        $comments = [];
        $reactions = ['counts' => [], 'total' => 0, 'user_reaction' => null];
        try {
            $comments = CommentService::getForEntity('blog', $postId, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            $reactions = app(ReactionService::class)->getReactions($postId, 'blog', $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::blogreviews-post-comments', [
            'title' => __('govuk_alpha_blogreviews.comments.title', ['title' => (string) ($post['title'] ?? '')]),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'blog',
            'post' => $post,
            'comments' => is_array($comments) ? $comments : [],
            'commentsCount' => CommentService::countAll(is_array($comments) ? $comments : []),
            'currentUserId' => $userId,
            'alphaReactions' => self::ALPHA_BLOGREVIEWS_REACTIONS,
            'postReactionCounts' => (array) ($reactions['counts'] ?? []),
            'postReactionTotal' => (int) ($reactions['total'] ?? 0),
            'postUserReaction' => $reactions['user_reaction'] ?? null,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Add a comment or reply (parent_id) to a blog post. Mirrors
     * CommentService::addComment with the parent_id reply path.
     */
    public function blogReviewsStorePostComment(Request $request, string $tenantSlug, string $slug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('blog'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $post = app(BlogService::class)->getBySlug($slug);
        abort_if($post === null, 404);

        $postId = (int) ($post['id'] ?? 0);
        $body = trim(self::asStr($request->input('body')));
        $parentRaw = self::asStr($request->input('parent_id'));
        $parentId = ctype_digit($parentRaw) && (int) $parentRaw > 0 ? (int) $parentRaw : null;

        if ($body === '') {
            return $this->blogReviewsCommentsRedirect($tenantSlug, $slug, 'comment-invalid');
        }

        $status = 'comment-failed';
        try {
            $result = CommentService::addComment(
                $userId,
                (int) TenantContext::getId(),
                'blog',
                $postId,
                mb_substr($body, 0, 5000),
                $parentId
            );
            $status = !empty($result['success']) ? ($parentId !== null ? 'reply-added' : 'comment-added') : 'comment-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->blogReviewsCommentsRedirect($tenantSlug, $slug, $status);
    }

    /**
     * Edit a blog comment (owner only). CommentService::update is owner-scoped
     * and returns the stored content on success, null otherwise.
     */
    public function blogReviewsUpdateComment(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('blog'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $content = trim(self::asStr($request->input('content')));
        if ($content === '') {
            return $this->blogReviewsCommentRedirectFor($request, $tenantSlug, 'comment-empty');
        }

        $status = 'comment-update-failed';
        try {
            $status = CommentService::update($id, $userId, mb_substr($content, 0, 5000)) !== null
                ? 'comment-updated'
                : 'comment-update-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->blogReviewsCommentRedirectFor($request, $tenantSlug, $status);
    }

    /**
     * Delete a blog comment (owner only). Cascades to replies in the service.
     */
    public function blogReviewsDeleteComment(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('blog'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $status = 'comment-delete-failed';
        try {
            $status = CommentService::delete($id, $userId) > 0 ? 'comment-deleted' : 'comment-delete-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->blogReviewsCommentRedirectFor($request, $tenantSlug, $status);
    }

    /**
     * Toggle an emoji reaction on a blog comment. Only the curated accessible
     * set is accepted (a strict subset of ReactionService::VALID_TYPES).
     */
    public function blogReviewsCommentReaction(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('blog'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $status = $this->blogReviewsToggleReaction($userId, 'comment', $id, self::asStr($request->input('emoji')));

        return $this->blogReviewsCommentRedirectFor($request, $tenantSlug, $status, 'comment-' . $id);
    }

    /**
     * Toggle an emoji reaction (like/love/...) on a blog post itself. Mirrors
     * the SocialInteractionPanel like button (POST /v2/reactions, type=blog).
     */
    public function blogReviewsPostReaction(Request $request, string $tenantSlug, string $slug): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('blog'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $post = app(BlogService::class)->getBySlug($slug);
        abort_if($post === null, 404);

        $status = $this->blogReviewsToggleReaction($userId, 'blog', (int) ($post['id'] ?? 0), self::asStr($request->input('emoji')));

        return $this->blogReviewsCommentsRedirect($tenantSlug, $slug, $status, 'post-reactions');
    }

    /**
     * "Who reacted" page for a blog post (HTML-first replacement for the React
     * LikersModal). Mirrors ReactionService::getReactors / the reactors endpoint.
     */
    public function blogReviewsPostLikers(Request $request, string $tenantSlug, string $slug, string $reaction): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('blog'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $reaction = $this->allowed($reaction, array_keys(self::ALPHA_BLOGREVIEWS_REACTIONS), 'like');
        $post = app(BlogService::class)->getBySlug($slug);
        abort_if($post === null, 404);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $reactors = ['users' => [], 'total' => 0, 'has_more' => false];
        try {
            $reactors = app(ReactionService::class)->getReactors((int) ($post['id'] ?? 0), 'blog', $reaction, $page, $perPage);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::blogreviews-likers', [
            'title' => __('govuk_alpha_blogreviews.likers.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'blog',
            'post' => $post,
            'reaction' => $reaction,
            'reactionEmoji' => self::ALPHA_BLOGREVIEWS_REACTIONS[$reaction] ?? '',
            'likers' => is_array($reactors['users'] ?? null) ? $reactors['users'] : [],
            'likersTotal' => (int) ($reactors['total'] ?? 0),
            'likersHasMore' => (bool) ($reactors['has_more'] ?? false),
            'likersPage' => $page,
        ]);
    }

    // =====================================================================
    // Reviews — paginated list (Received / Given) with cursor load-more
    // =====================================================================

    /**
     * Paginated reviews list for a single tab (received / given). Mirrors the
     * React ReviewsPage tabs which fetch 20 at a time with a "Load more" button.
     * The existing /reviews page only ever shows the first 20 per section.
     */
    public function blogReviewsList(Request $request, string $tenantSlug): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('reviews'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $tab = $this->allowed(self::asStr($request->query('tab')), ['received', 'given'], 'received');
        $cursor = self::asStr($request->query('cursor')) ?: null;

        $items = [];
        $nextCursor = null;
        $hasMore = false;
        try {
            $svc = app(ReviewService::class);
            $result = $tab === 'given'
                ? $svc->getGivenByUser($userId, ['limit' => 20, 'cursor' => $cursor])
                : $svc->getForUser($userId, ['limit' => 20, 'cursor' => $cursor]);
            $items = is_array($result['items'] ?? null) ? $result['items'] : [];
            $nextCursor = $result['cursor'] ?? null;
            $hasMore = (bool) ($result['has_more'] ?? false);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::blogreviews-reviews-list', [
            'title' => __('govuk_alpha_blogreviews.reviews_list.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'reviews',
            'reviewsTab' => $tab,
            'reviewsItems' => $items,
            'reviewsCursor' => is_string($nextCursor) ? $nextCursor : null,
            'reviewsHasMore' => $hasMore,
            'isFirstPage' => $cursor === null,
        ]);
    }

    // =====================================================================
    // Review moderation — comment + react on a review itself
    // =====================================================================

    /**
     * Comment thread on a review (review moderation). Mirrors the
     * SocialInteractionPanel embedded on each review card in React. Uses the
     * polymorphic social comments system with target_type='review'.
     */
    public function blogReviewsReviewComments(Request $request, string $tenantSlug, int $id): Response|RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('reviews'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $review = null;
        try {
            $review = app(ReviewService::class)->getById($id);
        } catch (\Throwable $e) {
            report($e);
        }
        // Cross-tenant / missing → 404 (getById is tenant-scoped by the global scope).
        abort_if($review === null, 404);

        $comments = [];
        $reactions = ['counts' => [], 'total' => 0, 'user_reaction' => null];
        try {
            $comments = CommentService::getForEntity('review', $id, $userId);
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            $reactions = app(ReactionService::class)->getReactions($id, 'review', $userId);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->view('accessible-frontend::blogreviews-review-comments', [
            'title' => __('govuk_alpha_blogreviews.review_comments.title'),
            'tenantSlug' => $tenantSlug,
            'activeNav' => 'reviews',
            'review' => $review,
            'reviewId' => $id,
            'comments' => is_array($comments) ? $comments : [],
            'commentsCount' => CommentService::countAll(is_array($comments) ? $comments : []),
            'currentUserId' => $userId,
            'alphaReactions' => self::ALPHA_BLOGREVIEWS_REACTIONS,
            'reviewReactionCounts' => (array) ($reactions['counts'] ?? []),
            'reviewReactionTotal' => (int) ($reactions['total'] ?? 0),
            'reviewUserReaction' => $reactions['user_reaction'] ?? null,
            'status' => self::asStr($request->query('status')) ?: null,
        ]);
    }

    /**
     * Add a comment / reply on a review.
     */
    public function blogReviewsStoreReviewComment(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('reviews'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $review = null;
        try {
            $review = app(ReviewService::class)->getById($id);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($review === null, 404);

        $body = trim(self::asStr($request->input('body')));
        $parentRaw = self::asStr($request->input('parent_id'));
        $parentId = ctype_digit($parentRaw) && (int) $parentRaw > 0 ? (int) $parentRaw : null;

        if ($body === '') {
            return $this->blogReviewsReviewCommentsRedirect($tenantSlug, $id, 'comment-invalid');
        }

        $status = 'comment-failed';
        try {
            $result = CommentService::addComment(
                $userId,
                (int) TenantContext::getId(),
                'review',
                $id,
                mb_substr($body, 0, 5000),
                $parentId
            );
            $status = !empty($result['success']) ? ($parentId !== null ? 'reply-added' : 'comment-added') : 'comment-failed';
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->blogReviewsReviewCommentsRedirect($tenantSlug, $id, $status);
    }

    /**
     * Toggle a reaction on a review.
     */
    public function blogReviewsReviewReaction(Request $request, string $tenantSlug, int $id): RedirectResponse
    {
        $this->assertTenantSlug($tenantSlug);
        abort_unless(TenantContext::hasFeature('reviews'), 403);

        $userId = $this->currentUserId();
        if ($userId === null) {
            return redirect()->route('govuk-alpha.login', ['tenantSlug' => $tenantSlug, 'status' => 'auth-required']);
        }

        $review = null;
        try {
            $review = app(ReviewService::class)->getById($id);
        } catch (\Throwable $e) {
            report($e);
        }
        abort_if($review === null, 404);

        $status = $this->blogReviewsToggleReaction($userId, 'review', $id, self::asStr($request->input('emoji')));

        return $this->blogReviewsReviewCommentsRedirect($tenantSlug, $id, $status, 'review-reactions');
    }

    // =====================================================================
    // Shared private helpers (module-prefixed to stay unique)
    // =====================================================================

    /**
     * Toggle a reaction through ReactionService, accepting only the curated
     * accessible reaction set. Fires the best-effort author notification on add
     * (matching the React like path) and returns a status string for the banner.
     */
    private function blogReviewsToggleReaction(int $userId, string $targetType, int $targetId, string $emoji): string
    {
        if (!array_key_exists($emoji, self::ALPHA_BLOGREVIEWS_REACTIONS)
            || !in_array($emoji, ReactionService::VALID_TYPES, true)) {
            return 'reaction-failed';
        }

        if ($targetId <= 0 || !FeedItemTables::canView($targetType, $targetId, $userId)) {
            return 'reaction-failed';
        }

        try {
            $result = app(ReactionService::class)->toggleReaction($targetId, $targetType, $emoji, $userId);
            $status = ($result['action'] ?? '') === 'removed' ? 'reaction-removed' : 'reaction-added';

            if ($status === 'reaction-added') {
                try {
                    $ownerId = SocialNotificationService::getContentOwnerId($targetType, $targetId);
                    if ($ownerId && $ownerId !== $userId) {
                        $recipient = \App\Models\User::query()
                            ->where('id', $ownerId)
                            ->where('tenant_id', TenantContext::getId())
                            ->first(['id', 'preferred_language']);
                        \App\I18n\LocaleContext::withLocale($recipient, function () use ($ownerId, $userId, $targetType, $targetId, $emoji): void {
                            SocialNotificationService::notifyLike($ownerId, $userId, $targetType, $targetId, $emoji);
                        });
                    }
                } catch (\Throwable $e) {
                    Log::warning('BlogReviews reaction notification failed: ' . $e->getMessage());
                }
            }

            return $status;
        } catch (\Throwable $e) {
            report($e);
            return 'reaction-failed';
        }
    }

    /**
     * Route a comment mutation (update/delete/react) back to the right thread —
     * a review thread when a review_id hidden input is present, otherwise the
     * blog comment thread keyed by slug. Keeps the per-comment forms generic
     * across both surfaces while preserving the correct return page.
     */
    private function blogReviewsCommentRedirectFor(Request $request, string $tenantSlug, string $status, ?string $fragment = 'comments'): RedirectResponse
    {
        $reviewRaw = self::asStr($request->input('review_id'));
        if (ctype_digit($reviewRaw) && (int) $reviewRaw > 0) {
            return $this->blogReviewsReviewCommentsRedirect($tenantSlug, (int) $reviewRaw, $status, $fragment);
        }

        return $this->blogReviewsCommentsRedirect($tenantSlug, self::asStr($request->input('slug')), $status, $fragment);
    }

    /**
     * Redirect back to the blog comment thread with a status + optional anchor.
     */
    private function blogReviewsCommentsRedirect(string $tenantSlug, string $slug, string $status, ?string $fragment = 'comments'): RedirectResponse
    {
        if ($slug === '') {
            return redirect()->route('govuk-alpha.blog.index', ['tenantSlug' => $tenantSlug]);
        }

        $redirect = redirect()->route('govuk-alpha.blogreviews.blog.comments', [
            'tenantSlug' => $tenantSlug,
            'slug' => $slug,
            'status' => $status,
        ]);

        return $fragment !== null ? $redirect->withFragment($fragment) : $redirect;
    }

    /**
     * Redirect back to the review comment thread with a status + optional anchor.
     */
    private function blogReviewsReviewCommentsRedirect(string $tenantSlug, int $id, string $status, ?string $fragment = 'comments'): RedirectResponse
    {
        $redirect = redirect()->route('govuk-alpha.blogreviews.reviews.comments', [
            'tenantSlug' => $tenantSlug,
            'id' => $id,
            'status' => $status,
        ]);

        return $fragment !== null ? $redirect->withFragment($fragment) : $redirect;
    }
}
