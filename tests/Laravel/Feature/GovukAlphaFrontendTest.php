<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature;

use App\Models\FeedActivity;
use App\Models\FeedPost;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

class GovukAlphaFrontendTest extends TestCase
{
    use DatabaseTransactions;

    public function test_home_login_and_register_pages_render_for_tenant(): void
    {
        foreach (['/alpha', '/alpha/login', '/alpha/register'] as $path) {
            $response = $this->get("/{$this->testTenantSlug}{$path}");

            $response->assertOk();
            $response->assertHeader('content-type', 'text/html; charset=UTF-8');
            $response->assertSee('Project NEXUS Accessible');
            $response->assertSee('class="govuk-skip-link"', false);
            $response->assertSee('class="govuk-phase-banner"', false);
            $response->assertSee('AGPL-3.0-or-later');
        }
    }

    public function test_feed_page_renders_govuk_alpha_shell_and_feed_item(): void
    {
        $user = $this->authenticatedUser();
        $post = FeedPost::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'content' => 'Alpha feed verification post',
            'visibility' => 'public',
        ]);
        FeedActivity::factory()->forTenant($this->testTenantId)->create([
            'source_type' => 'post',
            'source_id' => $post->id,
            'user_id' => $user->id,
            'content' => 'Alpha feed verification post',
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/feed");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $response->assertSee('class="govuk-skip-link"', false);
        $response->assertSee('id="main-content"', false);
        $response->assertSee('class="govuk-phase-banner"', false);
        $response->assertSee('AGPL-3.0-or-later');
        $response->assertSee('Alpha feed verification post');
    }

    public function test_listings_page_renders_filters_results_and_tenant_isolation(): void
    {
        $user = $this->authenticatedUser();
        $this->ensureListingCategory();
        Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'title' => 'Alpha listing verification',
            'description' => 'Visible listing for GOV.UK alpha.',
            'type' => 'offer',
            'category_id' => 1,
        ]);
        Listing::factory()->forTenant(999)->create([
            'title' => 'Other tenant alpha listing',
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings");

        $response->assertOk();
        $response->assertSee('class="govuk-phase-banner"', false);
        $response->assertSee('Alpha listing verification');
        $response->assertDontSee('Other tenant alpha listing');
    }

    public function test_listing_detail_page_renders_summary(): void
    {
        $user = $this->authenticatedUser();
        $listing = Listing::factory()->forTenant($this->testTenantId)->create([
            'user_id' => $user->id,
            'title' => 'Alpha detail listing',
            'description' => 'Detail page description.',
            'type' => 'request',
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/listings/{$listing->id}");

        $response->assertOk();
        $response->assertSee('Alpha detail listing');
        $response->assertSee('class="govuk-summary-list"', false);
    }

    public function test_members_page_renders_directory_for_authenticated_user(): void
    {
        $viewer = $this->authenticatedUser(['name' => 'Viewer Member']);
        User::factory()->forTenant($this->testTenantId)->create([
            'name' => 'Alpha Directory Member',
            'first_name' => 'Alpha',
            'last_name' => 'Member',
            'status' => 'active',
            'is_approved' => true,
            'privacy_search' => true,
        ]);
        User::factory()->forTenant(999)->create([
            'name' => 'Other Tenant Member',
        ]);

        Sanctum::actingAs($viewer, ['*']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/members?q=Alpha%20Directory%20Member");

        $response->assertOk();
        $response->assertSee('Alpha Directory Member');
        $response->assertDontSee('Other Tenant Member');
        $response->assertSee('AGPL-3.0-or-later');
    }

    public function test_members_page_has_html_auth_required_state_when_unauthenticated(): void
    {
        $response = $this->get("/{$this->testTenantSlug}/alpha/members");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.states.auth_required'));
        $response->assertSee('class="govuk-notification-banner"', false);
    }

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function ensureListingCategory(): void
    {
        DB::table('categories')->insertOrIgnore([
            'id' => 1,
            'tenant_id' => $this->testTenantId,
            'name' => 'General',
            'slug' => 'general',
            'type' => 'listing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
