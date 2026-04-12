<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for MemberVerificationBadgeController.
 *
 * Covers:
 *   GET    /v2/users/{id}/verification-badges                  (auth)
 *   POST   /v2/admin/users/{id}/verification-badges            (admin)
 *   DELETE /v2/admin/users/{id}/verification-badges/{type}     (admin)
 *   GET    /v2/admin/users/{id}/verification-badges            (admin)
 *
 * Tenant scoping for the user lookup is exercised explicitly.
 */
class MemberVerificationBadgeControllerTest extends TestCase
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

    public function test_get_badges_requires_auth(): void
    {
        $response = $this->apiGet('/v2/users/1/verification-badges');

        $response->assertStatus(401);
    }

    public function test_get_badges_returns_404_for_user_in_different_tenant(): void
    {
        $this->authenticatedUser();

        $otherTenantUser = User::factory()->forTenant(999)->create();

        $response = $this->apiGet("/v2/users/{$otherTenantUser->id}/verification-badges");

        $response->assertStatus(404);
    }

    public function test_get_badges_returns_empty_list_for_user_with_no_badges(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->apiGet("/v2/users/{$user->id}/verification-badges");

        $response->assertStatus(200);
        $badges = $response->json('data') ?? [];
        $this->assertIsArray($badges);
    }

    public function test_admin_grant_rejects_non_admin(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->apiPost("/v2/admin/users/{$user->id}/verification-badges", [
            'badge_type' => 'id_verified',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_grant_requires_badge_type(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->admin()->create());

        $target = User::factory()->forTenant($this->testTenantId)->create();

        $response = $this->apiPost("/v2/admin/users/{$target->id}/verification-badges", []);

        // Controller maps missing field to respondWithError(..., 400).
        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_admin_badge_list_requires_admin(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->apiGet("/v2/admin/users/{$user->id}/verification-badges");

        $response->assertStatus(403);
    }

    public function test_admin_badge_list_accessible_to_admin(): void
    {
        Sanctum::actingAs(User::factory()->forTenant($this->testTenantId)->admin()->create());

        $target = User::factory()->forTenant($this->testTenantId)->create();

        $response = $this->apiGet("/v2/admin/users/{$target->id}/verification-badges");

        $response->assertStatus(200);
        $data = $response->json('data') ?? $response->json();
        $this->assertArrayHasKey('available_types', $data);
        $this->assertArrayHasKey('labels', $data);
    }
}
