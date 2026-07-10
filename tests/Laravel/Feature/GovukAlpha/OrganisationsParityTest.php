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
 * Feature tests for the accessible (GOV.UK) Organisations parity module.
 *
 * Covers the paginated browse list (search + cursor "load more"), the dedicated
 * registration page (per-field inline errors + happy-path create), the "manage
 * my organisations" entry, the per-organisation open-jobs listing, and the
 * HTML-first apply-to-opportunity confirm page — plus the auth / feature /
 * cross-tenant (404) gates.
 *
 * Extends the same base TestCase + DatabaseTransactions trait that
 * GovukAlphaFrontendTest uses; the private helpers there are replicated below.
 */
class OrganisationsParityTest extends TestCase
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

    private function disableAlphaFeatures(array $features): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        foreach ($features as $f) {
            $current[$f] = false;
        }
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    /** Seed a volunteer organisation row; returns its id. */
    private function seedOrganisation(array $overrides = []): int
    {
        return (int) DB::table('vol_organizations')->insertGetId(array_merge([
            'tenant_id'     => $this->testTenantId,
            'user_id'       => 1,
            'name'          => 'Parity Org ' . uniqid(),
            'description'   => 'A seeded organisation for parity tests.',
            'contact_email' => 'org@example.org',
            'website'       => 'https://example.org',
            'status'        => 'active',
            'created_at'    => now(),
        ], $overrides));
    }

    /** Seed a public volunteering opportunity for an org; returns its id. */
    private function seedOpportunity(int $organizationId, array $overrides = []): int
    {
        return (int) DB::table('vol_opportunities')->insertGetId(array_merge([
            'tenant_id'       => $this->testTenantId,
            'organization_id' => $organizationId,
            'title'           => 'Parity Opportunity',
            'description'     => 'Help out with a community task.',
            'is_active'       => 1,
            'status'          => 'open',
            'created_at'      => now(),
        ], $overrides));
    }

    // ---------------------------------------------------------------
    // Browse list (pagination)
    // ---------------------------------------------------------------

    public function test_organisations_browse_redirects_anonymous_to_login(): void
    {
        $this->enableAlphaFeatures(['volunteering']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/organisations/browse");

        $res->assertStatus(302);
        $res->assertRedirectContains('/accessible/login');
    }

    public function test_organisations_browse_renders_for_authenticated_user(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['volunteering']);
        $this->seedOrganisation(['name' => 'Parity Browse Org']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/organisations/browse");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_organisations.browse.title'));
        $res->assertSee('Parity Browse Org');
        $res->assertSee(__('govuk_alpha_organisations.browse.register_link'));
    }

    public function test_organisations_browse_shows_empty_state_for_no_results(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['volunteering']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/organisations/browse?q=zzz-no-such-org-zzz");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_organisations.browse.empty_title'));
        $res->assertSee(__('govuk_alpha_organisations.browse.empty_no_results'));
    }

    public function test_organisations_browse_returns_403_when_feature_disabled(): void
    {
        $this->authenticatedUser();
        $this->disableAlphaFeatures(['volunteering']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/organisations/browse");

        $res->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Register page
    // ---------------------------------------------------------------

    public function test_organisations_register_form_renders(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['volunteering']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/organisations/register");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_organisations.register.heading'));
        $res->assertSee('name="name"', false);
        $res->assertSee('name="agreed_terms"', false);
    }

    public function test_organisations_register_form_shows_inline_error_for_invalid_name(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['volunteering']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/organisations/register?status=org-name-invalid");

        $res->assertOk();
        $res->assertSee('govuk-form-group--error', false);
        $res->assertSee('id="name-error"', false);
        $res->assertSee('href="#name"', false);
        $res->assertSee(__('govuk_alpha_organisations.register.errors.org-name-invalid'));
    }

    public function test_organisations_register_rejects_short_name_with_redirect(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['volunteering']);

        $token = 'test-csrf-token';
        $res = $this->withSession(['_token' => $token])->post("/{$this->testTenantSlug}/accessible/organisations/register", [
            '_token'       => $token,
            'name'         => 'no',
            'description'  => 'This description is definitely long enough to pass.',
            'email'        => 'valid@example.org',
            'agreed_terms' => '1',
        ]);

        $res->assertStatus(302);
        $res->assertRedirectContains('status=org-name-invalid');
    }

    public function test_organisations_register_requires_terms_agreement(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['volunteering']);

        $token = 'test-csrf-token';
        $res = $this->withSession(['_token' => $token])->post("/{$this->testTenantSlug}/accessible/organisations/register", [
            '_token'      => $token,
            'name'        => 'A Valid Organisation Name',
            'description' => 'This description is definitely long enough to pass validation.',
            'email'       => 'valid@example.org',
            // agreed_terms intentionally omitted
        ]);

        $res->assertStatus(302);
        $res->assertRedirectContains('status=org-terms-required');
    }

    public function test_organisations_register_creates_pending_organisation(): void
    {
        $user = $this->authenticatedUser();
        $this->enableAlphaFeatures(['volunteering']);

        $name = 'My Parity Community Group ' . uniqid();

        $token = 'test-csrf-token';
        $res = $this->withSession(['_token' => $token])->post("/{$this->testTenantSlug}/accessible/organisations/register", [
            '_token'       => $token,
            'name'         => $name,
            'description'  => 'We organise community litter picks and gardening days.',
            'email'        => 'group@example.org',
            'website'      => 'https://group.example.org',
            'agreed_terms' => '1',
        ]);

        $res->assertStatus(302);
        $res->assertRedirectContains('status=org-submitted');

        $this->assertDatabaseHas('vol_organizations', [
            'tenant_id' => $this->testTenantId,
            'user_id'   => $user->id,
            'name'      => $name,
            'status'    => 'pending',
        ]);
    }

    // ---------------------------------------------------------------
    // Manage my organisations
    // ---------------------------------------------------------------

    public function test_organisations_manage_redirects_anonymous_to_login(): void
    {
        $this->enableAlphaFeatures(['volunteering']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/organisations/manage");

        $res->assertStatus(302);
        $res->assertRedirectContains('/accessible/login');
    }

    public function test_organisations_manage_lists_owned_organisation(): void
    {
        $owner = $this->authenticatedUser();
        $this->enableAlphaFeatures(['volunteering']);
        $this->seedOrganisation(['user_id' => $owner->id, 'name' => 'Owned Manage Org', 'status' => 'active']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/organisations/manage");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_organisations.manage.title'));
        $res->assertSee('Owned Manage Org');
        $res->assertSee(__('govuk_alpha_organisations.manage.manage_button'));
    }

    public function test_organisations_manage_shows_empty_state_when_none_owned(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['volunteering']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/organisations/manage");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_organisations.manage.empty_title'));
    }

    // ---------------------------------------------------------------
    // Per-organisation jobs
    // ---------------------------------------------------------------

    public function test_organisations_jobs_renders_for_existing_org(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['volunteering', 'job_vacancies']);
        $orgId = $this->seedOrganisation(['name' => 'Jobs Parity Org']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/organisations/{$orgId}/jobs");

        $res->assertOk();
        $res->assertSee('Jobs Parity Org');
        // No jobs FK to this volunteer org, so the empty state shows.
        $res->assertSee(__('govuk_alpha_organisations.jobs.empty'));
        // JSON-LD structured data is emitted for the organisation.
        $res->assertSee('application/ld+json', false);
    }

    public function test_organisations_jobs_404_for_cross_tenant_org(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['volunteering', 'job_vacancies']);
        $orgId = $this->seedOrganisation(['tenant_id' => 999, 'name' => 'Other Tenant Org']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/organisations/{$orgId}/jobs");

        $res->assertStatus(404);
    }

    public function test_organisations_jobs_404_for_non_public_org_status(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['volunteering', 'job_vacancies']);
        $orgId = $this->seedOrganisation(['name' => 'Pending Jobs Org', 'status' => 'pending']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/organisations/{$orgId}/jobs");

        $res->assertStatus(404);
    }

    public function test_organisations_jobs_403_when_jobs_feature_disabled(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['volunteering']);
        $this->disableAlphaFeatures(['job_vacancies']);
        $orgId = $this->seedOrganisation();

        $res = $this->get("/{$this->testTenantSlug}/accessible/organisations/{$orgId}/jobs");

        $res->assertStatus(403);
    }

    // ---------------------------------------------------------------
    // Apply to opportunity (HTML-first confirm page)
    // ---------------------------------------------------------------

    public function test_organisations_apply_form_renders_for_public_opportunity(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['volunteering']);
        $orgId = $this->seedOrganisation(['name' => 'Apply Parity Org']);
        $oppId = $this->seedOpportunity($orgId, ['title' => 'Apply Parity Opportunity']);

        $res = $this->get("/{$this->testTenantSlug}/accessible/organisations/opportunities/{$oppId}/apply");

        $res->assertOk();
        $res->assertSee(__('govuk_alpha_organisations.apply.heading'));
        $res->assertSee('Apply Parity Opportunity');
        $res->assertSee('name="message"', false);
        // The form posts to the existing volunteering apply route.
        $res->assertSee(route('govuk-alpha.volunteering.apply.store', ['tenantSlug' => $this->testTenantSlug, 'id' => $oppId]), false);
    }

    public function test_organisations_apply_form_404_for_cross_tenant_opportunity(): void
    {
        $this->authenticatedUser();
        $this->enableAlphaFeatures(['volunteering']);
        $orgId = $this->seedOrganisation(['tenant_id' => 999]);
        $oppId = $this->seedOpportunity($orgId, ['tenant_id' => 999]);

        $res = $this->get("/{$this->testTenantSlug}/accessible/organisations/opportunities/{$oppId}/apply");

        $res->assertStatus(404);
    }

    public function test_organisations_apply_form_redirects_anonymous_to_login(): void
    {
        $this->enableAlphaFeatures(['volunteering']);
        $orgId = $this->seedOrganisation();
        $oppId = $this->seedOpportunity($orgId);

        $res = $this->get("/{$this->testTenantSlug}/accessible/organisations/opportunities/{$oppId}/apply");

        $res->assertStatus(302);
        $res->assertRedirectContains('/accessible/login');
    }
}
