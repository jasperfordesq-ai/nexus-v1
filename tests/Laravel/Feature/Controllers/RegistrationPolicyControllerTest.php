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
use App\Models\User;

/**
 * Feature tests for RegistrationPolicyController — verification status, invite codes.
 */
class RegistrationPolicyControllerTest extends TestCase
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

    // ------------------------------------------------------------------
    //  GET /v2/auth/verification-status (auth required)
    // ------------------------------------------------------------------

    public function test_verification_status_requires_auth(): void
    {
        $response = $this->apiGet('/v2/auth/verification-status');

        $response->assertStatus(401);
    }

    public function test_verification_status_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/auth/verification-status');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/auth/start-verification (auth required)
    // ------------------------------------------------------------------

    public function test_start_verification_requires_auth(): void
    {
        $response = $this->apiPost('/v2/auth/start-verification', [
            'provider' => 'email',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  POST /v2/auth/validate-invite (public pre-registration)
    // ------------------------------------------------------------------

    public function test_validate_invite_is_public_and_validates_input(): void
    {
        $response = $this->apiPost('/v2/auth/validate-invite', [
            'code' => '',
        ]);

        $response->assertStatus(400);
    }

    public function test_validate_invite_does_not_reveal_valid_codes_when_registration_is_closed(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        DB::table('tenant_invite_codes')->insert([
            'tenant_id' => $this->testTenantId,
            'code' => 'CLOSED01',
            'created_by' => $admin->id,
            'max_uses' => 1,
            'uses_count' => 0,
            'is_active' => 1,
            'created_at' => now(),
        ]);
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.registration_mode'],
            [
                'setting_value' => 'closed',
                'setting_type' => 'string',
                'updated_at' => now(),
            ]
        );
        app(\App\Services\TenantSettingsService::class)->clearCacheForTenant($this->testTenantId);

        $response = $this->apiPost('/v2/auth/validate-invite', [
            'code' => 'CLOSED01',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.valid', false);
        $response->assertJsonPath('data.reason', 'registration_closed');
    }

    public function test_validate_invite_still_reports_valid_code_when_registration_allows_invites(): void
    {
        $admin = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        DB::table('tenant_invite_codes')->insert([
            'tenant_id' => $this->testTenantId,
            'code' => 'INVITE01',
            'created_by' => $admin->id,
            'max_uses' => 1,
            'uses_count' => 0,
            'is_active' => 1,
            'created_at' => now(),
        ]);
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.registration_mode'],
            [
                'setting_value' => 'invite_only',
                'setting_type' => 'string',
                'updated_at' => now(),
            ]
        );
        app(\App\Services\TenantSettingsService::class)->clearCacheForTenant($this->testTenantId);

        $response = $this->apiPost('/v2/auth/validate-invite', [
            'code' => 'INVITE01',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.valid', true);
        $response->assertJsonPath('data.reason', null);
    }

    // ------------------------------------------------------------------
    //  GET /v2/auth/registration-info (public pre-registration)
    // ------------------------------------------------------------------

    public function test_registration_info_is_public_and_returns_data(): void
    {
        $response = $this->apiGet('/v2/auth/registration-info');

        $response->assertStatus(200);
    }

    public function test_registration_info_reports_admin_closed_mode_even_when_policy_is_open(): void
    {
        DB::table('tenant_registration_policies')->updateOrInsert(
            ['tenant_id' => $this->testTenantId],
            [
                'registration_mode' => 'open',
                'verification_level' => 'none',
                'post_verification' => 'activate',
                'fallback_mode' => 'none',
                'require_email_verify' => 1,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'general.registration_mode'],
            [
                'setting_value' => 'closed',
                'setting_type' => 'string',
                'updated_at' => now(),
            ]
        );
        app(\App\Services\TenantSettingsService::class)->clearCacheForTenant($this->testTenantId);

        $response = $this->apiGet('/v2/auth/registration-info');

        $response->assertOk();
        $response->assertJsonPath('data.registration_mode', 'closed');
        $response->assertJsonPath('data.is_closed', true);
        $response->assertJsonPath('data.can_register', false);
        $response->assertJsonPath('data.requires_invite_code', false);
    }
}
