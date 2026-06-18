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
 * Accessible (GOV.UK) frontend — volunteering parity routes.
 *
 * Covers the org-management suite (my-organisations, dashboard, settings,
 * wallet, create-opportunity) and three member features (emergency alerts,
 * credential uploads, wellbeing) added in VolunteeringParity. Mirrors the
 * setUp scrubbing + helpers used by GovukAlphaFrontendTest so it runs the same
 * way inside the full suite. Unique test_volunteering_ method names throughout.
 */
class VolunteeringParityTest extends TestCase
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

        $this->enableVolunteeringFeature();
    }

    // =====================================================================
    // Auth gating — every parity route redirects anonymous users to login.
    // =====================================================================

    public function test_volunteering_parity_pages_require_authentication(): void
    {
        $loginPath = "/{$this->testTenantSlug}/alpha/login";

        foreach ([
            '/volunteering/my-organisations',
            '/volunteering/recommended-shifts',
            '/volunteering/emergency-alerts',
            '/volunteering/credentials',
            '/volunteering/wellbeing',
            '/volunteering/donations',
            '/volunteering/opportunities/create',
        ] as $path) {
            $response = $this->get("/{$this->testTenantSlug}/alpha{$path}");
            $response->assertRedirect();
            $this->assertStringContainsString($loginPath, $response->headers->get('Location') ?? '');
        }
    }

    // =====================================================================
    // My organisations
    // =====================================================================

    public function test_volunteering_my_organisations_renders_for_authenticated_user(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/my-organisations");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_volunteering.my_orgs.title'));
    }

    public function test_volunteering_my_organisations_lists_an_owned_org(): void
    {
        $user = $this->authenticatedUser();
        $orgId = $this->createVolOrg((int) $user->id, 'My Owned Org', 'approved');

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/my-organisations");

        $response->assertOk();
        $response->assertSee('My Owned Org');
        // Approved org the user owns gets a dashboard link.
        $response->assertSee(route('govuk-alpha.volunteering.org.dashboard', ['tenantSlug' => $this->testTenantSlug, 'id' => $orgId]), false);
    }

    // =====================================================================
    // Org dashboard — owner ok, cross-tenant 404, non-owner 403
    // =====================================================================

    public function test_volunteering_org_dashboard_renders_for_owner(): void
    {
        $user = $this->authenticatedUser();
        $orgId = $this->createVolOrg((int) $user->id, 'Dashboard Org', 'approved');

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/organisations/{$orgId}/dashboard");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_volunteering.org_dashboard.title'));
        $response->assertSee('Dashboard Org');
    }

    public function test_volunteering_org_dashboard_404_for_missing_org(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/organisations/99999999/dashboard");

        $response->assertNotFound();
    }

    public function test_volunteering_org_dashboard_403_for_non_owner(): void
    {
        // Org owned by someone else in the same tenant.
        $owner = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        $orgId = $this->createVolOrg((int) $owner->id, 'Someone Elses Org', 'approved');

        // A different, authenticated user (not owner / not org member).
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/organisations/{$orgId}/dashboard");

        $response->assertForbidden();
    }

    // =====================================================================
    // Org settings — render + update persists + validation
    // =====================================================================

    public function test_volunteering_org_settings_renders_for_owner(): void
    {
        $user = $this->authenticatedUser();
        $orgId = $this->createVolOrg((int) $user->id, 'Settings Org', 'approved');

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/organisations/{$orgId}/settings");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_volunteering.org_settings.title'));
        $response->assertSee('Settings Org');
    }

    public function test_volunteering_org_settings_update_persists(): void
    {
        $user = $this->authenticatedUser();
        $orgId = $this->createVolOrg((int) $user->id, 'Before Name', 'approved');

        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/organisations/{$orgId}/settings", [
            'name' => 'After Name',
            'description' => 'Updated description',
            'contact_email' => 'team@example.org',
            'website' => 'https://example.org',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vol_organizations', [
            'id' => $orgId,
            'tenant_id' => $this->testTenantId,
            'name' => 'After Name',
            'contact_email' => 'team@example.org',
        ]);
    }

    public function test_volunteering_org_settings_update_rejects_blank_name(): void
    {
        $user = $this->authenticatedUser();
        $orgId = $this->createVolOrg((int) $user->id, 'Keep This Name', 'approved');

        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/organisations/{$orgId}/settings", [
            'name' => '',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=name-required', $response->headers->get('Location') ?? '');
        // Name was not wiped.
        $this->assertDatabaseHas('vol_organizations', ['id' => $orgId, 'name' => 'Keep This Name']);
    }

    // =====================================================================
    // Org wallet — render + auto-pay toggle persists
    // =====================================================================

    public function test_volunteering_org_wallet_renders_for_owner(): void
    {
        $user = $this->authenticatedUser();
        $orgId = $this->createVolOrg((int) $user->id, 'Wallet Org', 'approved');

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/organisations/{$orgId}/wallet");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_volunteering.org_wallet.title'));
    }

    public function test_volunteering_org_wallet_autopay_toggle_persists(): void
    {
        $user = $this->authenticatedUser();
        $orgId = $this->createVolOrg((int) $user->id, 'Autopay Org', 'approved');

        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/organisations/{$orgId}/wallet/auto-pay", [
            'enabled' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vol_organizations', [
            'id' => $orgId,
            'tenant_id' => $this->testTenantId,
            'auto_pay_enabled' => 1,
        ]);
    }

    // =====================================================================
    // Create opportunity — render + happy-path persists an opportunity
    // =====================================================================

    public function test_volunteering_create_opportunity_renders(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/opportunities/create");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_volunteering.create_opp.title'));
    }

    public function test_volunteering_store_opportunity_persists(): void
    {
        $user = $this->authenticatedUser();
        $orgId = $this->createVolOrg((int) $user->id, 'Posting Org', 'approved');

        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/opportunities/create", [
            'organization_id' => $orgId,
            'title' => 'Park Clean-up Helper',
            'description' => 'Help tidy the community park on Saturday mornings.',
            'location' => 'Community Park',
            'skills_needed' => 'litter picking',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vol_opportunities', [
            'organization_id' => $orgId,
            'tenant_id' => $this->testTenantId,
            'title' => 'Park Clean-up Helper',
        ]);
    }

    public function test_volunteering_store_opportunity_rejects_missing_fields(): void
    {
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/opportunities/create", [
            'organization_id' => 0,
            'title' => '',
            'description' => '',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=opp-validation', $response->headers->get('Location') ?? '');
    }

    // =====================================================================
    // Emergency alerts
    // =====================================================================

    public function test_volunteering_emergency_alerts_renders(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/emergency-alerts");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_volunteering.emergency.title'));
    }

    public function test_volunteering_respond_emergency_alert_redirects_for_unknown_alert(): void
    {
        $this->authenticatedUser();

        // No recipient row for this user → service returns false → respond-failed.
        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/emergency-alerts/99999999/respond", [
            'response' => 'accepted',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=alert-respond-failed', $response->headers->get('Location') ?? '');
    }

    // =====================================================================
    // Credentials
    // =====================================================================

    public function test_volunteering_credentials_renders(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/credentials");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_volunteering.credentials.title'));
    }

    public function test_volunteering_credential_upload_requires_a_type(): void
    {
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/credentials", [
            'credential_type' => '',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=credential-type-required', $response->headers->get('Location') ?? '');
    }

    public function test_volunteering_credential_delete_is_ownership_scoped(): void
    {
        $user = $this->authenticatedUser();
        // Credential belonging to a DIFFERENT user → delete must not affect it.
        $other = User::factory()->forTenant($this->testTenantId)->create(['status' => 'active', 'is_approved' => true]);
        DB::table('vol_credentials')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => (int) $other->id,
            'credential_type' => 'first_aid',
            'file_url' => 'private:volunteer-credentials/' . $this->testTenantId . '/dummy.pdf',
            'file_name' => 'dummy.pdf',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $credId = (int) DB::getPdo()->lastInsertId();

        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/credentials/{$credId}/delete");

        $response->assertRedirect();
        // The other user's credential still exists (ownership-scoped DELETE no-op).
        $this->assertDatabaseHas('vol_credentials', ['id' => $credId, 'user_id' => (int) $other->id]);
        $this->assertStringContainsString('status=credential-delete-failed', $response->headers->get('Location') ?? '');
    }

    // =====================================================================
    // Wellbeing
    // =====================================================================

    public function test_volunteering_wellbeing_renders(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/wellbeing");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_volunteering.wellbeing.title'));
    }

    public function test_volunteering_wellbeing_checkin_persists(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/wellbeing/checkin", [
            'mood' => 4,
            'note' => 'Feeling good after my shift.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vol_mood_checkins', [
            'tenant_id' => $this->testTenantId,
            'user_id' => (int) $user->id,
            'mood' => 4,
        ]);
    }

    public function test_volunteering_wellbeing_checkin_rejects_out_of_range_mood(): void
    {
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/wellbeing/checkin", [
            'mood' => 9,
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=mood-invalid', $response->headers->get('Location') ?? '');
    }

    // =====================================================================
    // Recommended shifts
    // =====================================================================

    public function test_volunteering_recommended_shifts_renders(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/recommended-shifts");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_volunteering.recommended.title'));
    }

    // =====================================================================
    // Donations / giving
    // =====================================================================

    public function test_volunteering_donations_renders_for_authenticated_user(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/donations");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_volunteering.donations.title'));
        $response->assertSee(__('govuk_alpha_volunteering.donations.form_title'));
    }

    public function test_volunteering_donations_403_when_feature_disabled(): void
    {
        $this->authenticatedUser();

        // Turn the volunteering feature off for this tenant.
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['volunteering'] = false;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/donations");

        $response->assertForbidden();
    }

    public function test_volunteering_store_donation_persists_pending_offline_donation(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/donations", [
            '_token' => csrf_token(),
            'amount' => '25.50',
            'payment_method' => 'bank_transfer',
            'message' => 'Keep up the good work',
            'is_anonymous' => '1',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('donate-recorded', $response->headers->get('Location') ?? '');

        $this->assertDatabaseHas('vol_donations', [
            'tenant_id' => $this->testTenantId,
            'user_id' => (int) $user->id,
            'payment_method' => 'bank_transfer',
            'status' => 'pending',
            'is_anonymous' => 1,
        ]);
    }

    public function test_volunteering_store_donation_rejects_zero_amount(): void
    {
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/donations", [
            '_token' => csrf_token(),
            'amount' => '0',
            'payment_method' => 'paypal',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('donate-failed', $response->headers->get('Location') ?? '');
        $this->assertDatabaseMissing('vol_donations', [
            'tenant_id' => $this->testTenantId,
            'payment_method' => 'paypal',
        ]);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function enableVolunteeringFeature(): void
    {
        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['volunteering'] = true;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
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

    /**
     * Insert a minimal approved vol_organizations row owned by $userId and
     * return its id. Only columns that always exist on the table are written.
     */
    private function createVolOrg(int $userId, string $name, string $status): int
    {
        DB::table('vol_organizations')->insert([
            'tenant_id' => $this->testTenantId,
            'user_id' => $userId,
            'name' => $name,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }
}
