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
 * Feature tests for VolunteerCheckInController — shift check-in/out.
 */
class VolunteerCheckInControllerTest extends TestCase
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

    public function test_get_check_in_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/shifts/1/checkin');

        $response->assertStatus(401);
    }

    public function test_shift_check_ins_requires_auth(): void
    {
        $response = $this->apiGet('/v2/volunteering/shifts/1/checkins');

        $response->assertStatus(401);
    }

    public function test_get_check_in_authenticated_smoke(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/shifts/1/checkin');

        $this->assertLessThan(500, $response->status());
    }

    public function test_shift_check_ins_authenticated_smoke(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/volunteering/shifts/1/checkins');

        $this->assertLessThan(500, $response->status());
    }
}
