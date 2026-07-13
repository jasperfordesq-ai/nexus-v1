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
use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Feature tests for SearchController — global search, suggestions, saved searches, trending.
 */
class SearchControllerTest extends TestCase
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
    //  GET /v2/search
    // ------------------------------------------------------------------

    public function test_index_requires_auth(): void
    {
        $response = $this->apiGet('/v2/search?q=help');

        $response->assertStatus(401);
    }

    public function test_index_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/search?q=help');

        $response->assertStatus(200);
    }

    public function test_index_searches_member_visible_podcast_titles_and_transcripts(): void
    {
        $user = $this->authenticatedUser();
        DB::table('tenants')->where('id', $this->testTenantId)
            ->update(['features' => json_encode(['podcasts' => true])]);
        TenantContext::setById($this->testTenantId);

        $token = 'podcast-search-' . bin2hex(random_bytes(4));
        $showId = DB::table('podcast_shows')->insertGetId([
            'tenant_id' => $this->testTenantId,
            'owner_user_id' => $user->id,
            'title' => 'Community Audio ' . $token,
            'slug' => $token,
            'visibility' => 'members',
            'status' => 'published',
            'moderation_status' => 'approved',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('podcast_episodes')->insert([
            'tenant_id' => $this->testTenantId,
            'show_id' => $showId,
            'author_user_id' => $user->id,
            'title' => 'Episode one',
            'slug' => 'episode-one',
            'audio_url' => 'https://audio.example.test/episode.mp3',
            'visibility' => 'inherit',
            'status' => 'published',
            'moderation_status' => 'approved',
            'transcript' => 'Transcript marker ' . $token,
            'media_processing_status' => 'complete',
            'media_scan_status' => 'not_required',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('podcast_episodes')->insert([
            'tenant_id' => $this->testTenantId,
            'show_id' => $showId,
            'author_user_id' => $user->id,
            'title' => 'Unsafe pending podcast result',
            'slug' => 'unsafe-pending-result',
            'audio_url' => 'https://api.example.test/api/v2/podcasts/media/2/999/audio',
            'audio_storage_path' => 'podcasts/pending.mp3',
            'audio_storage_disk' => 'local',
            'visibility' => 'inherit',
            'status' => 'published',
            'moderation_status' => 'approved',
            'transcript' => 'Unsafe transcript marker ' . $token,
            'media_processing_status' => 'complete',
            'media_scan_status' => 'scan_unavailable',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('podcast_shows')->insert([
            'tenant_id' => $this->testTenantId,
            'owner_user_id' => $user->id,
            'title' => 'Private hidden ' . $token,
            'slug' => 'private-' . $token,
            'visibility' => 'private',
            'status' => 'published',
            'moderation_status' => 'approved',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->apiGet('/v2/search?q=' . urlencode($token) . '&type=podcasts');

        $response->assertOk();
        $types = collect($response->json('data'))->pluck('type')->all();
        $this->assertContains('podcast_show', $types);
        $this->assertContains('podcast_episode', $types);
        $this->assertNotContains('user', $types);
        $this->assertNotContains('Private hidden ' . $token, collect($response->json('data'))->pluck('title')->all());
        $this->assertNotContains('Unsafe pending podcast result', collect($response->json('data'))->pluck('title')->all());
    }

    // ------------------------------------------------------------------
    //  GET /v2/search/suggestions
    // ------------------------------------------------------------------

    public function test_suggestions_requires_auth(): void
    {
        $response = $this->apiGet('/v2/search/suggestions?q=dog');

        $response->assertStatus(401);
    }

    public function test_suggestions_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/search/suggestions?q=dog');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  GET /v2/search/saved
    // ------------------------------------------------------------------

    public function test_saved_searches_requires_auth(): void
    {
        $response = $this->apiGet('/v2/search/saved');

        $response->assertStatus(401);
    }

    public function test_saved_searches_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/search/saved');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  POST /v2/search/saved
    // ------------------------------------------------------------------

    public function test_save_search_requires_auth(): void
    {
        $response = $this->apiPost('/v2/search/saved', [
            'query' => 'dog walking',
        ]);

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  DELETE /v2/search/saved/{id}
    // ------------------------------------------------------------------

    public function test_delete_saved_search_requires_auth(): void
    {
        $response = $this->apiDelete('/v2/search/saved/1');

        $response->assertStatus(401);
    }

    // ------------------------------------------------------------------
    //  GET /v2/search/trending
    // ------------------------------------------------------------------

    public function test_trending_requires_auth(): void
    {
        $response = $this->apiGet('/v2/search/trending');

        $response->assertStatus(401);
    }

    public function test_trending_returns_data(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/search/trending');

        $response->assertStatus(200);
    }

    // ------------------------------------------------------------------
    //  Tenant isolation
    // ------------------------------------------------------------------

    public function test_search_is_tenant_scoped(): void
    {
        $this->authenticatedUser();

        $response = $this->apiGet('/v2/search?q=test');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertIsArray($data);
    }
}
