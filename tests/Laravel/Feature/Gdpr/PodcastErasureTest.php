<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Feature\Gdpr;

use App\Core\TenantContext;
use App\Models\User;
use App\Services\Enterprise\GdprService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Laravel\TestCase;

class PodcastErasureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_account_erasure_removes_tenant_scoped_podcast_content_activity_and_media(): void
    {
        $tenantId = $this->testTenantId;
        TenantContext::setById($tenantId);
        $user = User::factory()->forTenant($tenantId)->create();
        $other = User::factory()->forTenant($tenantId)->create();

        $assetDirectory = public_path("uploads/{$tenantId}/podcasts/gdpr");
        if (!is_dir($assetDirectory)) {
            mkdir($assetDirectory, 0775, true);
        }
        $artworkUrl = "/uploads/{$tenantId}/podcasts/gdpr/artwork-" . uniqid() . '.jpg';
        $coverUrl = "/uploads/{$tenantId}/podcasts/gdpr/cover-" . uniqid() . '.jpg';
        file_put_contents(public_path(ltrim($artworkUrl, '/')), 'personal show artwork');
        file_put_contents(public_path(ltrim($coverUrl, '/')), 'personal episode cover');

        $ownedShowId = $this->show($tenantId, $user->id, 'Owned erasure show', [
            'artwork_url' => $artworkUrl,
        ]);
        $otherShowId = $this->show($tenantId, $other->id, 'Other member show', [
            'moderated_by' => $user->id,
            'moderated_at' => now(),
        ]);
        $mediaPath = "podcasts/{$tenantId}/gdpr/" . uniqid() . '.mp3';
        Storage::disk('local')->put($mediaPath, 'podcast audio containing personal data');
        $ownedEpisodeId = $this->episode($tenantId, $ownedShowId, $user->id, 'Owned episode', [
            'audio_storage_path' => $mediaPath,
            'audio_storage_disk' => 'local',
            'cover_image_url' => $coverUrl,
        ]);
        $authoredElsewhereId = $this->episode($tenantId, $otherShowId, $user->id, 'Authored elsewhere', [
            // External legacy pointers are scrubbed by deleting the row. The
            // erasure service must never make an outbound request for them.
            'cover_image_url' => 'https://remote.invalid/podcast-cover.jpg',
        ]);
        $otherEpisodeId = $this->episode($tenantId, $otherShowId, $other->id, 'Other member episode', [
            'moderated_by' => $user->id,
            'moderated_at' => now(),
        ]);

        DB::table('podcast_episode_chapters')->insert([
            'tenant_id' => $tenantId,
            'episode_id' => $ownedEpisodeId,
            'title' => 'Personal chapter',
            'starts_at_seconds' => 0,
            'position' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('podcast_episode_listens')->insert([
            'tenant_id' => $tenantId,
            'episode_id' => $otherEpisodeId,
            'user_id' => $user->id,
            'listened_seconds' => 12,
            'completed' => false,
            'created_at' => now(),
        ]);
        DB::table('podcast_episode_reactions')->insert([
            'tenant_id' => $tenantId,
            'episode_id' => $otherEpisodeId,
            'user_id' => $user->id,
            'reaction' => 'like',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('podcast_show_subscriptions')->insert([
            'tenant_id' => $tenantId,
            'show_id' => $otherShowId,
            'user_id' => $user->id,
            'notify_new_episodes' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('podcast_episode_reports')->insert([
            'tenant_id' => $tenantId,
            'episode_id' => $otherEpisodeId,
            'reporter_user_id' => $user->id,
            'reason' => 'other',
            'details' => 'Personal report details',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $reviewedReportId = (int) DB::table('podcast_episode_reports')->insertGetId([
            'tenant_id' => $tenantId,
            'episode_id' => $otherEpisodeId,
            'reporter_user_id' => $other->id,
            'reason' => 'other',
            'details' => 'Another member report',
            'status' => 'resolved',
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherTenantId = (int) DB::table('tenants')->insertGetId([
            'name' => 'Podcast erasure isolation tenant',
            'slug' => 'podcast-erasure-' . uniqid(),
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('podcast_episode_listens')->insert([
            'tenant_id' => $otherTenantId,
            'episode_id' => 999999,
            'user_id' => $user->id,
            'listened_seconds' => 30,
            'completed' => true,
            'created_at' => now(),
        ]);

        $service = new GdprService($tenantId);
        $collect = new \ReflectionMethod(GdprService::class, 'collectUserData');
        $collect->setAccessible(true);
        $export = $collect->invoke($service, $user->id);
        $this->assertSame('Owned erasure show', $export['podcasts']['shows_owned'][0]['title']);
        $this->assertCount(2, $export['podcasts']['episodes_authored']);
        $this->assertCount(1, $export['podcasts']['listens']);
        $this->assertSame('Personal report details', $export['podcasts']['reports_filed'][0]['details']);

        $service->executeAccountDeletion($user->id);

        $this->assertFalse(Storage::disk('local')->exists($mediaPath));
        $this->assertFileDoesNotExist(public_path(ltrim($artworkUrl, '/')));
        $this->assertFileDoesNotExist(public_path(ltrim($coverUrl, '/')));
        $this->assertDatabaseMissing('podcast_shows', ['id' => $ownedShowId, 'tenant_id' => $tenantId]);
        $this->assertDatabaseMissing('podcast_episodes', ['id' => $ownedEpisodeId, 'tenant_id' => $tenantId]);
        $this->assertDatabaseMissing('podcast_episodes', ['id' => $authoredElsewhereId, 'tenant_id' => $tenantId]);
        $this->assertDatabaseHas('podcast_episodes', ['id' => $otherEpisodeId, 'tenant_id' => $tenantId]);
        $this->assertSame(0, DB::table('podcast_episode_listens')->where('tenant_id', $tenantId)->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('podcast_episode_reactions')->where('tenant_id', $tenantId)->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('podcast_show_subscriptions')->where('tenant_id', $tenantId)->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('podcast_episode_reports')->where('tenant_id', $tenantId)->where('reporter_user_id', $user->id)->count());
        $this->assertNull(DB::table('podcast_episode_reports')->where('id', $reviewedReportId)->value('reviewed_by'));
        $this->assertNull(DB::table('podcast_shows')->where('id', $otherShowId)->value('moderated_by'));
        $this->assertNull(DB::table('podcast_episodes')->where('id', $otherEpisodeId)->value('moderated_by'));
        $this->assertSame(1, DB::table('podcast_episode_listens')->where('tenant_id', $otherTenantId)->where('user_id', $user->id)->count());
        @rmdir($assetDirectory);
    }

    /** @param array<string,mixed> $overrides */
    private function show(int $tenantId, int $ownerId, string $title, array $overrides = []): int
    {
        return (int) DB::table('podcast_shows')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'owner_user_id' => $ownerId,
            'title' => $title,
            'slug' => 'gdpr-show-' . uniqid(),
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'approved',
            'episode_count' => 0,
            'subscriber_count' => 0,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    /** @param array<string,mixed> $overrides */
    private function episode(int $tenantId, int $showId, int $authorId, string $title, array $overrides = []): int
    {
        return (int) DB::table('podcast_episodes')->insertGetId(array_merge([
            'tenant_id' => $tenantId,
            'show_id' => $showId,
            'author_user_id' => $authorId,
            'title' => $title,
            'slug' => 'gdpr-episode-' . uniqid(),
            'audio_url' => 'https://media.example.test/' . uniqid() . '.mp3',
            'media_processing_status' => 'complete',
            'media_scan_status' => 'clean',
            'visibility' => 'public',
            'status' => 'published',
            'moderation_status' => 'approved',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
