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
 * Feature smoke tests for GroupFilesController.
 */
class GroupFilesControllerTest extends TestCase
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

    public function test_index_requires_auth(): void
    {
        $this->apiGet('/v2/groups/1/files')->assertStatus(401);
    }

    public function test_store_requires_auth(): void
    {
        $this->apiPost('/v2/groups/1/files', [])->assertStatus(401);
    }

    public function test_folders_requires_auth(): void
    {
        $this->apiGet('/v2/groups/1/files/folders')->assertStatus(401);
    }

    public function test_stats_requires_auth(): void
    {
        $this->apiGet('/v2/groups/1/files/stats')->assertStatus(401);
    }

    public function test_download_requires_auth(): void
    {
        $this->apiGet('/v2/groups/1/files/1/download')->assertStatus(401);
    }

    public function test_destroy_requires_auth(): void
    {
        $this->apiDelete('/v2/groups/1/files/1')->assertStatus(401);
    }

    public function test_index_returns_non_5xx_when_authenticated(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/groups/1/files');
        $this->assertTrue($response->status() < 500, "Got 5xx: {$response->status()}");
    }

    public function test_stats_returns_non_5xx_when_authenticated(): void
    {
        $this->authenticatedUser();
        $response = $this->apiGet('/v2/groups/1/files/stats');
        $this->assertTrue($response->status() < 500, "Got 5xx: {$response->status()}");
    }
}
