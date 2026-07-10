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
 * Feature tests for the accessible (GOV.UK) Search parity module.
 *
 * Covers the advanced search page (auth + feature gates, filters render,
 * result thumbnails, SQL-path results), the saved-search lifecycle (list,
 * save, run, delete with a confirmation step) and the owner / cross-tenant
 * authorisation gates on each saved-search action.
 *
 * Extends the same base TestCase + DatabaseTransactions trait that
 * GovukAlphaFrontendTest uses; the private helpers there are replicated below.
 */
class SearchParityTest extends TestCase
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

    private function disableMeiliSearch(): void
    {
        $prop = new \ReflectionProperty(\App\Services\SearchService::class, 'available');
        $prop->setAccessible(true);
        $prop->setValue(null, false);
    }

    /**
     * Seed an active, approved listing and return its id.
     */
    private function seedListing(int $userId, array $overrides = []): int
    {
        return (int) DB::table('listings')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'title' => 'Zebra Carpentry Helper',
            'description' => 'I can help with carpentry and woodworking.',
            'type' => 'offer',
            'status' => 'active',
            'moderation_status' => 'approved',
            'image_url' => '/uploads/listings/zebra-thumb.jpg',
            'hours_estimate' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /**
     * Seed a saved search row and return its id.
     */
    private function seedSavedSearch(int $userId, array $overrides = []): int
    {
        return (int) DB::table('saved_searches')->insertGetId(array_merge([
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'name' => 'My carpentry search',
            'query_params' => json_encode(['q' => 'carpentry', 'type' => 'listings']),
            'notify_on_new' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // Advanced search page — gates
    // ---------------------------------------------------------------

    public function test_search_advanced_redirects_anonymous_to_login(): void
    {
        $this->enableAlphaFeatures(['search']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/search/advanced");

        $res->assertStatus(302);
        $res->assertRedirectContains('/accessible/login');
    }

    public function test_search_advanced_renders_filters_for_authenticated_user(): void
    {
        $this->enableAlphaFeatures(['search']);
        $this->authenticatedUser();

        $res = $this->get("/{$this->testTenantSlug}/accessible/search/advanced");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_search.advanced.title'));
        // Every advanced filter control is present.
        $res->assertSee(__('govuk_alpha_search.filters.content_type'));
        $res->assertSee(__('govuk_alpha_search.filters.sort_by'));
        $res->assertSee(__('govuk_alpha_search.filters.date_from'));
        $res->assertSee(__('govuk_alpha_search.filters.location'));
        $res->assertSee(__('govuk_alpha_search.filters.skills'));
        $res->assertSee('name="category_id"', false);
        // Empty-query initial state.
        $res->assertSee(__('govuk_alpha_search.states.no_query_title'));
    }

    // ---------------------------------------------------------------
    // Advanced search page — results
    // ---------------------------------------------------------------

    public function test_search_advanced_returns_matching_listing_with_thumbnail(): void
    {
        $this->enableAlphaFeatures(['search']);
        $this->disableMeiliSearch();
        $user = $this->authenticatedUser();
        $this->seedListing($user->id);

        $res = $this->get("/{$this->testTenantSlug}/accessible/search/advanced?q=Zebra");

        $res->assertOk();
        $res->assertSee('Zebra Carpentry Helper');
        // Listing thumbnail is rendered (the gap this module closed).
        $res->assertSee('/uploads/listings/zebra-thumb.jpg', false);
        $res->assertSee(__('govuk_alpha_search.results.view_listing'));
    }

    public function test_search_advanced_shows_empty_state_for_no_match(): void
    {
        $this->enableAlphaFeatures(['search']);
        $this->disableMeiliSearch();
        $this->authenticatedUser();

        $res = $this->get("/{$this->testTenantSlug}/accessible/search/advanced?q=ZZnomatchquery");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_search.states.empty_title'));
    }

    public function test_search_advanced_active_filter_count_badge_shows(): void
    {
        $this->enableAlphaFeatures(['search']);
        $this->disableMeiliSearch();
        $this->authenticatedUser();

        // type + sort = 2 active filters.
        $res = $this->get("/{$this->testTenantSlug}/accessible/search/advanced?q=help&type=listings&sort=newest");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_search.filters.summary_with_count', ['count' => 2]));
    }

    // ---------------------------------------------------------------
    // Saved searches — list + save
    // ---------------------------------------------------------------

    public function test_search_advanced_lists_saved_searches(): void
    {
        $this->enableAlphaFeatures(['search']);
        $user = $this->authenticatedUser();
        $this->seedSavedSearch($user->id, ['name' => 'Find a gardener']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/search/advanced");

        $res->assertOk();
        $res->assertSee('Find a gardener');
        $res->assertSee(__('govuk_alpha_search.saved.run'));
    }

    public function test_search_save_search_persists_for_the_owner(): void
    {
        $this->enableAlphaFeatures(['search']);
        $user = $this->authenticatedUser();

        $res = $this->post("/{$this->testTenantSlug}/accessible/search/saved", [
            'name' => 'Plumbing offers',
            'q' => 'plumbing',
            'type' => 'listings',
            'sort' => 'newest',
        ]);

        $res->assertStatus(302);
        $res->assertRedirectContains('status=search-saved');

        $this->assertDatabaseHas('saved_searches', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'name' => 'Plumbing offers',
        ]);
    }

    public function test_search_save_search_rejects_missing_name(): void
    {
        $this->enableAlphaFeatures(['search']);
        $user = $this->authenticatedUser();

        $res = $this->post("/{$this->testTenantSlug}/accessible/search/saved", [
            'name' => '',
            'q' => 'plumbing',
        ]);

        $res->assertStatus(302);
        $res->assertRedirectContains('status=search-save-failed');

        $this->assertDatabaseMissing('saved_searches', [
            'tenant_id' => $this->testTenantId,
            'user_id' => $user->id,
            'query_params' => json_encode(['q' => 'plumbing']),
        ]);
    }

    // ---------------------------------------------------------------
    // Saved searches — run
    // ---------------------------------------------------------------

    public function test_search_run_saved_redirects_with_params_and_records_run(): void
    {
        $this->enableAlphaFeatures(['search']);
        $user = $this->authenticatedUser();
        $id = $this->seedSavedSearch($user->id, [
            'query_params' => json_encode(['q' => 'carpentry', 'type' => 'listings']),
        ]);

        $res = $this->post("/{$this->testTenantSlug}/accessible/search/saved/{$id}/run");

        $res->assertStatus(302);
        $res->assertRedirectContains('q=carpentry');
        $res->assertRedirectContains('type=listings');

        $row = DB::table('saved_searches')->where('id', $id)->first();
        $this->assertNotNull($row->last_run_at);
    }

    public function test_search_run_saved_404_for_cross_tenant(): void
    {
        $this->enableAlphaFeatures(['search']);
        $this->authenticatedUser();
        // Saved search belongs to a different tenant.
        $id = (int) DB::table('saved_searches')->insertGetId([
            'tenant_id' => 9999,
            'user_id' => 1,
            'name' => 'Other tenant search',
            'query_params' => json_encode(['q' => 'x']),
            'notify_on_new' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->post("/{$this->testTenantSlug}/accessible/search/saved/{$id}/run");

        $res->assertStatus(404);
    }

    public function test_search_run_saved_403_for_non_owner(): void
    {
        $this->enableAlphaFeatures(['search']);
        $owner = $this->authenticatedUser();
        $id = $this->seedSavedSearch($owner->id);

        // A different user in the same tenant tries to run it.
        $this->authenticatedUser();

        $res = $this->post("/{$this->testTenantSlug}/accessible/search/saved/{$id}/run");

        $res->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Saved searches — delete (confirm + execute)
    // ---------------------------------------------------------------

    public function test_search_delete_saved_confirm_renders_for_owner(): void
    {
        $this->enableAlphaFeatures(['search']);
        $user = $this->authenticatedUser();
        $id = $this->seedSavedSearch($user->id, ['name' => 'Delete me']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/search/saved/{$id}/delete");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_search.saved.delete_title'));
        $res->assertSee('Delete me');
        // GOV.UK destructive-action warning is present.
        $res->assertSee('govuk-warning-text', false);
    }

    public function test_search_delete_saved_confirm_403_for_non_owner(): void
    {
        $this->enableAlphaFeatures(['search']);
        $owner = $this->authenticatedUser();
        $id = $this->seedSavedSearch($owner->id);

        $this->authenticatedUser();

        $res = $this->get("/{$this->testTenantSlug}/accessible/search/saved/{$id}/delete");

        $res->assertStatus(403);
    }

    public function test_search_delete_saved_removes_the_row(): void
    {
        $this->enableAlphaFeatures(['search']);
        $user = $this->authenticatedUser();
        $id = $this->seedSavedSearch($user->id);

        $res = $this->post("/{$this->testTenantSlug}/accessible/search/saved/{$id}/delete");

        $res->assertStatus(302);
        $res->assertRedirectContains('status=search-deleted');

        $this->assertDatabaseMissing('saved_searches', ['id' => $id]);
    }

    public function test_search_delete_saved_404_for_cross_tenant(): void
    {
        $this->enableAlphaFeatures(['search']);
        $this->authenticatedUser();
        $id = (int) DB::table('saved_searches')->insertGetId([
            'tenant_id' => 9999,
            'user_id' => 1,
            'name' => 'Other tenant search',
            'query_params' => json_encode(['q' => 'x']),
            'notify_on_new' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->post("/{$this->testTenantSlug}/accessible/search/saved/{$id}/delete");

        $res->assertStatus(404);
    }
}
