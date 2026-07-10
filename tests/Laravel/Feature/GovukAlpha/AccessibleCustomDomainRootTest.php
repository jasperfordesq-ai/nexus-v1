<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\GovukAlpha;

use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * The accessible (GOV.UK) frontend must behave like the React custom domains:
 * on a tenant's dedicated accessible custom domain the tenant is resolved by
 * the HOST and every URL is slug-less (bare paths). The tenant slug only appears
 * for tenants WITHOUT a custom domain (the shared /{tenantSlug}/accessible routes).
 *
 * The request is driven through the tenant's accessible_domain host so the
 * ResolveTenant middleware resolves it exactly as in production.
 */
class AccessibleCustomDomainRootTest extends TestCase
{
    use DatabaseTransactions;

    private const ACCESSIBLE_HOST = 'accessible-test.nexus.test';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['HTTP_X_TENANT_ID', 'HTTP_X_TENANT_SLUG', 'HTTP_AUTHORIZATION'] as $serverKey) {
            unset($_SERVER[$serverKey]);
        }

        // Give the test tenant a dedicated accessible custom domain so the
        // ResolveTenant middleware resolves it by host, like accessible-uk.timebank.global.
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'accessible_domain' => self::ACCESSIBLE_HOST,
        ]);

        TenantContext::reset();
    }

    private function getOnAccessibleDomain(string $uri)
    {
        return $this->get('http://' . self::ACCESSIBLE_HOST . $uri);
    }

    private function slugPrefix(): string
    {
        return '/' . $this->testTenantSlug . '/accessible';
    }

    public function test_root_renders_home_with_no_slug_in_the_page(): void
    {
        $response = $this->getOnAccessibleDomain('/');

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.home.title'));
        // No /{slug}/accessible anywhere — every link is slug-less.
        $response->assertDontSee($this->slugPrefix());
    }

    public function test_bare_login_path_works_and_is_slug_less(): void
    {
        // No slug, no /accessible — just like a React custom domain.
        $response = $this->getOnAccessibleDomain('/login');

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.auth.login_title'));
        $response->assertDontSee($this->slugPrefix());
    }

    public function test_bare_register_path_works_and_is_slug_less(): void
    {
        $response = $this->getOnAccessibleDomain('/register');

        $response->assertOk();
        $response->assertDontSee($this->slugPrefix());
    }

    public function test_slug_routes_still_carry_the_slug_without_a_custom_domain(): void
    {
        // Default (non-accessible) host → the canonical slug route still works and
        // its links KEEP the slug (the behaviour is unchanged for tenants with no
        // custom accessible domain).
        $response = $this->get($this->slugPrefix() . '/login');

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.auth.login_title'));
        $response->assertSee($this->slugPrefix(), false);
    }

    public function test_bare_paths_do_not_serve_on_a_non_custom_host(): void
    {
        // The slug-less host routes are gated to accessible custom domains. On any
        // other host the bare path must NOT render the accessible login page.
        $response = $this->get('/login');

        $this->assertNotSame(200, $response->getStatusCode());
    }
}
