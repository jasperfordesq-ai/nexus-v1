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
 * Regression: a tenant's dedicated accessible (GOV.UK) custom domain must serve
 * the accessible home AT THE CLEAN ROOT, not 302-redirect to the slug-prefixed
 * /{slug}/alpha canonical.
 *
 * Before the fix, hitting a configured accessible custom domain
 * (e.g. https://accessible-uk.timebank.global/) bounced to
 * https://accessible-uk.timebank.global/timebanking-org/alpha — dumping the
 * internal tenant slug into the address bar of the clean custom domain the
 * administrator configured. On a host-resolved domain the host already
 * identifies the tenant, so the entry points now render the page in place.
 *
 * The request is driven through the tenant's accessible_domain host so the
 * ResolveTenant middleware resolves the tenant exactly as it does in production.
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
        // ResolveTenant middleware resolves it by host, just like a real
        // accessible-uk.timebank.global request.
        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'accessible_domain' => self::ACCESSIBLE_HOST,
        ]);

        TenantContext::reset();
    }

    private function getOnAccessibleDomain(string $uri)
    {
        return $this->get('http://' . self::ACCESSIBLE_HOST . $uri);
    }

    public function test_root_renders_accessible_home_instead_of_redirecting_to_slug_path(): void
    {
        $response = $this->getOnAccessibleDomain('/');

        // Was a 302 to /{slug}/alpha; must now render the home in place.
        $response->assertOk();
        $response->assertSee(__('govuk_alpha.home.title'));
        $response->assertDontSee(__('govuk_alpha.tenant_chooser.title'));
    }

    public function test_host_alpha_entry_renders_home_instead_of_redirecting(): void
    {
        $response = $this->getOnAccessibleDomain('/alpha');

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.home.title'));
    }

    public function test_host_alpha_login_renders_login_in_place(): void
    {
        $response = $this->getOnAccessibleDomain('/alpha/login');

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.auth.login_title'));
    }
}
