<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use App\Services\EmailDispatchService;
use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Feature tests for EmailVerificationController — email verification and resend.
 *
 * These are public endpoints (rate-limited) — no auth required.
 */
class EmailVerificationControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ------------------------------------------------------------------
    //  POST /auth/verify-email (public, rate-limited)
    // ------------------------------------------------------------------

    public function test_verify_email_requires_token(): void
    {
        $response = $this->apiPost('/auth/verify-email', []);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_verify_email_rejects_invalid_token(): void
    {
        $response = $this->apiPost('/auth/verify-email', [
            'token' => 'invalid-token-abc123',
        ]);

        $this->assertContains($response->getStatusCode(), [400, 404, 422]);
    }

    // ------------------------------------------------------------------
    //  POST /auth/resend-verification (public, rate-limited)
    // ------------------------------------------------------------------

    public function test_resend_verification_requires_email(): void
    {
        $response = $this->apiPost('/auth/resend-verification', []);

        $this->assertContains($response->getStatusCode(), [400, 401, 422]);
    }

    public function test_resend_verification_does_not_rotate_token_when_send_fails(): void
    {
        $this->ensureEmailVerificationTokenTable();

        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email_verified_at' => null,
            'is_verified' => false,
            'preferred_language' => 'en',
        ]);
        Sanctum::actingAs($user, ['*']);

        $oldTokenId = DB::table('email_verification_tokens')->insertGetId([
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'token' => password_hash('still-valid-token', PASSWORD_DEFAULT),
            'expires_at' => now()->addDay(),
        ]);

        app()->instance(EmailDispatchService::class, new FailingEmailDispatchService());

        $response = $this->apiPost('/auth/resend-verification', []);

        $response->assertStatus(503);
        $this->assertSame(1, DB::table('email_verification_tokens')
            ->where('user_id', $user->id)
            ->where('tenant_id', $this->testTenantId)
            ->count());
        $this->assertTrue(DB::table('email_verification_tokens')->where('id', $oldTokenId)->exists());
    }

    public function test_resend_verification_replaces_token_after_send_acceptance(): void
    {
        $this->ensureEmailVerificationTokenTable();

        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email_verified_at' => null,
            'is_verified' => false,
            'preferred_language' => 'en',
        ]);
        Sanctum::actingAs($user, ['*']);

        $oldTokenId = DB::table('email_verification_tokens')->insertGetId([
            'user_id' => $user->id,
            'tenant_id' => $this->testTenantId,
            'token' => password_hash('old-token', PASSWORD_DEFAULT),
            'expires_at' => now()->addDay(),
        ]);

        app()->instance(EmailDispatchService::class, new SuccessfulEmailDispatchService());

        $response = $this->apiPost('/auth/resend-verification', []);

        $response->assertOk();
        $this->assertSame(1, DB::table('email_verification_tokens')
            ->where('user_id', $user->id)
            ->where('tenant_id', $this->testTenantId)
            ->count());
        $this->assertFalse(DB::table('email_verification_tokens')->where('id', $oldTokenId)->exists());
    }

    // ------------------------------------------------------------------
    //  POST /auth/resend-verification-by-email (public, rate-limited)
    // ------------------------------------------------------------------

    public function test_resend_verification_by_email_requires_email(): void
    {
        $response = $this->apiPost('/auth/resend-verification-by-email', []);

        $this->assertContains($response->getStatusCode(), [200, 429]);
    }

    private function ensureEmailVerificationTokenTable(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id` INT UNSIGNED NOT NULL,
                `tenant_id` INT(11) NOT NULL,
                `token` VARCHAR(255) NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `expires_at` TIMESTAMP NOT NULL,
                INDEX `idx_user_id` (`user_id`),
                INDEX `idx_tenant_id` (`tenant_id`),
                INDEX `idx_tenant_user` (`tenant_id`, `user_id`),
                INDEX `idx_expires_at` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

class FailingEmailDispatchService extends EmailDispatchService
{
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        return false;
    }
}

class SuccessfulEmailDispatchService extends EmailDispatchService
{
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        return true;
    }
}
