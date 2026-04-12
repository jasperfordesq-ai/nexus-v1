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
 * Feature tests for RealtimeController — GET /v2/realtime/config.
 *
 * Returns Pusher / WebSocket configuration for authenticated users.
 */
class RealtimeControllerTest extends TestCase
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

    public function test_config_requires_auth(): void
    {
        $response = $this->apiGet('/v2/realtime/config');

        $response->assertStatus(401);
    }

    public function test_config_returns_200_for_authenticated_user(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/realtime/config');

        $response->assertStatus(200);
    }

    public function test_config_shape_includes_required_keys(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/realtime/config');

        $response->assertStatus(200);
        $data = $response->json('data') ?? $response->json();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('driver', $data);
        $this->assertArrayHasKey('key', $data);
        $this->assertArrayHasKey('cluster', $data);
        $this->assertArrayHasKey('force_tls', $data);
    }

    public function test_config_driver_is_pusher(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/realtime/config');

        $response->assertStatus(200);
        $driver = $response->json('data.driver') ?? $response->json('driver');
        $this->assertSame('pusher', $driver);
    }
}
