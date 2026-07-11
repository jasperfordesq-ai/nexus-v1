<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Regression coverage for the accessible frontend's member-identity boundary.
 */
class PublicIdentityAuthBoundaryTest extends TestCase
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

    public function test_anonymous_identity_bearing_routes_redirect_before_rendering(): void
    {
        $paths = [
            '/kb',
            '/kb/1',
            '/blog/privacy-boundary-post/comments',
            '/events',
            '/events/browse',
            '/events/1',
            '/events/1/map',
            '/volunteering',
            '/volunteering?tab=community_projects',
            '/volunteering/donations',
            '/volunteering/opportunities/1',
            '/feed',
            '/feed/posts/1',
            '/feed/hashtags',
            '/feed/hashtag/privacy',
            '/feed/item/post/1',
            '/listings',
            '/listings/1',
            '/listings/1/comments',
            '/listings/1/analytics',
            '/explore',
            '/groups',
            '/groups/1',
            '/groups/1/files',
            '/jobs',
            '/jobs/1',
            '/jobs/employers/1',
            '/courses',
            '/courses/1',
            '/courses/mine',
            '/podcasts',
            '/podcasts/1',
            '/podcasts/studio',
            '/marketplace',
            '/marketplace/1',
            '/marketplace/seller/1',
            '/resources',
            '/resources/library',
            '/organisations',
            '/organisations/1',
            '/organisations/browse',
            '/ideation',
            '/ideation/1',
            '/ideation/campaigns',
        ];

        $login = "/{$this->testTenantSlug}/accessible/login?status=auth-required";
        foreach ($paths as $path) {
            $response = $this->get("/{$this->testTenantSlug}/accessible{$path}");

            $response->assertRedirect($login);
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
            $response->assertDontSee('PRIVACY-BOUNDARY-SENTINEL');
        }
    }

    public function test_public_legal_auth_and_help_allowlist_remains_available(): void
    {
        foreach (['', '/login', '/register', '/help', '/legal/privacy'] as $path) {
            $this->get("/{$this->testTenantSlug}/accessible{$path}")->assertOk();
        }
    }

    public function test_authentication_boundary_preserves_the_intended_route(): void
    {
        $path = "/{$this->testTenantSlug}/accessible/listings/42";

        $this->get($path)->assertRedirect(
            "/{$this->testTenantSlug}/accessible/login?status=auth-required"
        );

        $this->assertStringEndsWith($path, (string) session('url.intended'));
    }

    public function test_active_approved_tenant_member_passes_the_boundary(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->get("/{$this->testTenantSlug}/accessible/feed/hashtags");

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_suspended_member_session_is_rejected(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'suspended',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($user, ['*']);

        $this->get("/{$this->testTenantSlug}/accessible/listings")
            ->assertRedirect("/{$this->testTenantSlug}/accessible/login?status=auth-required");
    }
}
