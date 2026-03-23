<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for authentication endpoints.
 *
 * Tests login, token issuance, and protected route access
 * using Laravel Sanctum.
 */
class AuthTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test that the login endpoint returns a token for valid credentials.
     */
    public function test_login_returns_token_with_valid_credentials(): void
    {
        $email = 'test_' . uniqid() . '@example.com';
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email' => $email,
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->apiPost('/auth/login', [
            'email' => $email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(200);
        // Login returns token at top level (not nested under 'data')
        $response->assertJsonStructure([
            'token',
        ]);
    }

    /**
     * Test that login fails with invalid credentials.
     */
    public function test_login_rejects_invalid_credentials(): void
    {
        $email = 'test_' . uniqid() . '@example.com';
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email' => $email,
            'password_hash' => Hash::make('secret123'),
        ]);

        $response = $this->apiPost('/auth/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ]);

        // Should return an error (401 or 400 depending on implementation)
        $response->assertStatus(401);
    }

    /**
     * Test that login fails with a missing email field.
     */
    public function test_login_rejects_missing_email(): void
    {
        $response = $this->apiPost('/auth/login', [
            'password' => 'secret123',
        ]);

        // Should return a validation/auth error
        $response->assertStatus(400);
    }

    /**
     * Test that an authenticated route works with a valid Sanctum token.
     */
    public function test_authenticated_route_works_with_sanctum_token(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        // The /v2/users/me endpoint requires authentication
        $response = $this->apiGet('/v2/users/me');

        // Should not be a 401
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    /**
     * Test that an authenticated route rejects requests without a token.
     */
    public function test_authenticated_route_rejects_without_token(): void
    {
        // The /v2/users/me endpoint requires authentication.
        // Without Sanctum::actingAs() and without a session, this should fail.
        $response = $this->apiGet('/v2/users/me');

        // Expect 401 Unauthorized (or 403 depending on controller logic)
        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    /**
     * Test that login is tenant-scoped — a user from tenant 3 cannot
     * log in when requesting against tenant 2.
     */
    public function test_login_is_tenant_scoped(): void
    {
        $email = 'other_tenant_' . uniqid() . '@example.com';
        // Create a user on a DIFFERENT tenant
        User::factory()->forTenant(999)->create([
            'email' => $email,
            'password_hash' => Hash::make('secret123'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        // Attempt login against the default test tenant (2)
        $response = $this->apiPost('/auth/login', [
            'email' => $email,
            'password' => 'secret123',
        ]);

        // Should not succeed — user is on a different tenant
        $this->assertContains($response->getStatusCode(), [400, 401, 403, 404]);
    }
}
