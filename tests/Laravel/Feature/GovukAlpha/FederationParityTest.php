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
 * Federation module — accessible (GOV.UK) frontend parity tests.
 *
 * Covers the React-parity guided "Federation Onboarding" 4-step wizard
 * (govuk-alpha.federation.onboarding) — Welcome -> Privacy -> Communication ->
 * Confirm — that the accessible frontend was missing (it previously had only
 * the single flat /federation/opt-in form). The wizard persists the SAME
 * settings via the SAME service the React FederationOnboardingPage uses, so
 * these tests assert both the per-step UX and the final data outcome.
 *
 * Auth/tenant scrubbing mirrors GovukAlphaFrontendTest::setUp().
 */
class FederationParityTest extends TestCase
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
     * Make TenantContext::hasFeature('federation') and the tenant-level
     * federation gate resolve truthy, and whitelist the tenant. Self-contained
     * (does not depend on the federation integration harness).
     */
    private function enableFederationForTenant(): void
    {
        DB::table('federation_system_control')->updateOrInsert(
            ['id' => 1],
            [
                'federation_enabled'                => 1,
                'whitelist_mode_enabled'            => 1,
                'cross_tenant_profiles_enabled'     => 1,
                'cross_tenant_messaging_enabled'    => 1,
                'cross_tenant_transactions_enabled' => 1,
                'cross_tenant_listings_enabled'     => 1,
                'cross_tenant_events_enabled'       => 1,
                'cross_tenant_groups_enabled'       => 1,
                'emergency_lockdown_active'         => 0,
                'updated_at'                        => now(),
            ]
        );

        DB::table('federation_tenant_whitelist')->updateOrInsert(
            ['tenant_id' => $this->testTenantId],
            ['approved_at' => now(), 'approved_by' => 1]
        );

        foreach (['tenant_federation_enabled', 'federation'] as $feature) {
            DB::table('federation_tenant_features')->updateOrInsert(
                ['tenant_id' => $this->testTenantId, 'feature_key' => $feature],
                ['is_enabled' => 1, 'updated_at' => now()]
            );
        }

        try {
            $tenant = DB::table('tenants')->where('id', $this->testTenantId)->first();
            if ($tenant) {
                $features = json_decode($tenant->features ?? '{}', true) ?: [];
                $features['federation'] = true;
                DB::table('tenants')->where('id', $this->testTenantId)->update([
                    'features'   => json_encode($features),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Non-fatal in schemas without the column.
        }

        try {
            app(\App\Services\TenantSettingsService::class)->clearCacheForTenant($this->testTenantId);
        } catch (\Throwable $e) {
            // Optional cache clear.
        }
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    public function test_federation_onboarding_redirects_anonymous_to_login(): void
    {
        $this->enableFederationForTenant();

        $response = $this->get("/{$this->testTenantSlug}/alpha/federation/onboarding");

        $response->assertRedirect();
        $response->assertRedirectContains('/alpha/login');
    }

    public function test_federation_onboarding_renders_welcome_step(): void
    {
        $this->enableFederationForTenant();
        $this->authenticatedUser(['name' => 'Welcome Member']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/federation/onboarding");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_federation.onboarding.welcome_title'));
        $response->assertSee(__('govuk_alpha_federation.onboarding.get_started'));
        // Native <progress> drives the step indicator (never colour-only).
        $response->assertSee('<progress', false);
        $response->assertSee('AGPL-3.0-or-later');
    }

    public function test_federation_onboarding_renders_privacy_step(): void
    {
        $this->enableFederationForTenant();
        $this->authenticatedUser(['name' => 'Privacy Member']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/federation/onboarding?step=privacy");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_federation.onboarding.privacy_heading'));
        $response->assertSee('name="profile_visible_federated"', false);
        $response->assertSee('name="show_location_federated"', false);
    }

    public function test_federation_onboarding_renders_communication_step(): void
    {
        $this->enableFederationForTenant();
        $this->authenticatedUser(['name' => 'Comms Member']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/federation/onboarding?step=communication");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_federation.onboarding.communication_heading'));
        $response->assertSee('name="service_reach"', false);
        $response->assertSee('name="travel_radius_km"', false);
    }

    public function test_federation_onboarding_renders_confirm_step_with_warning(): void
    {
        $this->enableFederationForTenant();
        $this->authenticatedUser(['name' => 'Confirm Member']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/federation/onboarding?step=confirm");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_federation.onboarding.confirm_heading'));
        $response->assertSee(__('govuk_alpha_federation.onboarding.enable_federation'));
        // Data-sharing confirm warning is present before the enable action.
        $response->assertSee('govuk-warning-text', false);
    }

    public function test_federation_onboarding_unknown_step_falls_back_to_welcome(): void
    {
        $this->enableFederationForTenant();
        $this->authenticatedUser(['name' => 'Fallback Member']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/federation/onboarding?step=not-a-real-step");

        $response->assertOk();
        $response->assertSee(__('govuk_alpha_federation.onboarding.welcome_title'));
    }

    public function test_federation_onboarding_store_advances_step_without_opting_in(): void
    {
        $this->enableFederationForTenant();
        $user = $this->authenticatedUser(['name' => 'Advance Member']);

        $response = $this->post("/{$this->testTenantSlug}/alpha/federation/onboarding", [
            'step' => 'welcome',
        ]);

        $response->assertRedirect(
            route('govuk-alpha.federation.onboarding', ['tenantSlug' => $this->testTenantSlug, 'step' => 'privacy'])
        );

        // Advancing a step must NOT opt the member in yet.
        $this->assertFalse(\App\Services\FederationUserService::hasOptedIn($user->id));
    }

    public function test_federation_onboarding_confirm_opts_member_in_and_persists_settings(): void
    {
        $this->enableFederationForTenant();
        $user = $this->authenticatedUser(['name' => 'Finish Member']);

        // Walk the privacy + communication steps so the session bag carries the
        // member's choices into the final confirm submit.
        $this->post("/{$this->testTenantSlug}/alpha/federation/onboarding", [
            'step' => 'privacy',
            'profile_visible_federated' => '1',
            'appear_in_federated_search' => '1',
            'show_skills_federated' => '1',
            // location deliberately left off
            'show_reviews_federated' => '1',
        ])->assertRedirect();

        $this->post("/{$this->testTenantSlug}/alpha/federation/onboarding", [
            'step' => 'communication',
            'messaging_enabled_federated' => '1',
            'transactions_enabled_federated' => '1',
            'email_notifications' => '1',
            'service_reach' => 'travel_ok',
            'travel_radius_km' => '40',
        ])->assertRedirect();

        $finish = $this->post("/{$this->testTenantSlug}/alpha/federation/onboarding", [
            'step' => 'confirm',
        ]);

        $finish->assertRedirect(
            route('govuk-alpha.federation.index', ['tenantSlug' => $this->testTenantSlug, 'status' => 'opted-in'])
        );

        // The member is now opted in with the chosen preferences persisted.
        $this->assertTrue(\App\Services\FederationUserService::hasOptedIn($user->id));

        $settings = \App\Services\FederationUserService::getUserSettings($user->id);
        $this->assertTrue((bool) $settings['federation_optin']);
        $this->assertTrue((bool) $settings['profile_visible_federated']);
        $this->assertFalse((bool) $settings['show_location_federated']);
        $this->assertSame('travel_ok', $settings['service_reach']);
        $this->assertSame(40, (int) $settings['travel_radius_km']);
    }

    public function test_federation_onboarding_redirects_opted_in_member_to_hub(): void
    {
        $this->enableFederationForTenant();
        $user = $this->authenticatedUser(['name' => 'Already In Member']);

        \App\Services\FederationUserService::updateSettings($user->id, [
            'federation_optin' => true,
            'profile_visible_federated' => true,
        ]);

        $response = $this->get("/{$this->testTenantSlug}/alpha/federation/onboarding");

        $response->assertRedirect(
            route('govuk-alpha.federation.index', ['tenantSlug' => $this->testTenantSlug])
        );
    }

    public function test_federation_onboarding_returns_403_when_feature_disabled(): void
    {
        // Federation NOT enabled for the tenant — strip the feature flag.
        try {
            $tenant = DB::table('tenants')->where('id', $this->testTenantId)->first();
            if ($tenant) {
                $features = json_decode($tenant->features ?? '{}', true) ?: [];
                $features['federation'] = false;
                DB::table('tenants')->where('id', $this->testTenantId)->update([
                    'features' => json_encode($features),
                    'updated_at' => now(),
                ]);
            }
            DB::table('federation_tenant_features')
                ->where('tenant_id', $this->testTenantId)
                ->update(['is_enabled' => 0, 'updated_at' => now()]);
            app(\App\Services\TenantSettingsService::class)->clearCacheForTenant($this->testTenantId);
        } catch (\Throwable $e) {
            // Best-effort teardown of the flag.
        }
        TenantContext::reset();
        TenantContext::setById($this->testTenantId);

        $this->authenticatedUser(['name' => 'No Feature Member']);

        $response = $this->get("/{$this->testTenantSlug}/alpha/federation/onboarding");

        $response->assertForbidden();
    }
}
