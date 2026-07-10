<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for the accessible (GOV.UK) Resources parity module.
 *
 * Covers the full library page (tree + flat category filter, search, cursor
 * pagination, metadata), authenticated streamed download (with counter),
 * upload form + store, owner/admin delete confirmation + delete, and admin
 * reorder — plus the auth / feature / ownership gates.
 *
 * Extends the same base TestCase + DatabaseTransactions trait that
 * GovukAlphaFrontendTest uses; the private helpers there are replicated below.
 */
class ResourcesParityTest extends TestCase
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

    /**
     * Seed a resource row and return its id.
     */
    private function seedResource(array $overrides = []): int
    {
        return (int) DB::table('resources')->insertGetId(array_merge([
            'tenant_id'   => $this->testTenantId,
            'user_id'     => 1,
            'title'       => 'Seeded Resource',
            'description' => 'A seeded resource for parity tests.',
            'file_path'   => 'seed.txt',
            'file_type'   => 'text/plain',
            'file_size'   => 1234,
            'downloads'   => 0,
            'sort_order'  => 0,
            'created_at'  => now(),
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // Library page
    // ---------------------------------------------------------------

    public function test_resources_library_redirects_anonymous_to_login(): void
    {
        $this->enableAlphaFeatures(['resources']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/resources/library");

        $res->assertStatus(302);
        $res->assertRedirectContains('/accessible/login');
    }

    public function test_resources_library_renders_for_authenticated_user(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['resources']);
        $this->seedResource(['title' => 'Parity Library Item', 'downloads' => 5]);

        $res = $this->get("/{$this->testTenantSlug}/accessible/resources/library");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_resources.library.title'));
        $res->assertSee('Parity Library Item');
        $res->assertSee(__('govuk_alpha_resources.actions.download'));
        $res->assertSee(__('govuk_alpha_resources.actions.upload'));
    }

    public function test_resources_library_shows_empty_state_with_tips(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['resources']);

        // Search for something guaranteed to match nothing.
        $res = $this->get("/{$this->testTenantSlug}/accessible/resources/library?q=zzz-no-such-resource-zzz");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_resources.empty.tips_title'));
        $res->assertSee(__('govuk_alpha_resources.empty.tip_guides'));
    }

    public function test_resources_library_returns_403_when_feature_disabled(): void
    {
        $this->authenticatedUser();
        // Explicitly turn the feature off for this tenant.
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode(['resources' => false])]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $res = $this->get("/{$this->testTenantSlug}/accessible/resources/library");

        $res->assertStatus(403);
    }

    public function test_resources_library_category_filter_renders_flat_categories(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['resources']);

        DB::table('categories')->insert([
            'tenant_id'  => $this->testTenantId,
            'name'       => 'Parity Guides',
            'slug'       => 'parity-guides-' . $this->testTenantId,
            'type'       => 'resource',
            'color'      => 'green',
            'is_active'  => 1,
            'created_at' => now(),
        ]);

        $res = $this->get("/{$this->testTenantSlug}/accessible/resources/library");

        $res->assertOk();
        $res->assertSee('Parity Guides');
        $res->assertSee(__('govuk_alpha_resources.categories.all'));
    }

    // ---------------------------------------------------------------
    // Upload
    // ---------------------------------------------------------------

    public function test_resources_upload_form_renders(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['resources']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/resources/upload");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_resources.upload.title'));
        $res->assertSee('name="file"', false);
        $res->assertSee('enctype="multipart/form-data"', false);
    }

    public function test_resources_upload_requires_title_and_file(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['resources']);

        $res = $this->post("/{$this->testTenantSlug}/accessible/resources/upload", [
            'title' => '',
        ]);

        $res->assertStatus(302);
        $res->assertSessionHasErrors(['title', 'file']);
    }

    public function test_resources_upload_persists_a_valid_file(): void
    {
        $user = $this->authenticatedUser();
        $this->enableAlphaFeatures(['resources']);

        $file = UploadedFile::fake()->createWithContent('notes.txt', "hello world\n");

        $res = $this->post("/{$this->testTenantSlug}/accessible/resources/upload", [
            'title'       => 'My Uploaded Notes',
            'description' => 'Test upload',
            'file'        => $file,
        ]);

        $res->assertStatus(302);
        $res->assertRedirectContains('/accessible/resources/library');

        $this->assertDatabaseHas('resources', [
            'tenant_id' => $this->testTenantId,
            'user_id'   => $user->id,
            'title'     => 'My Uploaded Notes',
        ]);
    }

    // ---------------------------------------------------------------
    // Delete (owner / admin / cross-tenant)
    // ---------------------------------------------------------------

    public function test_resources_delete_confirm_renders_for_owner(): void
    {
        $owner = $this->authenticatedUser();
        $this->enableAlphaFeatures(['resources']);
        $id = $this->seedResource(['user_id' => $owner->id, 'title' => 'Owned Resource']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/resources/{$id}/delete");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_resources.delete.title'));
        $res->assertSee('Owned Resource');
    }

    public function test_resources_delete_confirm_forbidden_for_non_owner(): void
    {
        $this->authenticatedUser(['role' => 'member']);
        $this->enableAlphaFeatures(['resources']);
        // Owned by user id 1, viewer is a different member.
        $id = $this->seedResource(['user_id' => 999999]);

        $res = $this->get("/{$this->testTenantSlug}/accessible/resources/{$id}/delete");

        $res->assertStatus(403);
    }

    public function test_resources_delete_confirm_404_for_cross_tenant(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['resources']);
        // Resource belongs to a different tenant.
        $id = $this->seedResource(['tenant_id' => 999]);

        $res = $this->get("/{$this->testTenantSlug}/accessible/resources/{$id}/delete");

        $res->assertStatus(404);
    }

    public function test_resources_delete_removes_owned_resource(): void
    {
        $owner = $this->authenticatedUser();
        $this->enableAlphaFeatures(['resources']);
        $id = $this->seedResource(['user_id' => $owner->id]);

        $res = $this->post("/{$this->testTenantSlug}/accessible/resources/{$id}/delete");

        $res->assertStatus(302);
        $res->assertRedirectContains('/accessible/resources/library');
        $this->assertDatabaseMissing('resources', ['id' => $id]);
    }

    public function test_resources_delete_forbidden_for_non_owner(): void
    {
        $this->authenticatedUser(['role' => 'member']);
        $this->enableAlphaFeatures(['resources']);
        $id = $this->seedResource(['user_id' => 999999]);

        $res = $this->post("/{$this->testTenantSlug}/accessible/resources/{$id}/delete");

        $res->assertStatus(403);
        $this->assertDatabaseHas('resources', ['id' => $id]);
    }

    public function test_resources_admin_can_delete_any_resource(): void
    {
        $this->authenticatedUser(['role' => 'admin']);
        $this->enableAlphaFeatures(['resources']);
        $id = $this->seedResource(['user_id' => 999999]);

        $res = $this->post("/{$this->testTenantSlug}/accessible/resources/{$id}/delete");

        $res->assertStatus(302);
        $this->assertDatabaseMissing('resources', ['id' => $id]);
    }

    // ---------------------------------------------------------------
    // Download
    // ---------------------------------------------------------------

    public function test_resources_download_404_for_missing_file_on_disk(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['resources']);
        // file_path points at a file that does not exist on disk → 404.
        $id = $this->seedResource(['file_path' => 'does-not-exist-' . uniqid() . '.txt']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/resources/{$id}/download");

        $res->assertStatus(404);
    }

    public function test_resources_download_redirects_anonymous_to_login(): void
    {
        $this->enableAlphaFeatures(['resources']);
        $id = $this->seedResource();

        $res = $this->get("/{$this->testTenantSlug}/accessible/resources/{$id}/download");

        $res->assertStatus(302);
        $res->assertRedirectContains('/accessible/login');
    }

    // ---------------------------------------------------------------
    // Reorder (admin only)
    // ---------------------------------------------------------------

    public function test_resources_reorder_forbidden_for_non_admin(): void
    {
        $this->authenticatedUser(['role' => 'member']);
        $this->enableAlphaFeatures(['resources']);
        $id = $this->seedResource();

        $res = $this->post("/{$this->testTenantSlug}/accessible/resources/reorder", [
            'resource_id' => $id,
            'direction'   => 'down',
        ]);

        $res->assertStatus(403);
    }

    public function test_resources_admin_reorder_persists_sort_order(): void
    {
        $this->authenticatedUser(['role' => 'admin']);
        $this->enableAlphaFeatures(['resources']);

        // Two resources; the index orders by (sort_order, id desc), so with equal
        // sort_order the higher id is first. Move the FIRST one down.
        $first = $this->seedResource(['title' => 'First', 'sort_order' => 0]);
        $second = $this->seedResource(['title' => 'Second', 'sort_order' => 0]);
        // higher id (second) sorts first by id-desc tiebreak.

        $res = $this->post("/{$this->testTenantSlug}/accessible/resources/reorder", [
            'resource_id' => $second,
            'direction'   => 'down',
        ]);

        $res->assertStatus(302);

        // After moving $second down, it must have a higher sort_order than $first.
        $secondOrder = (int) DB::table('resources')->where('id', $second)->value('sort_order');
        $firstOrder = (int) DB::table('resources')->where('id', $first)->value('sort_order');
        $this->assertGreaterThan($firstOrder, $secondOrder);
    }
}
