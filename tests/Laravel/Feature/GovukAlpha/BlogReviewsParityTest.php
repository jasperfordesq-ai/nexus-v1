<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for the accessible (GOV.UK) Blog & Reviews parity module.
 *
 * Covers the rich blog comment thread (add / reply / edit / delete / react),
 * blog-post reactions + likers page, the paginated reviews list (received /
 * given with cursor load-more), and review moderation (comment + react on a
 * review) — plus the auth / feature gates and cross-tenant 404s.
 *
 * Extends the same base TestCase + DatabaseTransactions trait that
 * GovukAlphaFrontendTest uses; the private helpers there are replicated below.
 */
class BlogReviewsParityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['auth']->forgetGuards();

        foreach ([
            'HTTP_X_TENANT_ID',
            'HTTP_X_TENANT_SLUG',
            'HTTP_AUTHORIZATION',
            'REDIRECT_HTTP_AUTHORIZATION',
        ] as $serverKey) {
            unset($_SERVER[$serverKey]);
        }

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    // ---------------------------------------------------------------
    // Helpers (replicated from GovukAlphaFrontendTest, which keeps them private)
    // ---------------------------------------------------------------

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function enableAlphaFeatures(array $features): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        foreach ($features as $f) {
            $current[$f] = true;
        }
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function seedBlogPost(array $overrides = []): array
    {
        $authorId = (int) ($overrides['author_id'] ?? $this->authorId());
        $slug = (string) ($overrides['slug'] ?? ('parity-blog-' . uniqid()));
        $id = (int) DB::table('posts')->insertGetId(array_merge([
            'tenant_id'  => $this->testTenantId,
            'author_id'  => $authorId,
            'title'      => 'Parity Blog Post',
            'slug'       => $slug,
            'excerpt'    => 'An accessible parity test post.',
            'content'    => 'This is real blog content for the accessible parity tests.',
            'status'     => 'published',
            'created_at' => now(),
        ], array_diff_key($overrides, ['author_id' => true])));

        return ['id' => $id, 'slug' => $slug];
    }

    /**
     * A throwaway author user (posts.author_id has an FK to users).
     */
    private function authorId(): int
    {
        return (int) User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ])->id;
    }

    private function seedComment(string $targetType, int $targetId, int $userId, array $overrides = []): int
    {
        return (int) DB::table('comments')->insertGetId(array_merge([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $userId,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'parent_id'   => null,
            'content'     => 'A seeded comment.',
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $overrides));
    }

    private function seedReview(int $reviewerId, int $receiverId, array $overrides = []): int
    {
        return (int) DB::table('reviews')->insertGetId(array_merge([
            'tenant_id'   => $this->testTenantId,
            'reviewer_id' => $reviewerId,
            'receiver_id' => $receiverId,
            'rating'      => 5,
            'comment'     => 'Great exchange.',
            'review_type' => 'local',
            'status'      => 'approved',
            'is_anonymous' => 0,
            'created_at'  => now(),
        ], $overrides));
    }

    // ===============================================================
    // Blog comment thread
    // ===============================================================

    public function test_blogreviews_comments_redirects_anonymous_to_login(): void
    {
        $this->enableAlphaFeatures(['blog']);
        $post = $this->seedBlogPost();

        $res = $this->get("/{$this->testTenantSlug}/alpha/blog/{$post['slug']}/comments");

        $res->assertStatus(302);
        $res->assertRedirectContains('/alpha/login');
    }

    public function test_blogreviews_comments_returns_403_when_feature_disabled(): void
    {
        $this->authenticatedUser();
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode(['blog' => false])]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $post = $this->seedBlogPost();

        $res = $this->get("/{$this->testTenantSlug}/alpha/blog/{$post['slug']}/comments");

        $res->assertStatus(403);
    }

    public function test_blogreviews_comments_404_for_unknown_slug(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['blog']);

        $res = $this->get("/{$this->testTenantSlug}/alpha/blog/no-such-post-zzz/comments");

        $res->assertStatus(404);
    }

    public function test_blogreviews_comments_renders_thread_for_authenticated_user(): void
    {
        $user = $this->authenticatedUser();
        $this->enableAlphaFeatures(['blog']);
        $post = $this->seedBlogPost();
        $this->seedComment('blog', $post['id'], $user->id, ['content' => 'A visible parity comment']);

        $res = $this->get("/{$this->testTenantSlug}/alpha/blog/{$post['slug']}/comments");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_blogreviews.comments.heading'));
        $res->assertSee('A visible parity comment');
        $res->assertSee(__('govuk_alpha_blogreviews.comments.submit'));
        $res->assertSee(__('govuk_alpha_blogreviews.reactions.post_legend'));
    }

    public function test_blogreviews_store_comment_persists(): void
    {
        $user = $this->authenticatedUser();
        $this->enableAlphaFeatures(['blog']);
        $post = $this->seedBlogPost();

        $res = $this->post("/{$this->testTenantSlug}/alpha/blog/{$post['slug']}/comments", [
            'body' => 'My new accessible comment',
        ]);

        $res->assertStatus(302);
        $this->assertDatabaseHas('comments', [
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $user->id,
            'target_type' => 'blog',
            'target_id'   => $post['id'],
            'content'     => 'My new accessible comment',
        ]);
    }

    public function test_blogreviews_store_reply_persists_with_parent(): void
    {
        $user = $this->authenticatedUser();
        $this->enableAlphaFeatures(['blog']);
        $post = $this->seedBlogPost();
        $parentId = $this->seedComment('blog', $post['id'], $user->id);

        // Replies are the rich-thread parity feature, served by
        // blogReviewsStorePostComment at /comments/add (the base
        // /blog/{slug}/comments form is flat and does not thread). Posting the
        // reply to the flat endpoint would silently drop parent_id.
        $res = $this->post("/{$this->testTenantSlug}/alpha/blog/{$post['slug']}/comments/add", [
            'body'      => 'A threaded reply',
            'parent_id' => $parentId,
        ]);

        $res->assertStatus(302);
        $this->assertDatabaseHas('comments', [
            'target_type' => 'blog',
            'target_id'   => $post['id'],
            'parent_id'   => $parentId,
            'content'     => 'A threaded reply',
        ]);
    }

    public function test_blogreviews_store_comment_rejects_empty_body(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['blog']);
        $post = $this->seedBlogPost();

        $res = $this->post("/{$this->testTenantSlug}/alpha/blog/{$post['slug']}/comments", [
            'body' => '   ',
        ]);

        $res->assertStatus(302);
        $res->assertRedirectContains('status=comment-invalid');
    }

    public function test_blogreviews_update_comment_owner_can_edit(): void
    {
        $user = $this->authenticatedUser();
        $this->enableAlphaFeatures(['blog']);
        $post = $this->seedBlogPost();
        $commentId = $this->seedComment('blog', $post['id'], $user->id, ['content' => 'before edit']);

        $res = $this->post("/{$this->testTenantSlug}/alpha/blog/comments/{$commentId}/update", [
            'slug'    => $post['slug'],
            'content' => 'after edit',
        ]);

        $res->assertStatus(302);
        $this->assertDatabaseHas('comments', ['id' => $commentId, 'content' => 'after edit']);
    }

    public function test_blogreviews_update_comment_non_owner_cannot_edit(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['blog']);
        $post = $this->seedBlogPost();
        // Owned by a different user.
        $commentId = $this->seedComment('blog', $post['id'], $this->authorId(), ['content' => 'untouched']);

        $res = $this->post("/{$this->testTenantSlug}/alpha/blog/comments/{$commentId}/update", [
            'slug'    => $post['slug'],
            'content' => 'hacked content',
        ]);

        $res->assertStatus(302);
        // CommentService::update is owner-scoped — content is unchanged.
        $this->assertDatabaseHas('comments', ['id' => $commentId, 'content' => 'untouched']);
    }

    public function test_blogreviews_delete_comment_owner_removes_it(): void
    {
        $user = $this->authenticatedUser();
        $this->enableAlphaFeatures(['blog']);
        $post = $this->seedBlogPost();
        $commentId = $this->seedComment('blog', $post['id'], $user->id);

        $res = $this->post("/{$this->testTenantSlug}/alpha/blog/comments/{$commentId}/delete", [
            'slug' => $post['slug'],
        ]);

        $res->assertStatus(302);
        // The Comment model uses SoftDeletes, so CommentService::delete sets
        // deleted_at rather than removing the row. The owner delete succeeded
        // when the row is soft-deleted (deleted_at is non-null).
        $this->assertDatabaseHas('comments', ['id' => $commentId]);
        $this->assertNotNull(
            DB::table('comments')->where('id', $commentId)->value('deleted_at'),
            'Owner-deleted comment should be soft-deleted (deleted_at set).'
        );
    }

    public function test_blogreviews_delete_comment_non_owner_keeps_it(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['blog']);
        $post = $this->seedBlogPost();
        $commentId = $this->seedComment('blog', $post['id'], $this->authorId());

        $res = $this->post("/{$this->testTenantSlug}/alpha/blog/comments/{$commentId}/delete", [
            'slug' => $post['slug'],
        ]);

        $res->assertStatus(302);
        $this->assertDatabaseHas('comments', ['id' => $commentId]);
    }

    public function test_blogreviews_comment_reaction_persists_and_toggles(): void
    {
        $user = $this->authenticatedUser();
        $this->enableAlphaFeatures(['blog']);
        $post = $this->seedBlogPost();
        $commentId = $this->seedComment('blog', $post['id'], $this->authorId());

        // Add
        $this->post("/{$this->testTenantSlug}/alpha/blog/comments/{$commentId}/react", [
            'slug'  => $post['slug'],
            'emoji' => 'like',
        ])->assertStatus(302);

        $this->assertDatabaseHas('reactions', [
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $user->id,
            'target_type' => 'comment',
            'target_id'   => $commentId,
            'emoji'       => 'like',
        ]);

        // Toggle off
        $this->post("/{$this->testTenantSlug}/alpha/blog/comments/{$commentId}/react", [
            'slug'  => $post['slug'],
            'emoji' => 'like',
        ])->assertStatus(302);

        $this->assertDatabaseMissing('reactions', [
            'user_id'     => $user->id,
            'target_type' => 'comment',
            'target_id'   => $commentId,
            'emoji'       => 'like',
        ]);
    }

    public function test_blogreviews_comment_reaction_rejects_unknown_emoji(): void
    {
        $user = $this->authenticatedUser();
        $this->enableAlphaFeatures(['blog']);
        $post = $this->seedBlogPost();
        $commentId = $this->seedComment('blog', $post['id'], $this->authorId());

        $res = $this->post("/{$this->testTenantSlug}/alpha/blog/comments/{$commentId}/react", [
            'slug'  => $post['slug'],
            'emoji' => 'time_credit', // valid backend type but NOT in the curated alpha set
        ]);

        $res->assertStatus(302);
        $this->assertDatabaseMissing('reactions', [
            'user_id'     => $user->id,
            'target_type' => 'comment',
            'target_id'   => $commentId,
        ]);
    }

    // ===============================================================
    // Blog post reactions + likers page
    // ===============================================================

    public function test_blogreviews_post_reaction_persists(): void
    {
        $user = $this->authenticatedUser();
        $this->enableAlphaFeatures(['blog']);
        $post = $this->seedBlogPost();

        $res = $this->post("/{$this->testTenantSlug}/alpha/blog/{$post['slug']}/react", [
            'emoji' => 'love',
        ]);

        $res->assertStatus(302);
        $this->assertDatabaseHas('reactions', [
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $user->id,
            'target_type' => 'blog',
            'target_id'   => $post['id'],
            'emoji'       => 'love',
        ]);
    }

    public function test_blogreviews_likers_page_lists_reactors(): void
    {
        $user = $this->authenticatedUser();
        $this->enableAlphaFeatures(['blog']);
        $post = $this->seedBlogPost();
        DB::table('reactions')->insert([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $user->id,
            'target_type' => 'blog',
            'target_id'   => $post['id'],
            'emoji'       => 'like',
            'created_at'  => now(),
        ]);

        $res = $this->get("/{$this->testTenantSlug}/alpha/blog/{$post['slug']}/likers/like");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_blogreviews.likers.title'));
    }

    public function test_blogreviews_likers_page_redirects_anonymous(): void
    {
        $this->enableAlphaFeatures(['blog']);
        $post = $this->seedBlogPost();

        $res = $this->get("/{$this->testTenantSlug}/alpha/blog/{$post['slug']}/likers/like");

        $res->assertStatus(302);
        $res->assertRedirectContains('/alpha/login');
    }

    // ===============================================================
    // Reviews list (paginated, received / given)
    // ===============================================================

    public function test_blogreviews_reviews_list_redirects_anonymous(): void
    {
        $this->enableAlphaFeatures(['reviews']);

        $res = $this->get("/{$this->testTenantSlug}/alpha/reviews/list");

        $res->assertStatus(302);
        $res->assertRedirectContains('/alpha/login');
    }

    public function test_blogreviews_reviews_list_returns_403_when_feature_disabled(): void
    {
        $this->authenticatedUser();
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode(['reviews' => false])]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $res = $this->get("/{$this->testTenantSlug}/alpha/reviews/list");

        $res->assertStatus(403);
    }

    public function test_blogreviews_reviews_list_received_renders(): void
    {
        $user = $this->authenticatedUser();
        $this->enableAlphaFeatures(['reviews']);
        $reviewer = $this->authorId();
        $this->seedReview($reviewer, $user->id, ['comment' => 'Received parity review']);

        $res = $this->get("/{$this->testTenantSlug}/alpha/reviews/list?tab=received");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_blogreviews.reviews_list.title'));
        $res->assertSee('Received parity review');
    }

    public function test_blogreviews_reviews_list_given_renders(): void
    {
        $user = $this->authenticatedUser();
        $this->enableAlphaFeatures(['reviews']);
        $receiver = $this->authorId();
        $this->seedReview($user->id, $receiver, ['comment' => 'Given parity review']);

        $res = $this->get("/{$this->testTenantSlug}/alpha/reviews/list?tab=given");

        $res->assertOk();
        $res->assertSee('Given parity review');
        $res->assertSee(__('govuk_alpha_blogreviews.reviews_list.given_tab'));
    }

    // ===============================================================
    // Review moderation (comment + react on a review)
    // ===============================================================

    public function test_blogreviews_review_comments_renders(): void
    {
        $user = $this->authenticatedUser();
        $this->enableAlphaFeatures(['reviews']);
        $reviewId = $this->seedReview($this->authorId(), $user->id, ['comment' => 'Reviewed work']);

        $res = $this->get("/{$this->testTenantSlug}/alpha/reviews/{$reviewId}/comments");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_blogreviews.review_comments.heading'));
        $res->assertSee('Reviewed work');
    }

    public function test_blogreviews_review_comments_404_for_cross_tenant(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['reviews']);
        // Review belongs to a different tenant — the tenant-scoped getById returns null.
        $reviewId = $this->seedReview($this->authorId(), $this->authorId(), ['tenant_id' => 999]);

        $res = $this->get("/{$this->testTenantSlug}/alpha/reviews/{$reviewId}/comments");

        $res->assertStatus(404);
    }

    public function test_blogreviews_store_review_comment_persists(): void
    {
        $user = $this->authenticatedUser();
        $this->enableAlphaFeatures(['reviews']);
        $reviewId = $this->seedReview($this->authorId(), $user->id);

        $res = $this->post("/{$this->testTenantSlug}/alpha/reviews/{$reviewId}/comments", [
            'body' => 'A moderation note on this review',
        ]);

        $res->assertStatus(302);
        $this->assertDatabaseHas('comments', [
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $user->id,
            'target_type' => 'review',
            'target_id'   => $reviewId,
            'content'     => 'A moderation note on this review',
        ]);
    }

    public function test_blogreviews_review_reaction_persists(): void
    {
        $user = $this->authenticatedUser();
        $this->enableAlphaFeatures(['reviews']);
        $reviewId = $this->seedReview($this->authorId(), $user->id);

        $res = $this->post("/{$this->testTenantSlug}/alpha/reviews/{$reviewId}/react", [
            'emoji' => 'celebrate',
        ]);

        $res->assertStatus(302);
        $this->assertDatabaseHas('reactions', [
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $user->id,
            'target_type' => 'review',
            'target_id'   => $reviewId,
            'emoji'       => 'celebrate',
        ]);
    }
}
