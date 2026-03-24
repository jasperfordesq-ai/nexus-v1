<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Regression;

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Regression tests: existing flows must not break after onboarding module changes.
 */
class OnboardingRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Category::factory()->forTenant($this->testTenantId)->count(3)->create([
            'type' => 'listing',
        ]);
    }

    public function test_existing_onboarding_flow_works_without_config(): void
    {
        // Clear ALL onboarding settings — simulate a tenant with no config
        DB::table('tenant_settings')
            ->where('tenant_id', $this->testTenantId)
            ->where('setting_key', 'LIKE', 'onboarding.%')
            ->delete();

        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'onboarding_completed' => false,
            'avatar_url' => 'https://example.com/photo.jpg',
            'bio' => 'A test bio that is long enough to pass validation.',
        ]);
        Sanctum::actingAs($user, ['*']);

        // Onboarding status should still work
        $response = $this->apiGet('/v2/onboarding/status');
        $this->assertEquals(200, $response->getStatusCode());

        // Onboarding config should return defaults
        $response = $this->apiGet('/v2/onboarding/config');
        $this->assertEquals(200, $response->getStatusCode());

        // Completion should still work (user has avatar+bio)
        $response = $this->apiPost('/v2/onboarding/complete', [
            'interests' => [],
        ]);
        $this->assertContains($response->getStatusCode(), [200, 201]);
    }

    public function test_no_auto_listing_creation_with_default_config(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'onboarding_completed' => false,
            'avatar_url' => 'https://example.com/photo.jpg',
            'bio' => 'A test bio that is long enough to pass validation.',
        ]);
        Sanctum::actingAs($user, ['*']);

        $categories = Category::where('tenant_id', $this->testTenantId)->pluck('id')->toArray();

        $response = $this->apiPost('/v2/onboarding/complete', [
            'interests' => array_slice($categories, 0, 2),
            'offers' => array_slice($categories, 0, 2),
            'needs' => array_slice($categories, 0, 1),
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);

        // Phase 0 regression: NO listings should be auto-created (default mode = disabled)
        $listingCount = Listing::where('user_id', $user->id)
            ->where('tenant_id', $this->testTenantId)
            ->count();
        $this->assertEquals(0, $listingCount, 'Default listing_creation_mode=disabled must prevent auto-creation');
    }

    public function test_registration_flow_unchanged(): void
    {
        $response = $this->apiPost('/v2/auth/register', [
            'name' => 'Regression Test User',
            'email' => 'regression-test-' . uniqid() . '@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        // Should succeed or reject for policy reasons — NOT 500
        $this->assertContains($response->getStatusCode(), [200, 201, 400, 422]);
    }

    public function test_manual_listing_creation_unchanged(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'onboarding_completed' => true,
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->apiPost('/v2/listings', [
            'title' => 'Regression Test Listing',
            'description' => 'This listing should be created normally.',
            'type' => 'offer',
            'price' => 1.00,
            'hours_estimate' => 1.00,
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
    }

    public function test_onboarding_categories_still_works(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->apiGet('/v2/onboarding/categories');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_completed_users_not_affected_by_config_changes(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'onboarding_completed' => true,
        ]);

        // Change onboarding settings
        DB::table('tenant_settings')->updateOrInsert(
            ['tenant_id' => $this->testTenantId, 'setting_key' => 'onboarding.mandatory'],
            ['setting_value' => '1', 'setting_type' => 'boolean']
        );

        // User with completed onboarding should still be fine
        $user->refresh();
        $this->assertTrue((bool) $user->onboarding_completed);
    }

    public function test_safeguarding_options_endpoint_works_for_authenticated_user(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->apiGet('/v2/onboarding/safeguarding-options');
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_onboarding_config_endpoint_works_for_authenticated_user(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->apiGet('/v2/onboarding/config');
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->json('data') ?? $response->json();
        $responseData = $data['data'] ?? $data;
        $this->assertArrayHasKey('config', $responseData);
        $this->assertArrayHasKey('steps', $responseData);
    }
}
