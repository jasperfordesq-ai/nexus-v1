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
 * Feature tests for AppController — Mobile app version check and logging.
 */
class AppControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status' => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ------------------------------------------------------------------
    //  POST /app/check-version
    // ------------------------------------------------------------------

    public function test_check_version_requires_auth(): void
    {
        $response = $this->apiPost('/app/check-version', ['version' => '1.0']);

        $response->assertStatus(401);
    }

    public function test_check_version_returns_status(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/app/check-version', ['version' => '1.0']);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ------------------------------------------------------------------
    //  GET /app/version
    // ------------------------------------------------------------------

    public function test_version_requires_auth(): void
    {
        $response = $this->apiGet('/app/version');

        $response->assertStatus(401);
    }

    public function test_version_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/app/version');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /app/log
    // ------------------------------------------------------------------

    public function test_log_requires_auth(): void
    {
        $response = $this->apiPost('/app/log', ['message' => 'test log']);

        $response->assertStatus(401);
    }

    public function test_log_accepts_message(): void
    {
        $this->authenticatedUser();

        $response = $this->apiPost('/app/log', [
            'level' => 'info',
            'message' => 'Test log message',
        ]);

        $this->assertContains($response->getStatusCode(), [200, 204]);
    }
}
