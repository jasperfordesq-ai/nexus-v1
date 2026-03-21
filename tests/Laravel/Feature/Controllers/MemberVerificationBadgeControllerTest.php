<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Tests\Laravel\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use App\Models\User;

/**
 * Feature tests for MemberVerificationBadgeController — verification badges.
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

    // ------------------------------------------------------------------
    //  GET /v2/users/{id}/verification-badges
    // ------------------------------------------------------------------

    public function test_get_badges_requires_auth(): void
    {
        $response = $this->apiGet('/v2/users/1/verification-badges');

        $response->assertStatus(401);
    }

    public function test_get_badges_returns_data(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->apiGet("/v2/users/{$user->id}/verification-badges");

        $response->assertStatus(200);
    }
}
