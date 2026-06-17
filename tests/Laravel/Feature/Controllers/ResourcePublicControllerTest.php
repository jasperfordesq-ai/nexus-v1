<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Feature tests for ResourcePublicController — public resource library.
 */
class ResourcePublicControllerTest extends TestCase
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
    //  GET /v2/resources
    // ------------------------------------------------------------------

    public function test_index_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/resources');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/resources/categories
    // ------------------------------------------------------------------

    public function test_categories_requires_auth(): void
    {
        $response = $this->apiGet('/v2/resources/categories');

        $response->assertStatus(401);
    }

    public function test_categories_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/resources/categories');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/resources
    // ------------------------------------------------------------------

    public function test_store_requires_auth(): void
    {
        $response = $this->apiPost('/v2/resources', [
            'title' => 'Test Resource',
            'description' => 'A helpful resource',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_rejects_allowed_extension_when_detected_mime_is_not_allowed(): void
    {
        $this->authenticatedUser();
        $uploadDir = base_path('httpdocs/uploads/' . $this->testTenantId . '/resources');
        $before = is_dir($uploadDir) ? glob($uploadDir . '/*') ?: [] : [];

        $response = $this->apiPost('/v2/resources', [
            'title' => 'Disguised executable',
            'file' => UploadedFile::fake()->createWithContent('not-a-real.pdf', 'MZ' . str_repeat("\0", 512)),
        ]);

        $after = is_dir($uploadDir) ? glob($uploadDir . '/*') ?: [] : [];
        foreach (array_diff($after, $before) as $createdFile) {
            @unlink($createdFile);
        }

        $response->assertStatus(400);
    }

    // ------------------------------------------------------------------
    //  DELETE /v2/resources/{id}
    // ------------------------------------------------------------------

    public function test_destroy_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/resources/1');

        $response->assertStatus(401);
    }
}
