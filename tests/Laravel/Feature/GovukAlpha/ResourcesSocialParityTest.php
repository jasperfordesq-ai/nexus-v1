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
 * Feature tests for accessible (GOV.UK) per-resource social interactions.
 *
 * Covers: like/unlike toggle, comment add, comment delete (owner-gated),
 * auth requirement for mutations, and count rendering on the library page.
 *
 * All method names are prefixed test_resources_social_*.
 */
class ResourcesSocialParityTest extends TestCase
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
    // Helpers
    // ---------------------------------------------------------------

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status'      => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function enableResources(): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['resources'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function seedResource(int $userId, array $overrides = []): int
    {
        return (int) DB::table('resources')->insertGetId(array_merge([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $userId,
            'title'       => 'Parity Test Resource',
            'description' => 'A resource for social interaction parity tests.',
            'file_path'   => 'parity-test-' . uniqid() . '.pdf',
            'file_type'   => 'application/pdf',
            'file_size'   => 1024,
            'downloads'   => 0,
            'created_at'  => now(),
        ], $overrides));
    }

    private function seedComment(int $resourceId, int $userId, string $content = 'A parity comment.'): int
    {
        return (int) DB::table('comments')->insertGetId([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $userId,
            'target_type' => 'resource',
            'target_id'   => $resourceId,
            'parent_id'   => null,
            'content'     => $content,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    private function seedReaction(int $resourceId, int $userId, string $emoji = 'like'): void
    {
        DB::table('reactions')->insertOrIgnore([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $userId,
            'target_type' => 'resource',
            'target_id'   => $resourceId,
            'emoji'       => $emoji,
            'created_at'  => now(),
        ]);
    }

    // ---------------------------------------------------------------
    // Auth guard — all mutation endpoints redirect anonymous users
    // ---------------------------------------------------------------

    public function test_resources_social_comments_page_redirects_anonymous_to_login(): void
    {
        $this->enableResources();
        $user = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $resourceId = $this->seedResource($user->id);

        $res = $this->get("/{$this->testTenantSlug}/alpha/resources/{$resourceId}/comments");

        $res->assertStatus(302);
        $res->assertRedirectContains('/alpha/login');
    }

    public function test_resources_social_react_redirects_anonymous_to_login(): void
    {
        $this->enableResources();
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $resourceId = $this->seedResource($owner->id);

        $res = $this->post("/{$this->testTenantSlug}/alpha/resources/{$resourceId}/react", ['emoji' => 'like']);

        $res->assertStatus(302);
        $res->assertRedirectContains('/alpha/login');
    }

    // ---------------------------------------------------------------
    // Feature gate
    // ---------------------------------------------------------------

    public function test_resources_social_comments_403_when_feature_disabled(): void
    {
        $user = $this->authenticatedUser();
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode(['resources' => false])]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
        $resourceId = $this->seedResource($user->id);

        $res = $this->get("/{$this->testTenantSlug}/alpha/resources/{$resourceId}/comments");

        $res->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Comment thread page renders correctly
    // ---------------------------------------------------------------

    public function test_resources_social_comments_page_renders_thread(): void
    {
        $user = $this->authenticatedUser();
        $this->enableResources();
        $resourceId = $this->seedResource($user->id);
        $this->seedComment($resourceId, $user->id, 'A visible parity comment on resource');

        $res = $this->get("/{$this->testTenantSlug}/alpha/resources/{$resourceId}/comments");

        $res->assertOk();
        $res->assertSee('A visible parity comment on resource');
        $res->assertSee(__('govuk_alpha_resources.social.comments_heading'));
        $res->assertSee(__('govuk_alpha_resources.social.add_comment_heading'));
        $res->assertSee(__('govuk_alpha_resources.social.reactions_heading'));
    }

    // ---------------------------------------------------------------
    // Like toggle persists
    // ---------------------------------------------------------------

    public function test_resources_social_like_toggle_adds_reaction(): void
    {
        $user = $this->authenticatedUser();
        $this->enableResources();
        $resourceId = $this->seedResource($user->id);

        $res = $this->post("/{$this->testTenantSlug}/alpha/resources/{$resourceId}/react", ['emoji' => 'like']);

        $res->assertStatus(302);
        $this->assertDatabaseHas('reactions', [
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $user->id,
            'target_type' => 'resource',
            'target_id'   => $resourceId,
            'emoji'       => 'like',
        ]);
    }

    public function test_resources_social_like_toggle_removes_existing_reaction(): void
    {
        $user = $this->authenticatedUser();
        $this->enableResources();
        $resourceId = $this->seedResource($user->id);
        $this->seedReaction($resourceId, $user->id, 'like');

        // Toggle again — same emoji should remove it.
        $res = $this->post("/{$this->testTenantSlug}/alpha/resources/{$resourceId}/react", ['emoji' => 'like']);

        $res->assertStatus(302);
        $this->assertDatabaseMissing('reactions', [
            'user_id'     => $user->id,
            'target_type' => 'resource',
            'target_id'   => $resourceId,
        ]);
    }

    // ---------------------------------------------------------------
    // Comment add persists
    // ---------------------------------------------------------------

    public function test_resources_social_comment_add_persists(): void
    {
        $user = $this->authenticatedUser();
        $this->enableResources();
        $resourceId = $this->seedResource($user->id);

        $res = $this->post("/{$this->testTenantSlug}/alpha/resources/{$resourceId}/comments/add", [
            'body' => 'My new resource comment',
        ]);

        $res->assertStatus(302);
        $this->assertDatabaseHas('comments', [
            'tenant_id'   => $this->testTenantId,
            'user_id'     => $user->id,
            'target_type' => 'resource',
            'target_id'   => $resourceId,
            'content'     => 'My new resource comment',
        ]);
    }

    public function test_resources_social_comment_add_rejects_empty_body(): void
    {
        $user = $this->authenticatedUser();
        $this->enableResources();
        $resourceId = $this->seedResource($user->id);

        $res = $this->post("/{$this->testTenantSlug}/alpha/resources/{$resourceId}/comments/add", [
            'body' => '   ',
        ]);

        $res->assertStatus(302);
        $res->assertRedirectContains('status=comment-invalid');
    }

    // ---------------------------------------------------------------
    // Comment delete is owner-gated
    // ---------------------------------------------------------------

    public function test_resources_social_comment_delete_owner_succeeds(): void
    {
        $user = $this->authenticatedUser();
        $this->enableResources();
        $resourceId = $this->seedResource($user->id);
        $commentId = $this->seedComment($resourceId, $user->id, 'To be deleted');

        $res = $this->post("/{$this->testTenantSlug}/alpha/resources/{$resourceId}/comments/{$commentId}/delete");

        $res->assertStatus(302);
        $res->assertRedirectContains('status=comment-deleted');
        // Comments are soft-deleted (deleted_at set), so the row persists.
        $this->assertSoftDeleted('comments', ['id' => $commentId]);
    }

    public function test_resources_social_comment_delete_non_owner_cannot_delete(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $actor = $this->authenticatedUser(); // a different user
        $this->enableResources();
        $resourceId = $this->seedResource($owner->id);
        $commentId = $this->seedComment($resourceId, $owner->id, 'Protected comment');

        $res = $this->post("/{$this->testTenantSlug}/alpha/resources/{$resourceId}/comments/{$commentId}/delete");

        // CommentService::delete is owner-scoped — returns 0 rows deleted → comment-delete-failed redirect.
        $res->assertStatus(302);
        $res->assertRedirectContains('status=comment-delete-failed');
        $this->assertDatabaseHas('comments', ['id' => $commentId]);
    }

    // ---------------------------------------------------------------
    // Counts render on the library page
    // ---------------------------------------------------------------

    public function test_resources_social_counts_render_on_library_page(): void
    {
        $user = $this->authenticatedUser();
        $this->enableResources();
        $resourceId = $this->seedResource($user->id);
        $this->seedComment($resourceId, $user->id, 'Library count comment');
        $this->seedReaction($resourceId, $user->id, 'like');

        $res = $this->get("/{$this->testTenantSlug}/alpha/resources/library");

        $res->assertOk();
        // Like form should be present for the resource.
        $res->assertSee(__('govuk_alpha_resources.social.like'));
        // Comment count link text ("1 comment") should appear.
        $res->assertSee('1');
        $res->assertSee('comment');
    }
}
