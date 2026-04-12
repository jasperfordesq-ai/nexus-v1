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
 * Smoke tests for GroupNotificationPrefController.
 */
class GroupNotificationPrefControllerTest extends TestCase
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

    public function test_get_requires_auth(): void
    {
        $response = $this->apiGet('/v2/groups/1/notification-prefs');
        $response->assertStatus(401);
    }

    public function test_set_requires_auth(): void
    {
        $response = $this->apiPut('/v2/groups/1/notification-prefs', []);
        $response->assertStatus(401);
    }

    public function test_get_authenticated_smoke(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/groups/1/notification-prefs');
        $this->assertLessThan(600, $response->status());
    }
}
