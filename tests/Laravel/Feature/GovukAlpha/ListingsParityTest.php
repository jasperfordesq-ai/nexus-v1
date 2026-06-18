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
