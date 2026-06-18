<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Accessible (GOV.UK) frontend — listings parity routes.
 *
 * Covers the listings parity gap added in ListingsParity: gap #12, the
 * owner-only listing analytics dashboard. Mirrors the setUp scrubbing +
 * helpers used by GovukAlphaFrontendTest so it runs the same way inside the
 * full suite. Unique test_listings_ method names throughout.
 */
class ListingsParityTest extends TestCase
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

    // =====================================================================
    // Auth gating — analytics redirects anonymous users to login.
    // =====================================================================

    public function test_listings_analytics_requires_authentication(): void
    {
        $loginPath = "/{$this->testTenantSlug}/alpha/login";

        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $listing = $this->createListing((int) $owner->id);

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings/{$listing->id}/analytics");
        $response->assertRedirect();
        $this->assertStringContainsString($loginPath, $response->headers->get('Location') ?? '');
    }

    // =====================================================================
    // Gap #12 — Owner-only analytics dashboard
    // =====================================================================

    public function test_listings_analytics_renders_for_owner(): void
    {
        $owner = $this->authenticatedUser();
        $listing = $this->createListing((int) $owner->id, ['title' => 'Analytics owner listing']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings/{$listing->id}/analytics");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_listings.analytics.title'));
        $response->assertSee(__('govuk_alpha_listings.analytics.key_metrics_heading'));
        $response->assertSee('Analytics owner listing');
        // The accessible sparkbar tables use the period selector + back link.
        $response->assertSee('class="govuk-back-link"', false);
        $response->assertSee(__('govuk_alpha_listings.analytics.period_legend'));
    }

    public function test_listings_analytics_forbidden_for_non_owner(): void
    {
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $listing = $this->createListing((int) $owner->id);

        // A different, non-admin member cannot see another member's analytics.
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings/{$listing->id}/analytics");
        $response->assertForbidden();
    }

    public function test_listings_analytics_not_found_for_missing_listing(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings/99999001/analytics");
        $response->assertNotFound();
    }

    public function test_listings_analytics_not_found_for_cross_tenant_listing(): void
    {
        // A listing that belongs to a different tenant must 404 (tenant-scoped getById).
        $foreignOwner = User::factory()->forTenant(999)->create(['status' => 'active', 'is_approved' => true]);
        $foreignListing = Listing::factory()->forTenant(999)->create([
            'user_id' => $foreignOwner->id,
            'title' => 'Foreign tenant listing',
            'description' => 'Not visible to the test tenant.',
            'type' => 'offer',
        ]);

        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings/{$foreignListing->id}/analytics");
        $response->assertNotFound();
    }

    public function test_listings_analytics_respects_days_window(): void
    {
        $owner = $this->authenticatedUser();
        $listing = $this->createListing((int) $owner->id);

        // A valid window renders; the chosen radio is selected.
        $response = $this->get("/{$this->testTenantSlug}/alpha/listings/{$listing->id}/analytics?days=7");
        $response->assertOk();
        $response->assertSee(__('govuk_alpha_listings.analytics.period_days', ['count' => 7]));
    }

    // =====================================================================
    // Listing comment thread
    // =====================================================================

    public function test_listings_comments_requires_authentication(): void
    {
        $loginPath = "/{$this->testTenantSlug}/alpha/login";

        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $listing = $this->createListing((int) $owner->id);

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings/{$listing->id}/comments");
        $response->assertRedirect();
        $this->assertStringContainsString($loginPath, $response->headers->get('Location') ?? '');
    }

    public function test_listings_comments_renders_for_member(): void
    {
        $this->authenticatedUser();
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $listing = $this->createListing((int) $owner->id, ['title' => 'Commentable listing']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings/{$listing->id}/comments");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_listings.comments.heading'));
        $response->assertSee(__('govuk_alpha_listings.comments.add_heading'));
        $response->assertSee('Commentable listing');
        $response->assertSee('name="body"', false);
    }

    public function test_listings_comments_not_found_for_missing_listing(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings/99999002/comments");
        $response->assertNotFound();
    }

    public function test_listings_comments_not_found_for_cross_tenant_listing(): void
    {
        $foreignOwner = User::factory()->forTenant(999)->create(['status' => 'active', 'is_approved' => true]);
        $foreignListing = Listing::factory()->forTenant(999)->create([
            'user_id' => $foreignOwner->id,
            'title' => 'Foreign comment listing',
            'description' => 'Not visible to the test tenant.',
            'type' => 'offer',
        ]);

        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings/{$foreignListing->id}/comments");
        $response->assertNotFound();
    }

    public function test_listings_store_comment_persists_and_redirects(): void
    {
        $author = $this->authenticatedUser();
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $listing = $this->createListing((int) $owner->id);

        $response = $this->post("/{$this->testTenantSlug}/alpha/listings/{$listing->id}/comments", [
            'body' => 'A genuinely helpful comment about this listing.',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=comment-added', $response->headers->get('Location') ?? '');

        $this->assertDatabaseHas('comments', [
            'tenant_id' => $this->testTenantId,
            'target_type' => 'listing',
            'target_id' => $listing->id,
            'user_id' => $author->id,
        ]);
    }

    public function test_listings_store_comment_rejects_empty_body(): void
    {
        $this->authenticatedUser();
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $listing = $this->createListing((int) $owner->id);

        $response = $this->post("/{$this->testTenantSlug}/alpha/listings/{$listing->id}/comments", [
            'body' => '   ',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=comment-invalid', $response->headers->get('Location') ?? '');
    }

    public function test_listings_store_comment_requires_authentication(): void
    {
        $loginPath = "/{$this->testTenantSlug}/alpha/login";
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $listing = $this->createListing((int) $owner->id);

        $response = $this->post("/{$this->testTenantSlug}/alpha/listings/{$listing->id}/comments", [
            'body' => 'Should not persist.',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString($loginPath, $response->headers->get('Location') ?? '');
        $this->assertDatabaseMissing('comments', [
            'tenant_id' => $this->testTenantId,
            'target_type' => 'listing',
            'target_id' => $listing->id,
        ]);
    }

    // =====================================================================
    // AI description helper (no-JS round-trip)
    // =====================================================================

    public function test_listings_generate_description_requires_authentication(): void
    {
        $loginPath = "/{$this->testTenantSlug}/alpha/login";

        $response = $this->post("/{$this->testTenantSlug}/alpha/listings/generate-description", [
            'title' => 'A listing title',
            'type' => 'offer',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString($loginPath, $response->headers->get('Location') ?? '');
    }

    public function test_listings_generate_description_requires_title(): void
    {
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/alpha/listings/generate-description", [
            'title' => '',
            'type' => 'offer',
        ]);

        $response->assertRedirect();
        $location = $response->headers->get('Location') ?? '';
        // Empty title cannot produce a suggestion — bounce back to create with the prompt.
        $this->assertStringContainsString('status=ai-title-required', $location);
        $this->assertStringContainsString("/{$this->testTenantSlug}/alpha/listings/new", $location);
    }

    // =====================================================================
    // Listing detail — owner delete control + comments link wiring
    // =====================================================================

    public function test_listings_detail_shows_delete_for_owner(): void
    {
        $owner = $this->authenticatedUser();
        $listing = $this->createListing((int) $owner->id);

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings/{$listing->id}");
        $response->assertOk();
        $response->assertSee(__('govuk_alpha_listings.detail.delete_button'));
        // Owner-gated delete form posts to the existing delete route.
        $deleteAction = route('govuk-alpha.listings.delete', ['tenantSlug' => $this->testTenantSlug, 'id' => $listing->id]);
        $response->assertSee($deleteAction, false);
    }

    public function test_listings_detail_shows_comments_link(): void
    {
        $owner = $this->authenticatedUser();
        $listing = $this->createListing((int) $owner->id);

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings/{$listing->id}");
        $response->assertOk();
        $commentsHref = route('govuk-alpha.listings.comments', ['tenantSlug' => $this->testTenantSlug, 'id' => $listing->id]);
        $response->assertSee($commentsHref, false);
    }

    // =====================================================================
    // Helpers (self-contained, mirroring GovukAlphaFrontendTest)
    // =====================================================================

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    /**
     * @param array<string,mixed> $overrides
     */
    private function createListing(int $userId, array $overrides = []): Listing
    {
        return Listing::factory()->forTenant($this->testTenantId)->create(array_merge([
            'user_id' => $userId,
            'title' => 'Listings parity test listing',
            'description' => 'A listing for the parity analytics tests.',
            'type' => 'offer',
        ], $overrides));
    }
}
