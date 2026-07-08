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
            '/volunteering/group-signups',
            '/volunteering/expenses',
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
        $response->assertSee(__('govuk_alpha_volunteering.org_dashboard.auto_credit_note'));
        $response->assertDontSee(__('govuk_alpha_volunteering.org_dashboard.auto_pay_on'));
        $response->assertDontSee(__('govuk_alpha_volunteering.org_dashboard.auto_pay_off'));
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
    // Org wallet — render + auto-credit status
    // =====================================================================

    public function test_volunteering_org_wallet_renders_for_owner(): void
    {
        $user = $this->authenticatedUser();
        $orgId = $this->createVolOrg((int) $user->id, 'Wallet Org', 'approved');

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/organisations/{$orgId}/wallet");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_volunteering.org_wallet.title'));
        $response->assertSee(__('govuk_alpha_volunteering.org_wallet.auto_credit_title'));
        $response->assertDontSee(__('govuk_alpha_volunteering.org_wallet.auto_pay_title'));
        $response->assertDontSee(route('govuk-alpha.volunteering.org.wallet.auto-pay', ['tenantSlug' => $this->testTenantSlug, 'id' => $orgId]), false);
    }

    public function test_volunteering_org_wallet_autopay_route_no_longer_mutates_flag(): void
    {
        $user = $this->authenticatedUser();
        $orgId = $this->createVolOrg((int) $user->id, 'Autopay Org', 'approved');
        DB::table('vol_organizations')
            ->where('id', $orgId)
            ->where('tenant_id', $this->testTenantId)
            ->update(['auto_pay_enabled' => 0]);

        $response = $this->withSession(['_token' => 'test-csrf-token'])
            ->post("/{$this->testTenantSlug}/alpha/volunteering/organisations/{$orgId}/wallet/auto-pay", [
                '_token' => 'test-csrf-token',
                'enabled' => '1',
            ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=auto-credit-always-on', $response->headers->get('Location') ?? '');
        $this->assertDatabaseHas('vol_organizations', [
            'id' => $orgId,
            'tenant_id' => $this->testTenantId,
            'auto_pay_enabled' => 0,
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
            // Recorded in the tenant's configured currency — the parity form
            // used to hardcode EUR for every community.
            'currency' => strtoupper(TenantContext::getCurrency()),
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
    // Group sign-ups
    // =====================================================================

    public function test_volunteering_group_signups_renders(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/group-signups");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_volunteering.group_signups.title'));
    }

    public function test_volunteering_group_signups_lists_a_led_reservation(): void
    {
        $user = $this->authenticatedUser();
        $orgId = $this->createVolOrg((int) $user->id, 'Group Org', 'approved');
        $this->createGroupReservation((int) $user->id, $orgId, 'Saturday Park Crew');

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/group-signups");

        $response->assertOk();
        $response->assertSee('Saturday Park Crew');
        // The caller is the reserver → leader tag + add-member form show.
        $response->assertSee(__('govuk_alpha_volunteering.group_signups.leader_tag'));
    }

    public function test_volunteering_group_signups_add_member_requires_an_id(): void
    {
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/group-signups/123/members", [
            'user_id' => 0,
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=member-id-required', $response->headers->get('Location') ?? '');
    }

    public function test_volunteering_group_signups_cancel_unknown_reservation_fails_gracefully(): void
    {
        $this->authenticatedUser();

        // No such reservation → service returns false → cancel-failed flag.
        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/group-signups/99999999/cancel");

        $response->assertRedirect();
        $this->assertStringContainsString('status=reservation-cancel-failed', $response->headers->get('Location') ?? '');
    }

    // =====================================================================
    // Expenses
    // =====================================================================

    public function test_volunteering_expenses_renders(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/expenses");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_volunteering.expenses.title'));
    }

    public function test_volunteering_expenses_403_when_feature_disabled(): void
    {
        $this->authenticatedUser();

        $row = DB::table('tenants')->where('id', $this->testTenantId)->value('features');
        $current = $row ? (json_decode($row, true) ?: []) : [];
        $current['volunteering'] = false;
        DB::table('tenants')->where('id', $this->testTenantId)->update(['features' => json_encode($current)]);
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering/expenses");

        $response->assertForbidden();
    }

    public function test_volunteering_submit_expense_persists(): void
    {
        $user = $this->authenticatedUser();
        $orgId = $this->createVolOrg((int) $user->id, 'Expense Org', 'approved');

        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/expenses", [
            'organization_id' => $orgId,
            'expense_type' => 'travel',
            'amount' => '12.50',
            'description' => 'Bus fare to the food bank shift.',
            'currency' => 'EUR',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=expense-submitted', $response->headers->get('Location') ?? '');
        $this->assertDatabaseHas('vol_expenses', [
            'tenant_id' => $this->testTenantId,
            'user_id' => (int) $user->id,
            'organization_id' => $orgId,
            'expense_type' => 'travel',
            'status' => 'pending',
        ]);
    }

    public function test_volunteering_submit_expense_rejects_missing_organisation(): void
    {
        $this->authenticatedUser();

        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/expenses", [
            'organization_id' => 0,
            'expense_type' => 'travel',
            'amount' => '10',
            'description' => 'Something',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=expense-org-required', $response->headers->get('Location') ?? '');
    }

    public function test_volunteering_submit_expense_rejects_zero_amount(): void
    {
        $user = $this->authenticatedUser();
        $orgId = $this->createVolOrg((int) $user->id, 'Zero Org', 'approved');

        $response = $this->post("/{$this->testTenantSlug}/alpha/volunteering/expenses", [
            'organization_id' => $orgId,
            'expense_type' => 'meals',
            'amount' => '0',
            'description' => 'Lunch',
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('status=expense-amount-invalid', $response->headers->get('Location') ?? '');
        $this->assertDatabaseMissing('vol_expenses', [
            'tenant_id' => $this->testTenantId,
            'organization_id' => $orgId,
            'expense_type' => 'meals',
        ]);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    // =====================================================================
    // Gateway "two hats" organisation door — the discoverable Post-opportunity
    // path Jasper could not find. Replaces the old buried Organisations tab.
    // =====================================================================

    public function test_volunteering_gateway_shows_post_opportunity_door_for_approved_org_owner(): void
    {
        $user = $this->authenticatedUser();
        $this->createVolOrg((int) $user->id, 'Door Owner Org', 'approved');

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.vol_org.door_eyebrow'));
        // The "Post an opportunity" CTA must be present and link to create.
        $response->assertSee(route('govuk-alpha.volunteering.opportunities.create', ['tenantSlug' => $this->testTenantSlug]), false);
        // Sole approved org → the door names it directly.
        $response->assertSee(__('govuk_alpha.vol_org.door_heading_one', ['name' => 'Door Owner Org']));
    }

    public function test_volunteering_gateway_shows_register_nudge_when_user_has_no_org(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.vol_org.door_register_title'));
        $response->assertSee(route('govuk-alpha.organisations.register', ['tenantSlug' => $this->testTenantSlug]), false);
    }

    public function test_volunteering_gateway_shows_awaiting_approval_for_pending_org_owner(): void
    {
        $user = $this->authenticatedUser();
        $this->createVolOrg((int) $user->id, 'Pending Door Org', 'pending');

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha.vol_org.awaiting_approval'));
        // A pending org must NOT expose the post-opportunity CTA yet.
        $response->assertDontSee(route('govuk-alpha.volunteering.opportunities.create', ['tenantSlug' => $this->testTenantSlug]), false);
    }

    public function test_volunteering_gateway_no_longer_offers_the_organisations_tab(): void
    {
        $this->authenticatedUser();

        $response = $this->get("/{$this->testTenantSlug}/alpha/volunteering");

        $response->assertOk();
        // The redundant Organisations tab was removed in favour of the org door.
        $response->assertDontSee(route('govuk-alpha.volunteering.index', ['tenantSlug' => $this->testTenantSlug, 'tab' => 'organisations']), false);
        // Organisations is still reachable from the hero "Browse organisations" link.
        $response->assertSee(route('govuk-alpha.organisations.index', ['tenantSlug' => $this->testTenantSlug]), false);
    }

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

    /**
     * Build a minimal group reservation led by $reservedBy: an opportunity +
     * a future shift + an active reservation row. Returns the reservation id.
     * getUserReservations() joins opportunity (required) + organisation (left),
     * so all three rows are written. A group row is also inserted so the
     * service's Group::find() lookup resolves a name.
     */
    private function createGroupReservation(int $reservedBy, int $orgId, string $groupName): int
    {
        $groupId = (int) (DB::table('groups')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'name' => $groupName,
            'owner_id' => $reservedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $oppId = (int) (DB::table('vol_opportunities')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'organization_id' => $orgId,
            'title' => 'Group Opportunity',
            'description' => 'A group volunteering opportunity.',
            'location' => 'Town Hall',
            'is_active' => 1,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $shiftId = (int) (DB::table('vol_shifts')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'opportunity_id' => $oppId,
            'start_time' => now()->addDays(3),
            'end_time' => now()->addDays(3)->addHours(2),
            'capacity' => 10,
            'created_at' => now(),
        ]));

        $reservationId = (int) (DB::table('vol_shift_group_reservations')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'shift_id' => $shiftId,
            'group_id' => $groupId,
            'reserved_slots' => 5,
            'filled_slots' => 0,
            'reserved_by' => $reservedBy,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return $reservationId;
    }
}
