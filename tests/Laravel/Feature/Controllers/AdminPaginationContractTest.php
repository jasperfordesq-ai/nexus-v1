<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Laravel\TestCase;

/**
 * Verifies that admin paginated endpoints return the correct envelope structure.
 *
 * The React admin frontend depends on:
 *   { data: [...items], meta: { current_page, per_page, total, total_pages, has_more } }
 *
 * This test catches any regression where the pagination meta is missing or
 * uses wrong field names (e.g. "page" instead of "current_page").
 */
class AdminPaginationContractTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->forTenant($this->testTenantId)->admin()->create();
        Sanctum::actingAs($this->admin);
    }

    /**
     * Assert the standard paginated response structure on a response.
     */
    private function assertPaginatedStructure($response): void
    {
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'total_pages',
                'has_more',
            ],
        ]);

        $meta = $response->json('meta');
        $this->assertIsInt($meta['current_page']);
        $this->assertIsInt($meta['per_page']);
        $this->assertIsInt($meta['total']);
        $this->assertIsInt($meta['total_pages']);
        $this->assertIsBool($meta['has_more']);
    }

    // ================================================================
    // Users
    // ================================================================

    public function test_admin_users_index_returns_paginated_envelope(): void
    {
        // Seed some users so we have data
        User::factory()->forTenant($this->testTenantId)->count(3)->create();

        $response = $this->apiGet('/v2/admin/users?page=1&limit=2');

        $this->assertPaginatedStructure($response);

        $meta = $response->json('meta');
        // We created 3 users + 1 admin = at least 4
        $this->assertGreaterThanOrEqual(4, $meta['total']);
        $this->assertEquals(1, $meta['current_page']);
        $this->assertEquals(2, $meta['per_page']);
        $this->assertTrue($meta['has_more']);
    }

    public function test_admin_users_total_exceeds_page_size(): void
    {
        User::factory()->forTenant($this->testTenantId)->count(5)->create();

        $response = $this->apiGet('/v2/admin/users?page=1&limit=2');
        $response->assertStatus(200);

        $data = $response->json('data');
        $meta = $response->json('meta');

        // data should have at most 2 items (page size)
        $this->assertLessThanOrEqual(2, count($data));
        // total should be more than the page size
        $this->assertGreaterThan(2, $meta['total']);
        // This is the critical assertion: total != count(data)
        $this->assertNotEquals(count($data), $meta['total']);
    }

    // ================================================================
    // Dashboard Activity
    // ================================================================

    public function test_admin_activity_returns_paginated_envelope(): void
    {
        $response = $this->apiGet('/v2/admin/dashboard/activity?page=1&limit=5');

        $this->assertPaginatedStructure($response);
    }

    // ================================================================
    // Listings
    // ================================================================

    public function test_admin_listings_returns_paginated_envelope(): void
    {
        $response = $this->apiGet('/v2/admin/listings?page=1&limit=5');

        $this->assertPaginatedStructure($response);
    }

    // ================================================================
    // Blog
    // ================================================================

    public function test_admin_blog_returns_paginated_envelope(): void
    {
        $response = $this->apiGet('/v2/admin/blog?page=1&limit=5');

        $this->assertPaginatedStructure($response);
    }

    // ================================================================
    // System Activity Log
    // ================================================================

    public function test_system_activity_log_returns_paginated_envelope(): void
    {
        $response = $this->apiGet('/v2/admin/system/activity-log?page=1&limit=5');

        $this->assertPaginatedStructure($response);
    }
}
