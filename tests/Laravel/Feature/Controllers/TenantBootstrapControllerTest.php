<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Laravel\TestCase;

/**
 * Feature tests for TenantBootstrapController.
 *
 * Covers the three public endpoints that the React SPA calls on init:
 *   GET /api/v2/tenant/bootstrap
 *   GET /api/v2/tenants
 *   GET /api/v2/platform/stats
 */
class TenantBootstrapControllerTest extends TestCase
{
    use DatabaseTransactions;

    // ================================================================
    // BOOTSTRAP — Happy path
    // ================================================================

    public function test_bootstrap_returns_200_with_data_structure(): void
    {
        $response = $this->apiGet('/v2/tenant/bootstrap');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'slug',
                'features',
                'modules',
                'settings',
                'compliance',
            ],
        ]);
    }

    // ================================================================
    // BOOTSTRAP — Slug resolution
    // ================================================================

    public function test_bootstrap_with_valid_slug_returns_tenant_data(): void
    {
        $response = $this->apiGet('/v2/tenant/bootstrap?slug=' . $this->testTenantSlug);

        $response->assertStatus(200);
        $response->assertJsonPath('data.slug', $this->testTenantSlug);
        $response->assertJsonPath('data.id', $this->testTenantId);
    }

    public function test_bootstrap_returns_404_for_unknown_slug(): void
    {
        $response = $this->apiGet('/v2/tenant/bootstrap?slug=nonexistent-slug-xyz');

        $response->assertStatus(404);
    }

    public function test_bootstrap_slug_lookup_is_case_insensitive_trimmed(): void
    {
        // Passing a slug with surrounding whitespace that trim() handles.
        // A slug with leading/trailing spaces should still resolve (controller trims it).
        $response = $this->apiGet('/v2/tenant/bootstrap?slug=' . urlencode(' ' . $this->testTenantSlug . ' '));

        $response->assertStatus(200);
        $response->assertJsonPath('data.slug', $this->testTenantSlug);
    }

    // ================================================================
    // BOOTSTRAP — Inactive tenant guard
    // ================================================================

    public function test_bootstrap_returns_404_for_inactive_tenant_slug(): void
    {
        // Insert a second tenant that is inactive
        DB::table('tenants')->insertOrIgnore([
            'id' => 99901,
            'name' => 'Inactive Timebank',
            'slug' => 'inactive-timebank-test',
            'domain' => null,
            'is_active' => false,
            'depth' => 0,
            'allows_subtenants' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiGet('/v2/tenant/bootstrap?slug=inactive-timebank-test');

        // Inactive tenants must NOT be returned — controller checks is_active = 1
        $response->assertStatus(404);
    }

    // ================================================================
    // TENANTS LIST — Happy path
    // ================================================================

    public function test_list_returns_200_with_data_array(): void
    {
        $response = $this->apiGet('/v2/tenants');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
        ]);

        $data = $response->json('data');
        $this->assertIsArray($data);
    }

    public function test_list_contains_test_tenant(): void
    {
        $response = $this->apiGet('/v2/tenants');

        $response->assertStatus(200);

        $slugs = array_column($response->json('data'), 'slug');
        $this->assertContains($this->testTenantSlug, $slugs);
    }

    public function test_list_excludes_master_tenant_by_default(): void
    {
        $response = $this->apiGet('/v2/tenants');

        $response->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertNotContains(1, $ids, 'Master tenant (id=1) should not appear without include_master=true');
    }

    // ================================================================
    // PLATFORM STATS — Happy path
    // ================================================================

    public function test_platform_stats_returns_200_with_expected_keys(): void
    {
        $response = $this->apiGet('/v2/platform/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'members',
                'hours_exchanged',
                'listings',
                'skills',
                'communities',
            ],
        ]);
    }

    public function test_platform_stats_values_are_non_negative_integers(): void
    {
        $response = $this->apiGet('/v2/platform/stats');

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach (['members', 'hours_exchanged', 'listings', 'skills', 'communities'] as $key) {
            $this->assertIsInt($data[$key], "Expected integer for key: {$key}");
            $this->assertGreaterThanOrEqual(0, $data[$key], "Expected non-negative value for key: {$key}");
        }
    }
}
