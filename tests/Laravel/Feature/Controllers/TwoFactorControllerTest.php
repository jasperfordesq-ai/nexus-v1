<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\TenantFeatureConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for TwoFactorController — 2FA setup, verify, disable.
 */
class TwoFactorControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function authenticatedUser(array $attributes = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $attributes));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function setTwoFactorEnrollmentAllowed(bool $allowed): void
    {
        $features = TenantFeatureConfig::FEATURE_DEFAULTS;
        $features['two_factor_authentication'] = $allowed;

        DB::table('tenants')->where('id', $this->testTenantId)->update([
            'features' => json_encode($features),
        ]);

        TenantContext::reset();
        TenantContext::setById($this->testTenantId);
    }

    private function enableTwoFactorFor(User $user): void
    {
        DB::table('user_totp_settings')->insert([
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'totp_secret_encrypted' => 'not-read-by-these-tests',
            'is_enabled' => 1,
            'is_pending_setup' => 0,
        ]);

        DB::table('users')->where('id', $user->id)->update([
            'totp_enabled' => 1,
            'totp_setup_required' => 0,
        ]);
    }

    // ------------------------------------------------------------------
    //  GET /v2/auth/2fa/status
    // ------------------------------------------------------------------

    public function test_status_requires_auth(): void
    {
        $response = $this->apiGet('/v2/auth/2fa/status');

        $response->assertStatus(401);
    }

    public function test_status_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/auth/2fa/status');

        $response->assertStatus(200);
    }

    public function test_status_preserves_existing_two_factor_when_new_enrollment_is_disabled(): void
    {
        $user = $this->authenticatedUser();
        $this->enableTwoFactorFor($user);
        $this->setTwoFactorEnrollmentAllowed(false);

        $response = $this->apiGet('/v2/auth/2fa/status');

        $response->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.enrollment_allowed', false);
    }

    // ------------------------------------------------------------------
    //  POST /v2/auth/2fa/setup
    // ------------------------------------------------------------------

    public function test_setup_requires_auth(): void
    {
        $response = $this->apiPost('/v2/auth/2fa/setup');

        $response->assertStatus(401);
    }

    public function test_setup_returns_qr_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/v2/auth/2fa/setup');

        $response->assertStatus(200);
    }

    public function test_setup_and_setup_verification_are_blocked_when_new_enrollment_is_disabled(): void
    {
        $this->authenticatedUser();
        $this->setTwoFactorEnrollmentAllowed(false);

        $this->apiPost('/v2/auth/2fa/setup')
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FEATURE_DISABLED');

        $this->apiPost('/v2/auth/2fa/verify', ['code' => '123456'])
            ->assertForbidden()
            ->assertJsonPath('errors.0.code', 'FEATURE_DISABLED');
    }

    // ------------------------------------------------------------------
    //  POST /v2/auth/2fa/verify
    // ------------------------------------------------------------------

    public function test_verify_requires_auth(): void
    {
        $response = $this->apiPost('/v2/auth/2fa/verify', ['code' => '123456']);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/auth/2fa/disable
    // ------------------------------------------------------------------

    public function test_disable_requires_auth(): void
    {
        $response = $this->apiPost('/v2/auth/2fa/disable');

        $response->assertStatus(401);
    }

    public function test_existing_two_factor_can_be_disabled_when_new_enrollment_is_disabled(): void
    {
        $user = $this->authenticatedUser([
            'password_hash' => Hash::make('correct-password'),
        ]);
        $this->enableTwoFactorFor($user);
        $this->setTwoFactorEnrollmentAllowed(false);

        $this->apiPost('/v2/auth/2fa/disable', ['password' => 'correct-password'])
            ->assertOk();

        $this->assertDatabaseMissing('user_totp_settings', [
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
        ]);
    }
}
