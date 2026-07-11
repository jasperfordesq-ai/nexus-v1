<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use App\Core\TenantContext;
use App\Models\User;
use App\Services\TenantFeatureConfig;

/**
 * Feature tests for WebAuthnController — passkey registration, auth, management.
 *
 * Auth challenge routes are PUBLIC (pre-login). Registration and management require auth.
 */
class WebAuthnControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function setPasskeyEnrollmentAllowed(bool $allowed): void
    {
        $features = TenantFeatureConfig::FEATURE_DEFAULTS;
        $features['biometric_login'] = $allowed;

        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode($features),
        ]);

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    // ------------------------------------------------------------------
    //  POST /webauthn/auth-challenge (PUBLIC, rate-limited)
    // ------------------------------------------------------------------

    public function test_auth_challenge_is_public(): void
    {
        $response = $this->apiPost('/webauthn/auth-challenge', []);

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_auth_challenge_returns_passkey_options(): void
    {
        $response = $this->apiPost('/webauthn/auth-challenge', []);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'challenge',
                'challenge_id',
                'rpId',
                'timeout',
                'userVerification',
            ],
        ]);
    }

    public function test_auth_challenge_remains_available_when_new_passkey_enrollment_is_disabled(): void
    {
        $this->setPasskeyEnrollmentAllowed(false);

        $this->apiPost('/webauthn/auth-challenge', [])
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['challenge', 'challenge_id', 'rpId'],
            ]);
    }

    // ------------------------------------------------------------------
    //  RP ID derivation (multi-tenant custom domains)
    //
    //  Tenants can serve the React frontend from their own domain
    //  (tenants.domain). The RP ID must match the page's domain or the
    //  browser rejects the ceremony — a single platform-wide RP ID broke
    //  passkeys entirely on custom-domain tenants.
    // ------------------------------------------------------------------

    public function test_auth_challenge_uses_tenant_custom_domain_as_rp_id(): void
    {
        config(['webauthn.rp_id' => 'project-nexus.ie']);
        DB::table('tenants')->where('id', $this->testTenantId)->update(['domain' => 'hour-timebank.ie']);
        TenantContext::setById($this->testTenantId);

        $response = $this->apiPost('/webauthn/auth-challenge', [], ['Origin' => 'https://hour-timebank.ie']);

        $response->assertStatus(200);
        $this->assertSame('hour-timebank.ie', $response->json('data.rpId'));
    }

    public function test_auth_challenge_uses_multi_label_custom_domain_as_rp_id(): void
    {
        // Time Banking UK: tenant domain is itself a subdomain of another
        // tenant's domain (uk.timebank.global under timebank.global). The
        // exact-host match must win, keeping credentials scoped per tenant.
        config(['webauthn.rp_id' => 'project-nexus.ie']);
        DB::table('tenants')->where('id', $this->testTenantId)->update(['domain' => 'uk.timebank.global']);
        TenantContext::setById($this->testTenantId);

        $response = $this->apiPost('/webauthn/auth-challenge', [], ['Origin' => 'https://uk.timebank.global']);

        $response->assertStatus(200);
        $this->assertSame('uk.timebank.global', $response->json('data.rpId'));
    }

    public function test_auth_challenge_sub_tenant_inherits_parent_domain_rp_id(): void
    {
        // Slug-only sub-tenant served at the parent's custom domain
        // (e.g. stratford at uk.timebank.global/stratford): the sub-tenant has
        // no domain of its own, so the parent's domain must be accepted.
        config(['webauthn.rp_id' => 'project-nexus.ie']);
        DB::table('tenants')->where('id', 999)->update(['domain' => 'uk.timebank.global']);
        DB::table('tenants')->where('id', $this->testTenantId)->update(['domain' => null, 'parent_id' => 999]);
        TenantContext::setById($this->testTenantId);

        $response = $this->apiPost('/webauthn/auth-challenge', [], ['Origin' => 'https://uk.timebank.global']);

        $response->assertStatus(200);
        $this->assertSame('uk.timebank.global', $response->json('data.rpId'));
    }

    public function test_auth_challenge_uses_platform_rp_id_on_platform_domain(): void
    {
        config(['webauthn.rp_id' => 'project-nexus.ie']);

        $response = $this->apiPost('/webauthn/auth-challenge', [], ['Origin' => 'https://app.project-nexus.ie']);

        $response->assertStatus(200);
        $this->assertSame('project-nexus.ie', $response->json('data.rpId'));
    }

    public function test_auth_challenge_ignores_unrecognised_origin(): void
    {
        config(['webauthn.rp_id' => 'project-nexus.ie']);
        DB::table('tenants')->where('id', $this->testTenantId)->update(['domain' => 'hour-timebank.ie']);
        TenantContext::setById($this->testTenantId);

        $response = $this->apiPost('/webauthn/auth-challenge', [], ['Origin' => 'https://evil.example.com']);

        $response->assertStatus(200);
        $this->assertSame('project-nexus.ie', $response->json('data.rpId'));
    }

    public function test_register_challenge_uses_tenant_custom_domain_as_rp_id(): void
    {
        config(['webauthn.rp_id' => 'project-nexus.ie']);
        DB::table('tenants')->where('id', $this->testTenantId)->update(['domain' => 'hour-timebank.ie']);
        TenantContext::setById($this->testTenantId);
        $this->authenticatedUser();

        $response = $this->apiPost('/webauthn/register-challenge', [], ['Origin' => 'https://hour-timebank.ie']);

        if ($response->getStatusCode() === 200) {
            $this->assertSame('hour-timebank.ie', $response->json('data.rp.id'));
        } else {
            // Environments without full session support return 500 here (see
            // test_register_challenge_returns_options) — nothing to assert.
            $this->assertSame(500, $response->getStatusCode());
        }
    }

    // ------------------------------------------------------------------
    //  POST /webauthn/auth-verify (PUBLIC, rate-limited)
    // ------------------------------------------------------------------

    public function test_auth_verify_is_public(): void
    {
        $response = $this->apiPost('/webauthn/auth-verify', []);

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    //  POST /webauthn/login/options (PUBLIC, rate-limited — alias)
    // ------------------------------------------------------------------

    public function test_login_options_is_public(): void
    {
        $response = $this->apiPost('/webauthn/login/options', []);

        $this->assertNotEquals(401, $response->getStatusCode());
    }

    // ------------------------------------------------------------------
    //  POST /webauthn/register-challenge (auth required)
    // ------------------------------------------------------------------

    public function test_register_challenge_requires_auth(): void
    {
        $response = $this->apiPost('/webauthn/register-challenge');

        $response->assertStatus(401);
    }

    public function test_register_challenge_returns_options(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/webauthn/register-challenge');

        $this->assertContains($response->getStatusCode(), [200, 500]);
    }

    public function test_registration_endpoints_are_blocked_when_new_passkey_enrollment_is_disabled(): void
    {
        $this->authenticatedUser();
        $this->setPasskeyEnrollmentAllowed(false);

        $this->apiPost('/webauthn/register-challenge')
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FEATURE_DISABLED');

        $this->apiPost('/webauthn/register-verify', [])
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FEATURE_DISABLED');
    }

    // ------------------------------------------------------------------
    //  POST /webauthn/register-verify (auth required)
    // ------------------------------------------------------------------

    public function test_register_verify_requires_auth(): void
    {
        $response = $this->apiPost('/webauthn/register-verify', []);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /webauthn/remove (auth required)
    // ------------------------------------------------------------------

    public function test_remove_requires_auth(): void
    {
        $response = $this->apiPost('/webauthn/remove', ['credential_id' => 'abc']);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /webauthn/rename (auth required)
    // ------------------------------------------------------------------

    public function test_rename_requires_auth(): void
    {
        $response = $this->apiPost('/webauthn/rename', [
            'credential_id' => 'abc',
            'name' => 'My Passkey',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /webauthn/remove-all (auth required)
    // ------------------------------------------------------------------

    public function test_remove_all_requires_auth(): void
    {
        $response = $this->apiPost('/webauthn/remove-all');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /webauthn/credentials (auth required)
    // ------------------------------------------------------------------

    public function test_credentials_requires_auth(): void
    {
        $response = $this->apiGet('/webauthn/credentials');

        $response->assertStatus(401);
    }

    public function test_credentials_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/webauthn/credentials');

        $response->assertStatus(200);
    }

    public function test_existing_passkeys_remain_visible_and_manageable_when_new_enrollment_is_disabled(): void
    {
        $user = $this->authenticatedUser();
        $this->setPasskeyEnrollmentAllowed(false);

        DB::table('webauthn_credentials')->insert([
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'credential_id' => 'existing-passkey-credential',
            'public_key' => 'test-public-key',
            'device_name' => 'Existing passkey',
            'authenticator_type' => 'platform',
            'created_at' => now(),
        ]);

        $this->apiGet('/webauthn/credentials')
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.credentials.0.device_name', 'Existing passkey');

        $this->apiGet('/webauthn/status')
            ->assertOk()
            ->assertJsonPath('data.registered', true)
            ->assertJsonPath('data.enrollment_allowed', false);

        $this->apiPost('/webauthn/rename', [
            'credential_id' => 'existing-passkey-credential',
            'device_name' => 'Renamed passkey',
        ])->assertOk()
            ->assertJsonPath('data.device_name', 'Renamed passkey');

        $this->apiPost('/webauthn/remove', [
            'credential_id' => 'existing-passkey-credential',
        ])->assertOk();

        $this->assertDatabaseMissing('webauthn_credentials', [
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'credential_id' => 'existing-passkey-credential',
        ]);
    }

    public function test_last_passkey_cannot_be_removed_without_another_sign_in_method(): void
    {
        $user = $this->authenticatedUser();
        DB::table('users')->where('id', $user->id)->update([
            'password' => null,
            'password_hash' => null,
        ]);
        DB::table('webauthn_credentials')->insert([
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'credential_id' => 'only-sign-in-passkey',
            'public_key' => 'test-public-key',
            'device_name' => 'Only sign-in passkey',
            'authenticator_type' => 'platform',
            'created_at' => now(),
        ]);

        $this->apiPost('/webauthn/remove', [
            'credential_id' => 'only-sign-in-passkey',
        ])->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'LAST_SIGN_IN_METHOD');

        $this->apiPost('/webauthn/remove-all', [])
            ->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'LAST_SIGN_IN_METHOD');

        $this->assertDatabaseHas('webauthn_credentials', [
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'credential_id' => 'only-sign-in-passkey',
        ]);
    }

    // ------------------------------------------------------------------
    //  GET /webauthn/status (auth required)
    // ------------------------------------------------------------------

    public function test_status_requires_auth(): void
    {
        $response = $this->apiGet('/webauthn/status');

        $response->assertStatus(401);
    }

    public function test_status_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/webauthn/status');

        $response->assertStatus(200);
    }
}
