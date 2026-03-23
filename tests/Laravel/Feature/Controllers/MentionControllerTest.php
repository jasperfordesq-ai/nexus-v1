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
 * Feature tests for MentionController — search and myMentions endpoints.
 */
class MentionControllerTest extends TestCase
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
    //  SEARCH — GET /api/v2/mentions/search?q=...
    // ------------------------------------------------------------------

    public function test_search_requires_authentication(): void
    {
        $response = $this->apiGet('/v2/mentions/search?q=john');

        $response->assertStatus(401);
    }

    public function test_search_returns_empty_for_short_query(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/mentions/search?q=');

        $response->assertStatus(200);
        $response->assertJsonPath('data', []);
    }

    public function test_search_returns_matching_users(): void
    {
        $user = $this->authenticatedUser(['first_name' => 'Alice', 'last_name' => 'Smith']);

        User::factory()->forTenant($this->testTenantId)->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'username' => 'johndoe',
            'status' => 'active',
        ]);

        $response = $this->apiGet('/v2/mentions/search?q=john');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    public function test_search_excludes_banned_users(): void
    {
        $this->authenticatedUser();

        User::factory()->forTenant($this->testTenantId)->create([
            'first_name' => 'Banned',
            'last_name' => 'User',
            'username' => 'banneduser',
            'status' => 'banned',
        ]);

        $response = $this->apiGet('/v2/mentions/search?q=banneduser');

        $response->assertStatus(200);
        $data = $response->json('data');
        // Banned users should not appear in results
        $usernames = array_column($data, 'username');
        $this->assertNotContains('banneduser', $usernames);
    }

    public function test_search_excludes_self(): void
    {
        $user = $this->authenticatedUser([
            'first_name' => 'SelfTest',
            'username' => 'selfuser',
        ]);

        $response = $this->apiGet('/v2/mentions/search?q=selfuser');

        $response->assertStatus(200);
        $data = $response->json('data');
        $ids = array_column($data, 'id');
        $this->assertNotContains($user->id, $ids);
    }

    public function test_search_respects_limit_parameter(): void
    {
        $this->authenticatedUser();

        // Create several users matching "test"
        for ($i = 0; $i < 5; $i++) {
            User::factory()->forTenant($this->testTenantId)->create([
                'first_name' => 'TestLimit' . $i,
                'status' => 'active',
            ]);
        }

        $response = $this->apiGet('/v2/mentions/search?q=TestLimit&limit=2');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertLessThanOrEqual(2, count($data));
    }

    public function test_search_does_not_return_other_tenant_users(): void
    {
        $this->authenticatedUser();

        User::factory()->forTenant(999)->create([
            'first_name' => 'OtherTenantMention',
            'username' => 'othertenantmention',
            'status' => 'active',
        ]);

        $response = $this->apiGet('/v2/mentions/search?q=OtherTenantMention');

        $response->assertStatus(200);
        $data = $response->json('data');
        $names = array_column($data, 'name');
        foreach ($names as $name) {
            $this->assertStringNotContainsString('OtherTenantMention', $name);
        }
    }

    // ------------------------------------------------------------------
    //  MY MENTIONS — GET /api/v2/mentions/me
    // ------------------------------------------------------------------

    public function test_my_mentions_requires_authentication(): void
    {
        $response = $this->apiGet('/v2/mentions/me');

        $response->assertStatus(401);
    }

    public function test_my_mentions_returns_data_structure(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/mentions/me');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'meta' => ['per_page', 'has_more'],
        ]);
    }

    public function test_my_mentions_returns_empty_when_none(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/mentions/me');

        $response->assertStatus(200);
        $response->assertJsonPath('data', []);
        $response->assertJsonPath('meta.has_more', false);
    }

    public function test_my_mentions_returns_mentions_for_user(): void
    {
        $user = $this->authenticatedUser();
        $mentioner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
            'first_name' => 'Mentioner',
        ]);

        // Insert a mention record directly
        DB::table('mentions')->insert([
            'mentioned_user_id' => $user->id,
            'mentioning_user_id' => $mentioner->id,
            'tenant_id' => $this->testTenantId,
            'entity_type' => 'post',
            'entity_id' => 1,
            'created_at' => now(),
        ]);

        $response = $this->apiGet('/v2/mentions/me');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('post', $data[0]['entity_type']);
    }

    public function test_my_mentions_supports_pagination(): void
    {
        $user = $this->authenticatedUser();
        $mentioner = User::factory()->forTenant($this->testTenantId)->create([
            'status' => 'active',
        ]);

        // Insert multiple mention records
        for ($i = 0; $i < 5; $i++) {
            DB::table('mentions')->insert([
                'mentioned_user_id' => $user->id,
                'mentioning_user_id' => $mentioner->id,
                'tenant_id' => $this->testTenantId,
                'entity_type' => 'comment',
                'entity_id' => $i + 1,
                'created_at' => now()->subMinutes($i),
            ]);
        }

        $response = $this->apiGet('/v2/mentions/me?limit=2');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertTrue($response->json('meta.has_more'));
    }

    public function test_my_mentions_does_not_return_other_tenant_mentions(): void
    {
        $user = $this->authenticatedUser();
        $mentioner = User::factory()->forTenant(999)->create([
            'status' => 'active',
        ]);

        // Insert mention in different tenant
        DB::table('mentions')->insert([
            'mentioned_user_id' => $user->id,
            'mentioning_user_id' => $mentioner->id,
            'tenant_id' => 999,
            'entity_type' => 'post',
            'entity_id' => 100,
            'created_at' => now(),
        ]);

        $response = $this->apiGet('/v2/mentions/me');

        $response->assertStatus(200);
        $data = $response->json('data');
        // Should be empty because the mention is in another tenant
        $this->assertEmpty($data);
    }
}
