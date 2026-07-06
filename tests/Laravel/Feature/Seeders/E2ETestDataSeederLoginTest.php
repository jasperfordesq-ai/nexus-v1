<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Seeders;

use App\Services\TenantSettingsService;
use Database\Seeders\E2ETestDataSeeder;
use Database\Seeders\TenantSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Regression: the E2E fixture seeder must produce members who can actually log
 * in. CheckLoginGates keys email verification on `email_verified_at`, not
 * `is_verified`; the seeder set `is_verified=1` but left `email_verified_at`
 * NULL, so a freshly-seeded User B was rejected at login with
 * AUTH_EMAIL_NOT_VERIFIED on any tenant requiring email verification (the
 * fail-closed default) — silently breaking every E2E/journey/deploy-gate flow
 * that authenticates as the secondary actor.
 */
class E2ETestDataSeederLoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(TenantSeeder::class);

        // Make the email-verification login gate active for the master tenant so
        // this test exercises the exact branch that rejected the unverified
        // seeded user (don't rely on the fail-closed default being in effect).
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => TenantSeeder::MASTER_TENANT_ID, 'setting_key' => 'email_verification'],
            ['setting_value' => 'true', 'setting_type' => 'boolean', 'updated_at' => now()]
        );
        app(TenantSettingsService::class)->clearCacheForTenant(TenantSeeder::MASTER_TENANT_ID);
    }

    public function test_seeded_members_pass_the_email_verification_login_gate(): void
    {
        // The seeder reads E2E_TENANT_ID (default 1 == master tenant).
        $this->seed(E2ETestDataSeeder::class);

        $gates = app(TenantSettingsService::class);

        foreach (['e2e.user.a@project-nexus.local', 'e2e.user.b@project-nexus.local'] as $email) {
            $user = (array) DB::table('users')
                ->where('tenant_id', TenantSeeder::MASTER_TENANT_ID)
                ->where('email', $email)
                ->first();

            $this->assertNotEmpty($user, "seeded member {$email} should exist");
            $this->assertNotNull(
                $user['email_verified_at'] ?? null,
                "{$email} must have email_verified_at set or the email-verify login gate rejects it"
            );

            $gateError = $gates->checkLoginGatesForUser($user);
            $this->assertNull(
                $gateError,
                "{$email} must pass all login gates, got: " . json_encode($gateError)
            );
        }
    }
}
