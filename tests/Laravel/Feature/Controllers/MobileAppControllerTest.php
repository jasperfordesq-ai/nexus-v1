<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for the mobile app version and logging endpoints.
 *
 * Routes are handled by AppController:
 *   GET  /app/version         — public
 *   POST /app/check-version   — public (rate-limited)
 *   POST /app/log             — public (rate-limited)
 */
class MobileAppControllerTest extends TestCase
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

    // ================================================================
    // VERSION — Public endpoint
    // ================================================================

    public function test_version_returns_200_without_auth(): void
    {
        $response = $this->apiGet('/app/version');

        $response->assertStatus(200);
    }

    public function test_version_returns_expected_structure(): void
    {
        $response = $this->apiGet('/app/version');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'version',
                'min_version',
                'update_url',
                'release_notes',
            ],
        ]);
    }

    public function test_version_returns_non_empty_version_string(): void
    {
        $response = $this->apiGet('/app/version');

        $response->assertStatus(200);

        $version = $response->json('data.version');
        $this->assertNotEmpty($version, 'version field must not be empty');
    }

    // ================================================================
    // CHECK VERSION — Happy path
    // ================================================================

    public function test_check_version_returns_200_with_valid_payload(): void
    {
        $response = $this->apiPost('/app/check-version', [
            'version'  => '1.0',
            'platform' => 'android',
        ]);

        $response->assertStatus(200);
    }

    public function test_check_version_returns_update_available_and_force_update_fields(): void
    {
        $response = $this->apiPost('/app/check-version', [
            'version'  => '1.0',
            'platform' => 'android',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'current_version',
                'client_version',
                'update_available',
                'force_update',
                'update_url',
            ],
        ]);
    }

    public function test_check_version_with_old_version_indicates_update_available(): void
    {
        $response = $this->apiPost('/app/check-version', [
            'version'  => '0.1',
            'platform' => 'android',
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertTrue((bool) $data['update_available'], 'update_available should be true for old version');
    }

    public function test_check_version_with_very_old_version_forces_update(): void
    {
        $response = $this->apiPost('/app/check-version', [
            'version'  => '0.1',
            'platform' => 'android',
        ]);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertTrue((bool) $data['force_update'], 'force_update should be true for version below minimum');
    }

    // ================================================================
    // LOG — Happy path
    // ================================================================

    public function test_log_returns_200(): void
    {
        $response = $this->apiPost('/app/log', [
            'event'    => 'app_opened',
            'version'  => '1.1',
            'platform' => 'android',
        ]);

        $response->assertStatus(200);
    }

    public function test_log_returns_success_response(): void
    {
        $response = $this->apiPost('/app/log', [
            'event'    => 'crash_report',
            'version'  => '1.0',
            'platform' => 'android',
            'data'     => ['screen' => 'feed'],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }
}
