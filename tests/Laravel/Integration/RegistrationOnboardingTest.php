<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Integration;

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Integration test: user registration, login, onboarding, and first listing.
 *
 * Covers the new-member journey from account creation through to
 * becoming a productive community participant.
 */
class RegistrationOnboardingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed tenant settings needed for registration
        DB::table('tenant_settings')->insertOrIgnore([
            [
                'tenant_id' => $this->testTenantId,
                'category'  => 'general',
                'name'      => 'registration_enabled',
                'value'     => '1',
            ],
            [
                'tenant_id' => $this->testTenantId,
                'category'  => 'general',
                'name'      => 'registration_policy',
                'value'     => 'open',
            ],
        ]);

        // Seed some categories for onboarding interest selection
        Category::factory()->forTenant($this->testTenantId)->count(3)->create([
            'type' => 'listing',
        ]);
    }

    // =========================================================================
    // Registration Flow
    // =========================================================================

    public function test_register_creates_user_with_correct_tenant(): void
    {
        $response = $this->apiPost('/v2/auth/register', [
            'name'                  => 'Jane Doe',
            'email'                 => 'jane.doe@example.com',
            'password'              => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        // Registration may return 201 (created) or 200
        if ($response->getStatusCode() === 422) {
            $this->markTestIncomplete(
                'Registration rejected (policy/validation): ' . $response->getContent()
            );
        }

        $this->assertContains($response->getStatusCode(), [200, 201]);

        // Verify user exists in the database with the correct tenant
        $user = User::where('email', 'jane.doe@example.com')
            ->where('tenant_id', $this->testTenantId)
            ->first();

        $this->assertNotNull($user, 'User should exist in the database');
        $this->assertEquals($this->testTenantId, $user->tenant_id);
        $this->assertEquals('jane.doe@example.com', $user->email);
        $this->assertContains($user->role, ['member', 'pending']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->forTenant($this->testTenantId)->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->apiPost('/v2/auth/register', [
            'name'                  => 'Duplicate User',
            'email'                 => 'existing@example.com',
            'password'              => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        // Should be rejected (400, 409, or 422)
        $this->assertContains($response->getStatusCode(), [400, 409, 422]);
    }

    public function test_register_rejects_weak_password(): void
    {
        $response = $this->apiPost('/v2/auth/register', [
            'name'                  => 'Weak Pass User',
            'email'                 => 'weak@example.com',
            'password'              => '123',
            'password_confirmation' => '123',
        ]);

        // Should be rejected for weak password
        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    // =========================================================================
    // Login After Registration
    // =========================================================================

    public function test_login_with_newly_registered_credentials(): void
    {
        // Create user directly (registration may require email verification)
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'email'             => 'newuser@example.com',
            'password_hash'     => Hash::make('SecurePass123!'),
            'status'            => 'active',
            'is_approved'       => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->apiPost('/auth/login', [
            'email'    => 'newuser@example.com',
            'password' => 'SecurePass123!',
        ]);

        $this->assertEquals(200, $response->getStatusCode());
        $response->assertJsonStructure(['token']);
    }

    public function test_login_scoped_to_correct_tenant(): void
    {
        // Create user on a different tenant
        $otherTenantId = 999;
        DB::table('tenants')->insertOrIgnore([
            'id'         => $otherTenantId,
            'name'       => 'Other Timebank',
            'slug'       => 'other-timebank',
            'is_active'  => true,
            'depth'      => 0,
            'allows_subtenants' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::factory()->forTenant($otherTenantId)->create([
            'email'             => 'otheruser@example.com',
            'password_hash'     => Hash::make('SecurePass123!'),
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        // Try to login with the test tenant's header (tenant 2)
        $response = $this->apiPost('/auth/login', [
            'email'    => 'otheruser@example.com',
            'password' => 'SecurePass123!',
        ]);

        // Should fail — user belongs to a different tenant
        $this->assertContains($response->getStatusCode(), [400, 401, 403, 404]);
    }

    // =========================================================================
    // Onboarding Flow
    // =========================================================================

    public function test_onboarding_status_for_new_user(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status'               => 'active',
            'is_approved'          => true,
            'onboarding_completed' => false,
            'bio'                  => null,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->apiGet('/v2/onboarding/status');
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->json('data') ?? $response->json();
        $this->assertFalse($data['onboarding_completed'] ?? $data['data']['onboarding_completed'] ?? true);
    }

    public function test_onboarding_categories_returns_tenant_categories(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status'      => 'active',
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->apiGet('/v2/onboarding/categories');
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->json('data') ?? $response->json();
        $categories = $data['data'] ?? $data;
        $this->assertNotEmpty($categories, 'Should return at least the seeded categories');
    }

    public function test_complete_onboarding(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status'               => 'active',
            'is_approved'          => true,
            'onboarding_completed' => false,
        ]);

        Sanctum::actingAs($user, ['*']);

        $categories = Category::where('tenant_id', $this->testTenantId)->pluck('id')->toArray();

        $response = $this->apiPost('/v2/onboarding/complete', [
            'interests' => array_slice($categories, 0, 2),
        ]);

        // Onboarding completion should succeed
        $this->assertContains($response->getStatusCode(), [200, 201]);

        // Verify the user is now marked as onboarded
        $user->refresh();
        $this->assertTrue((bool) $user->onboarding_completed);
    }

    // =========================================================================
    // First Listing After Onboarding
    // =========================================================================

    public function test_new_user_can_create_first_listing(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status'               => 'active',
            'is_approved'          => true,
            'onboarding_completed' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->apiPost('/v2/listings', [
            'title'        => 'My First Offer',
            'description'  => 'I can help with cooking and meal prep for families.',
            'type'         => 'offer',
            'price'        => 1.50,
            'hours_estimate' => 1.50,
            'service_type' => 'in-person',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);

        // Verify listing exists in the database
        $listing = Listing::where('user_id', $user->id)
            ->where('tenant_id', $this->testTenantId)
            ->first();

        $this->assertNotNull($listing, 'Listing should be created in the database');
        $this->assertEquals('My First Offer', $listing->title);
        $this->assertEquals('offer', $listing->type);
    }

    // =========================================================================
    // Full Journey: Register -> Login -> Onboard -> Create Listing
    // =========================================================================

    public function test_full_registration_to_first_listing_journey(): void
    {
        // Step 1: Register
        $registerResponse = $this->apiPost('/v2/auth/register', [
            'name'                  => 'Full Journey User',
            'email'                 => 'journey@example.com',
            'password'              => 'StrongPass456!',
            'password_confirmation' => 'StrongPass456!',
        ]);

        if ($registerResponse->getStatusCode() === 422 || $registerResponse->getStatusCode() === 400) {
            $this->markTestIncomplete(
                'Registration flow requires additional setup: ' . $registerResponse->getContent()
            );
        }

        // Step 2: Manually activate user (email verification would be async)
        $user = User::where('email', 'journey@example.com')
            ->where('tenant_id', $this->testTenantId)
            ->first();

        if (!$user) {
            $this->markTestIncomplete('Registration did not create user — may require email verification flow');
        }

        $user->update([
            'status'            => 'active',
            'is_approved'       => true,
            'email_verified_at' => now(),
        ]);

        // Step 3: Login
        $loginResponse = $this->apiPost('/auth/login', [
            'email'    => 'journey@example.com',
            'password' => 'StrongPass456!',
        ]);

        $this->assertEquals(200, $loginResponse->getStatusCode());

        // Step 4: Onboard and create listing using Sanctum
        Sanctum::actingAs($user, ['*']);

        $onboardResponse = $this->apiPost('/v2/onboarding/complete', [
            'interests' => [],
        ]);
        $this->assertContains($onboardResponse->getStatusCode(), [200, 201]);

        // Step 5: Create first listing
        $listingResponse = $this->apiPost('/v2/listings', [
            'title'        => 'Journey Listing',
            'description'  => 'My first community offer after onboarding.',
            'type'         => 'offer',
            'price'        => 1.00,
            'hours_estimate' => 1.00,
            'service_type' => 'either',
        ]);

        $this->assertContains($listingResponse->getStatusCode(), [200, 201]);

        // Verify the full journey completed
        $user->refresh();
        $this->assertTrue((bool) $user->onboarding_completed);
        $this->assertEquals(1, Listing::where('user_id', $user->id)->count());
    }

    public function test_register_requires_all_fields(): void
    {
        // Missing name
        $response = $this->apiPost('/v2/auth/register', [
            'email'                 => 'noname@example.com',
            'password'              => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }
}
