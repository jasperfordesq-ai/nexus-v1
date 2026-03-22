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
 * Feature tests for the skills taxonomy endpoints.
 *
 * Routes are handled by SkillTaxonomyController:
 *   GET /v2/skills/categories — public
 *   GET /v2/skills/search     — public
 */
class SkillsControllerTest extends TestCase
{
    use DatabaseTransactions;

    private function authenticatedUser(array $overrides = []): User
    {
        $user = User::factory()->forTenant($this->testTenantId)->create(array_merge([
            'status'      => 'active',
            'is_approved' => true,
        ], $overrides));

        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    // ================================================================
    // CATEGORIES — Public endpoint
    // ================================================================

    public function test_categories_returns_200_without_auth(): void
    {
        $response = $this->apiGet('/v2/skills/categories');

        $response->assertStatus(200);
    }

    public function test_categories_returns_array_in_data(): void
    {
        $response = $this->apiGet('/v2/skills/categories');

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'), 'data should be an array');
    }

    public function test_categories_returns_200_when_authenticated(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/skills/categories');

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));
    }

    // ================================================================
    // CATEGORIES — Includes seeded category
    // ================================================================

    public function test_categories_includes_seeded_category(): void
    {
        $name = 'Technology-' . uniqid();
        DB::table('skill_categories')->insert([
            'tenant_id'  => $this->testTenantId,
            'name'       => $name,
            'slug'       => strtolower($name),
            'icon'       => null,
            'parent_id'  => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiGet('/v2/skills/categories');

        $response->assertStatus(200);

        // The seeded category should appear somewhere in the response body
        $body = $response->getContent();
        $this->assertStringContainsString($name, $body, 'Seeded category name should appear in response');
    }

    // ================================================================
    // SEARCH — Public endpoint
    // ================================================================

    public function test_search_returns_200_without_auth(): void
    {
        $response = $this->apiGet('/v2/skills/search?q=php');

        $response->assertStatus(200);
    }

    public function test_search_returns_array_in_data(): void
    {
        $response = $this->apiGet('/v2/skills/search?q=php');

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'), 'data should be an array');
    }

    public function test_search_returns_empty_array_for_empty_query(): void
    {
        $response = $this->apiGet('/v2/skills/search?q=');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'), 'empty q should return empty results');
    }

    // ================================================================
    // SEARCH — Respects q param
    // ================================================================

    public function test_search_respects_q_param(): void
    {
        $uniqueSuffix = uniqid();
        $skillName = 'UniqueSkillXYZ' . $uniqueSuffix;

        // Seed a skill category first (required FK if FK constraints enforced)
        $categoryId = DB::table('skill_categories')->insertGetId([
            'tenant_id'  => $this->testTenantId,
            'name'       => 'Test Category ' . $uniqueSuffix,
            'slug'       => 'test-cat-' . $uniqueSuffix,
            'icon'       => null,
            'parent_id'  => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('skills')->insert([
            'tenant_id'   => $this->testTenantId,
            'category_id' => $categoryId,
            'name'        => $skillName,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $response = $this->apiGet('/v2/skills/search?q=' . urlencode('UniqueSkillXYZ' . $uniqueSuffix));

        $response->assertStatus(200);

        $body = $response->getContent();
        $this->assertStringContainsString($skillName, $body, 'Search should return matching skill by name');
    }

    public function test_search_does_not_return_skills_not_matching_q(): void
    {
        // Use a highly unlikely search term
        $response = $this->apiGet('/v2/skills/search?q=ZZZNOMATCHZZZ99999');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data'), 'Search for non-existent term should return empty array');
    }
}
