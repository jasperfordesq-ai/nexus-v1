<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Services;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AuthService — login, logout, token validation,
 * rate limiting, and tenant isolation.
 *
 * Authentication is a critical security surface. These tests verify
 * credential checks, brute force protection, and cross-tenant isolation.
 */
class AuthServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AuthService::class);
        RateLimiter::clear('login:127.0.0.1:test@example.com');
    }

    // ------------------------------------------------------------------
    //  LOGIN — Happy path
    // ------------------------------------------------------------------

    public function test_login_returns_user_and_token_on_valid_credentials(): void
    {
        // NOTE: AuthService::login() checks the `password` column and Hash::check against
        // it (the live login path is AuthController::login, which uses password_hash + JWT;
        // AuthService::login is a legacy service method — see docs/MORNING-REPORT.md). To
        // exercise its success branch we must populate the column it actually reads.
        $email = 'authsvc_' . uniqid() . '@example.com';
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email'         => $email,
            'password_hash' => Hash::make('correct-password'),
            'status'        => 'active',
            'is_approved'   => true,
        ]);
        // `password` is not mass-assignable; set the column AuthService::login reads directly.
        \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)
            ->update(['password' => Hash::make('correct-password')]);

        // Re-pin: factory create drifts TenantContext, and login() runs a tenant-scoped
        // User query — it must resolve against the tenant the user was created in.
        \App\Core\TenantContext::setById($this->testTenantId);

        try {
            $result = $this->service->login($email, 'correct-password');
        } catch (\Illuminate\Database\QueryException $e) {
            // On success login issues a token via createApiToken() -> INSERT INTO api_tokens,
            // which is absent from the lean local nexus_test but present in full CI.
            $this->markTestSkipped('api_tokens table absent — token issuance cannot run in this DB');
        }

        // Valid credentials -> a real session payload carrying the user and an issued token.
        $this->assertIsArray($result, 'Valid credentials must return a session array, not null');
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertSame($email, $result['user']['email']);
        $this->assertNotEmpty($result['token']);
    }

    // ------------------------------------------------------------------
    //  LOGIN — Invalid credentials
    // ------------------------------------------------------------------

    public function test_login_returns_null_on_wrong_password(): void
    {
        $email = 'authsvc_' . uniqid() . '@example.com';
        User::factory()->forTenant($this->testTenantId)->create([
            'email' => $email,
            'password_hash' => Hash::make('correct-password'),
            'status' => 'active',
        ]);

        $result = $this->service->login($email, 'wrong-password');

        $this->assertNull($result);
    }

    public function test_login_returns_null_for_nonexistent_email(): void
    {
        $result = $this->service->login('nobody@example.com', 'anything');

        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    //  LOGIN — Inactive user
    // ------------------------------------------------------------------

    public function test_login_rejects_inactive_user(): void
    {
        $email = 'authsvc_' . uniqid() . '@example.com';
        User::factory()->forTenant($this->testTenantId)->create([
            'email' => $email,
            'password_hash' => Hash::make('correct-password'),
            'status' => 'suspended',
        ]);

        $result = $this->service->login($email, 'correct-password');

        $this->assertNull($result);
    }

    public function test_login_rejects_pending_user(): void
    {
        $email = 'authsvc_' . uniqid() . '@example.com';
        User::factory()->forTenant($this->testTenantId)->create([
            'email' => $email,
            'password_hash' => Hash::make('correct-password'),
            'status' => 'pending',
        ]);

        $result = $this->service->login($email, 'correct-password');

        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    //  LOGIN — Tenant isolation
    // ------------------------------------------------------------------

    public function test_login_cannot_authenticate_user_from_other_tenant(): void
    {
        $email = 'authsvc_other_' . uniqid() . '@example.com';
        User::factory()->forTenant(999)->create([
            'email' => $email,
            'password_hash' => Hash::make('correct-password'),
            'status' => 'active',
        ]);

        // TenantContext is set to tenant 2 (testTenantId) by TestCase.
        // User belongs to tenant 999 — should not be found.
        $result = $this->service->login($email, 'correct-password');

        $this->assertNull($result);
    }

    // ------------------------------------------------------------------
    //  RATE LIMITING
    // ------------------------------------------------------------------

    public function test_login_rate_limits_after_five_attempts(): void
    {
        $email = 'authsvc_ratelimit_' . uniqid() . '@example.com';

        // Exhaust the rate limit (5 attempts)
        for ($i = 0; $i < 5; $i++) {
            $this->service->login($email, 'bad-password');
        }

        // 6th attempt should be rate-limited
        $result = $this->service->login($email, 'bad-password');

        // Rate-limited response returns an error array instead of null
        $this->assertIsArray($result);
        $this->assertFalse($result['success'] ?? true);
        $this->assertStringContainsString('Too many login attempts', $result['error'] ?? '');
    }

    // ------------------------------------------------------------------
    //  TOKEN VALIDATION
    // ------------------------------------------------------------------

    public function test_validate_token_returns_null_for_invalid_token(): void
    {
        try {
            $result = $this->service->validateToken('completely-bogus-token');
            $this->assertNull($result);
        } catch (\Illuminate\Database\QueryException $e) {
            $this->markTestSkipped('api_tokens table does not exist in test database');
        }
    }

    // ------------------------------------------------------------------
    //  LOGOUT
    // ------------------------------------------------------------------

    public function test_logout_returns_false_for_nonexistent_token(): void
    {
        try {
            $result = $this->service->logout('nonexistent-token');
            $this->assertFalse($result);
        } catch (\Illuminate\Database\QueryException $e) {
            $this->markTestSkipped('api_tokens table does not exist in test database');
        }
    }
}
