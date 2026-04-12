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
 * Feature tests for BookmarkController.
 *
 * Covers authentication gating and input validation on the bookmark
 * endpoints. Database-level behaviour is covered by service tests.
 */
class BookmarkControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // Auth gating
    // ================================================================

    public function test_toggle_requires_auth(): void
    {
        $response = $this->apiPost('/v2/bookmarks', [
            'type' => 'listing',
            'id' => 1,
        ]);

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_index_requires_auth(): void
    {
        $response = $this->apiGet('/v2/bookmarks');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_status_requires_auth(): void
    {
        $response = $this->apiGet('/v2/bookmarks/status?type=listing&id=1');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_collections_requires_auth(): void
    {
        $response = $this->apiGet('/v2/bookmark-collections');

        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    // ================================================================
    // Input validation — no DB writes
    // ================================================================

    public function test_toggle_returns_error_for_missing_type_and_id(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiPost('/v2/bookmarks', []);

        // Controller returns respondWithError('INVALID_INPUT', ...) which maps to 4xx
        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_status_returns_error_for_missing_params(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiGet('/v2/bookmarks/status');

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }

    public function test_create_collection_rejects_empty_name(): void
    {
        $user = User::factory()->forTenant($this->testTenantId)->create();
        Sanctum::actingAs($user);

        $response = $this->apiPost('/v2/bookmark-collections', [
            'name' => '',
        ]);

        $this->assertContains($response->getStatusCode(), [400, 422]);
    }
}
