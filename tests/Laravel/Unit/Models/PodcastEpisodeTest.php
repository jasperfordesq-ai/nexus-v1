<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Models;

use App\Core\TenantContext;
use App\Models\Concerns\HasTenantScope;
use App\Models\PodcastEpisode;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Laravel\TestCase;

class PodcastEpisodeTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 99766;

    /** @var int Reusable show id seeded once per test */
    private int $showId;

    /** @var int Reusable author user id */
    private int $authorId;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID],
            [
                'name'              => 'Test Tenant 99766',
                'slug'              => 'test-99766',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );
        TenantContext::setById(self::TENANT_ID);

        $this->authorId = DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'name'        => 'Podcast Author',
            'email'       => 'pod-ep-author-' . uniqid() . '@example.test',
            'is_active'   => 1,
            'is_approved' => 1,
            'status'      => 'active',
            'created_at'  => now(),
        ]);

        $this->showId = DB::table('podcast_shows')->insertGetId([
            'tenant_id'        => self::TENANT_ID,
            'owner_user_id'    => $this->authorId,
            'title'            => 'Test Show',
            'slug'             => 'test-show-' . uniqid(),
            'language'         => 'en',
            'visibility'       => 'public',
            'status'           => 'published',
            'moderation_status' => 'approved',
            'episode_count'    => 0,
            'subscriber_count' => 0,
            'explicit'         => 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    // -------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------

    private function seedEpisode(array $overrides = []): PodcastEpisode
    {
        $defaults = [
            'tenant_id'            => self::TENANT_ID,
            'show_id'              => $this->showId,
            'author_user_id'       => $this->authorId,
            'title'                => 'Ep ' . uniqid(),
            'slug'                 => 'ep-' . uniqid(),
            'audio_url'            => 'https://cdn.example.com/ep.mp3',
            'audio_mime'           => 'audio/mpeg',
            'audio_bytes'          => 5000000,
            'duration_seconds'     => 600,
            'episode_number'       => 1,
            'season_number'        => 1,
            'explicit'             => 0,
            'episode_type'         => 'full',
            'visibility'           => 'inherit',
            'status'               => 'draft',
            'moderation_status'    => 'approved',
            'listen_count'         => 0,
            'scheduled_for'        => null,
            'published_at'         => null,
            'created_at'           => now(),
            'updated_at'           => now(),
        ];

        $data = array_merge($defaults, $overrides);
        $id   = DB::table('podcast_episodes')->insertGetId($data);

        return PodcastEpisode::findOrFail($id);
    }

    // -------------------------------------------------------------------
    // scopePublished — happy path
    // -------------------------------------------------------------------

    public function test_scope_published_returns_published_approved_past_scheduled(): void
    {
        $ep = $this->seedEpisode([
            'status'            => 'published',
            'moderation_status' => 'approved',
            'scheduled_for'     => null,
            'published_at'      => now()->subHour(),
        ]);

        $ids = PodcastEpisode::published()->pluck('id')->toArray();
        $this->assertContains($ep->id, $ids);
    }

    // -------------------------------------------------------------------
    // scopePublished — scheduled in future excluded
    // -------------------------------------------------------------------

    public function test_scope_published_excludes_future_scheduled_episodes(): void
    {
        $ep = $this->seedEpisode([
            'status'            => 'published',
            'moderation_status' => 'approved',
            'scheduled_for'     => now()->addDay(),
        ]);

        $ids = PodcastEpisode::published()->pluck('id')->toArray();
        $this->assertNotContains($ep->id, $ids);
    }

    // -------------------------------------------------------------------
    // scopePublished — past scheduled date included
    // -------------------------------------------------------------------

    public function test_scope_published_includes_past_scheduled_episodes(): void
    {
        $ep = $this->seedEpisode([
            'status'            => 'published',
            'moderation_status' => 'approved',
            'scheduled_for'     => now()->subMinute(),
        ]);

        $ids = PodcastEpisode::published()->pluck('id')->toArray();
        $this->assertContains($ep->id, $ids);
    }

    // -------------------------------------------------------------------
    // scopePublished — draft excluded
    // -------------------------------------------------------------------

    public function test_scope_published_excludes_draft_episodes(): void
    {
        $ep = $this->seedEpisode([
            'status'            => 'draft',
            'moderation_status' => 'approved',
        ]);

        $ids = PodcastEpisode::published()->pluck('id')->toArray();
        $this->assertNotContains($ep->id, $ids);
    }

    // -------------------------------------------------------------------
    // scopePublished — pending moderation excluded
    // -------------------------------------------------------------------

    public function test_scope_published_excludes_pending_moderation_episodes(): void
    {
        $ep = $this->seedEpisode([
            'status'            => 'published',
            'moderation_status' => 'pending',
        ]);

        $ids = PodcastEpisode::published()->pluck('id')->toArray();
        $this->assertNotContains($ep->id, $ids);
    }

    // -------------------------------------------------------------------
    // Casts
    // -------------------------------------------------------------------

    public function test_explicit_cast_to_boolean(): void
    {
        $ep = $this->seedEpisode(['explicit' => 1]);
        $this->assertTrue($ep->explicit);

        $ep2 = $this->seedEpisode(['explicit' => 0]);
        $this->assertFalse($ep2->explicit);
    }

    public function test_duration_seconds_cast_to_integer(): void
    {
        $ep = $this->seedEpisode(['duration_seconds' => 3661]);
        $this->assertSame(3661, $ep->duration_seconds);
        $this->assertIsInt($ep->duration_seconds);
    }

    public function test_audio_bytes_cast_to_integer(): void
    {
        $ep = $this->seedEpisode(['audio_bytes' => 12345678]);
        $this->assertSame(12345678, $ep->audio_bytes);
    }

    // -------------------------------------------------------------------
    // Hidden fields not exposed
    // -------------------------------------------------------------------

    public function test_audio_storage_path_is_hidden_in_serialization(): void
    {
        $ep  = $this->seedEpisode();
        $arr = $ep->toArray();
        $this->assertArrayNotHasKey('audio_storage_path', $arr);
        $this->assertArrayNotHasKey('audio_storage_disk', $arr);
        $this->assertArrayNotHasKey('tenant_id', $arr);
    }

    // -------------------------------------------------------------------
    // Tenant scope
    // -------------------------------------------------------------------

    public function test_tenant_scope_excludes_other_tenant_rows(): void
    {
        DB::table('tenants')->updateOrInsert(
            ['id' => self::TENANT_ID + 1],
            [
                'name'              => 'Other Tenant',
                'slug'              => 'other-99766',
                'domain'            => null,
                'is_active'         => true,
                'depth'             => 0,
                'allows_subtenants' => false,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );

        $otherUserId = DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID + 1,
            'name'        => 'Other Pod User',
            'email'       => 'other-pod-' . uniqid() . '@example.test',
            'is_active'   => 1,
            'is_approved' => 1,
            'status'      => 'active',
            'created_at'  => now(),
        ]);

        $otherShowId = DB::table('podcast_shows')->insertGetId([
            'tenant_id'         => self::TENANT_ID + 1,
            'owner_user_id'     => $otherUserId,
            'title'             => 'Other Show',
            'slug'              => 'other-show-' . uniqid(),
            'language'          => 'en',
            'visibility'        => 'public',
            'status'            => 'published',
            'moderation_status' => 'approved',
            'episode_count'     => 0,
            'subscriber_count'  => 0,
            'explicit'          => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $otherId = DB::table('podcast_episodes')->insertGetId([
            'tenant_id'         => self::TENANT_ID + 1,
            'show_id'           => $otherShowId,
            'author_user_id'    => $otherUserId,
            'title'             => 'Other Ep',
            'slug'              => 'other-ep-' . uniqid(),
            'audio_url'         => 'https://cdn.example.com/other.mp3',
            'explicit'          => 0,
            'episode_type'      => 'full',
            'visibility'        => 'inherit',
            'status'            => 'published',
            'moderation_status' => 'approved',
            'listen_count'      => 0,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        TenantContext::setById(self::TENANT_ID);
        $found = PodcastEpisode::find($otherId);
        $this->assertNull($found, 'Tenant scope should exclude episodes from another tenant');
    }

    // -------------------------------------------------------------------
    // Traits
    // -------------------------------------------------------------------

    public function test_uses_has_tenant_scope_trait(): void
    {
        $this->assertContains(HasTenantScope::class, class_uses_recursive(PodcastEpisode::class));
    }
}
