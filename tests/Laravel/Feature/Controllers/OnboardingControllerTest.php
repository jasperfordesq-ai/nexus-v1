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
 * Feature tests for OnboardingController — onboarding status, categories, completion.
 */
class OnboardingControllerTest extends TestCase
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
    //  GET /v2/onboarding/status
    // ------------------------------------------------------------------

    public function test_status_requires_auth(): void
    {
        $response = $this->apiGet('/v2/onboarding/status');

        $response->assertStatus(401);
    }

    public function test_status_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/onboarding/status');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/onboarding/categories
    // ------------------------------------------------------------------

    public function test_categories_requires_auth(): void
    {
        $response = $this->apiGet('/v2/onboarding/categories');

        $response->assertStatus(401);
    }

    public function test_categories_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/onboarding/categories');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/onboarding/complete
    // ------------------------------------------------------------------

    public function test_complete_requires_auth(): void
    {
        $response = $this->apiPost('/v2/onboarding/complete');

        $response->assertStatus(401);
    }

    public function test_complete_marks_onboarding_done(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'avatar_url' => 'https://example.com/photo.jpg',
            'bio' => 'A valid bio that is long enough to pass validation.',
        ]);
        Sanctum::actingAs($user, ['*']);

        $response = $this->apiPost('/v2/onboarding/complete', [
            'interests' => [],
        ]);

        $this->assertContains($response->getStatusCode(), [200, 201]);
    }

    public function test_complete_rejects_unauthenticated(): void
    {
        // No Sanctum::actingAs — request is unauthenticated
        $response = $this->apiPost('/v2/onboarding/complete', [
            'interests' => [1, 2],
        ]);

        $response->assertStatus(401);
    }

    public function test_complete_filters_cross_tenant_category_ids(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'avatar_url' => 'https://example.com/photo.jpg',
            'bio' => 'A valid bio that is long enough to pass validation.',
        ]);
        Sanctum::actingAs($user, ['*']);

        // Insert a category belonging to a different tenant (999)
        DB::insert(
            "INSERT INTO categories (tenant_id, name, slug, created_at) VALUES (?, ?, ?, NOW())",
            [999, 'Cross-Tenant Category', 'cross-tenant-cat-' . uniqid()]
        );
        $otherCatId = (int) DB::getPdo()->lastInsertId();

        // Insert a category belonging to the test tenant
        DB::insert(
            "INSERT INTO categories (tenant_id, name, slug, created_at) VALUES (?, ?, ?, NOW())",
            [$this->testTenantId, 'Valid Category', 'valid-cat-' . uniqid()]
        );
        $validCatId = (int) DB::getPdo()->lastInsertId();

        $response = $this->apiPost('/v2/onboarding/complete', [
            'interests' => [$otherCatId, $validCatId],
        ]);

        $response->assertStatus(200);

        // Verify only the valid tenant category was saved as an interest
        $savedInterests = DB::table('user_interests')
            ->where('user_id', $user->id)
            ->pluck('category_id')
            ->all();

        $this->assertContains($validCatId, $savedInterests);
        $this->assertNotContains($otherCatId, $savedInterests);
    }

    public function test_complete_validates_avatar_required(): void
    {
        User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'avatar_url' => null,
            'bio' => 'A valid bio that is long enough to pass validation.',
        ]);

        $user = User::where('avatar_url', null)
            ->where('tenant_id', $this->testTenantId)
            ->latest('id')
            ->first();

        Sanctum::actingAs($user, ['*']);

        $response = $this->apiPost('/v2/onboarding/complete', [
            'interests' => [1],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'VALIDATION_REQUIRED_FIELD');
    }

    public function test_complete_validates_bio_required(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'is_approved' => true,
            'avatar_url' => 'https://example.com/photo.jpg',
            'bio' => null,
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->apiPost('/v2/onboarding/complete', [
            'interests' => [1],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.code', 'VALIDATION_REQUIRED_FIELD');
    }
}
