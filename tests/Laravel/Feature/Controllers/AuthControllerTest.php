<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for AuthController.
 *
 * Covers login, logout, refresh-token, heartbeat, and session endpoints.
 */
class AuthControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // LOGIN — Happy path
    // ================================================================

    public function test_login_returns_token_and_user_on_valid_credentials(): void
    {
        User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'auth@example.com',
            'password_hash' => Hash::make('correct-password'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->apiPost('/auth/login', [
            'email' => 'auth@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'user' => ['id', 'first_name', 'last_name', 'email', 'tenant_id', 'role'],
            'access_token',
            'refresh_token',
            'token_type',
            'expires_in',
        ]);
        $response->assertJson(['success' => true]);
    }

    // ================================================================
    // LOGIN — Validation errors
    // ================================================================

    public function test_login_returns_400_when_email_missing(): void
    {
        $response = $this->apiPost('/auth/login', [
            'password' => 'secret',
        ]);

        $response->assertStatus(400);
    }

    public function test_login_returns_400_when_password_missing(): void
    {
        $response = $this->apiPost('/auth/login', [
            'email' => 'auth@example.com',
        ]);

        $response->assertStatus(400);
    }

    public function test_login_returns_400_when_both_fields_empty(): void
    {
        $response = $this->apiPost('/auth/login', []);

        $response->assertStatus(400);
    }

    // ================================================================
    // LOGIN — Invalid credentials (401)
    // ================================================================

    public function test_login_returns_401_with_wrong_password(): void
    {
        User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'auth@example.com',
            'password_hash' => Hash::make('correct-password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->apiPost('/auth/login', [
            'email' => 'auth@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_returns_401_for_nonexistent_email(): void
    {
        $response = $this->apiPost('/auth/login', [
            'email' => 'no-such-user@example.com',
            'password' => 'irrelevant',
        ]);

        $response->assertStatus(401);
    }

    // ================================================================
    // LOGIN — Tenant isolation
    // ================================================================

    public function test_login_rejects_user_from_different_tenant(): void
    {
        // Seed a second tenant
        \Illuminate\Support\Facades\DB::table('tenants')->insertOrIgnore([
            'id' => 999,
            'name' => 'Other Timebank',
            'slug' => 'other-timebank',
            'is_active' => true,
            'depth' => 0,
            'allows_subtenants' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::factory()->forTenant(999)->create([
            'email' => 'other-tenant@example.com',
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $response = $this->apiPost('/auth/login', [
            'email' => 'other-tenant@example.com',
            'password' => 'secret123',
        ]);

        $this->assertContains($response->getStatusCode(), [400, 401, 403]);
    }

    // ================================================================
    // LOGOUT
    // ================================================================

    public function test_logout_returns_success(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->apiPost('/auth/logout');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    // ================================================================
    // REFRESH TOKEN — Validation
    // ================================================================

    public function test_refresh_token_returns_400_when_token_missing(): void
    {
        $response = $this->apiPost('/auth/refresh-token', []);

        $response->assertStatus(400);
    }

    public function test_refresh_token_returns_401_with_invalid_token(): void
    {
        $response = $this->apiPost('/auth/refresh-token', [
            'refresh_token' => 'invalid-bogus-token',
        ]);

        $response->assertStatus(401);
    }

    // ================================================================
    // HEARTBEAT — Authentication required
    // ================================================================

    public function test_heartbeat_returns_401_without_auth(): void
    {
        $response = $this->apiPost('/auth/heartbeat');

        $response->assertStatus(401);
    }

    // ================================================================
    // CSRF TOKEN — Public endpoint
    // ================================================================

    public function test_csrf_token_endpoint_returns_200(): void
    {
        $response = $this->apiGet('/auth/csrf-token');

        $response->assertStatus(200);
    }

    // ================================================================
    // VALIDATE TOKEN
    // ================================================================

    public function test_validate_token_without_bearer_returns_error(): void
    {
        $response = $this->apiGet('/auth/validate-token');

        // Without a token it should indicate invalid/missing
        $this->assertContains($response->getStatusCode(), [200, 401]);
    }
}
