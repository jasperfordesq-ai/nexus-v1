<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace Tests\Laravel\Unit\Services;

use App\Core\TenantContext;
use App\Jobs\CleanupPodcastMedia;
use App\Models\PodcastEpisode;
use App\Models\PodcastMediaCleanupTask;
use App\Models\PodcastShow;
use App\Services\PodcastMediaCleanupService;
use App\Services\PodcastService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\Laravel\TestCase;

/** Regression coverage for podcast media deletion durability and retries. */
class PodcastMediaCleanupServiceTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private int $userId;
    private int $showId;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        Storage::fake('local');
        Queue::fake([CleanupPodcastMedia::class]);

        $this->userId = DB::table('users')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'name' => 'Podcast cleanup owner',
            'first_name' => 'Podcast',
            'last_name' => 'Owner',
            'email' => 'pod-cleanup-' . uniqid('', true) . '@example.test',
            'status' => 'active',
            'balance' => 0,
            'role' => 'member',
            'is_approved' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->showId = DB::table('podcast_shows')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'owner_user_id' => $this->userId,
            'title' => 'Cleanup test show',
            'slug' => 'cleanup-show-' . uniqid('', true),
            'language' => 'en',
            'visibility' => 'public',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_false_storage_deletion_keeps_pointer_and_pending_ledger(): void
    {
        [$episodeId, $path] = $this->insertEpisodeWithAudio('cleanup-false');
        $task = $this->insertCleanupTask($episodeId, 'cleanup-false', $path);
        $disk = Mockery::mock();
        $disk->shouldReceive('exists')->once()->with($path)->andReturnTrue();
        $disk->shouldReceive('delete')->once()->with($path)->andReturnFalse();
        Storage::shouldReceive('disk')->with('cleanup-false')->andReturn($disk);

        try {
            app(PodcastMediaCleanupService::class)->process((int) $task->id);
            $this->fail('False storage deletion must throw for queue retry.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('returned false', $exception->getMessage());
        }

        $this->assertSame($path, PodcastEpisode::findOrFail($episodeId)->audio_storage_path);
        $task->refresh();
        $this->assertSame('pending', $task->status);
        $this->assertSame(1, $task->attempts);
        $this->assertNotNull($task->available_at);
        $this->assertStringContainsString('returned false', (string) $task->last_error);
    }

    public function test_storage_deletion_exception_keeps_pointer_and_pending_ledger(): void
    {
        [$episodeId, $path] = $this->insertEpisodeWithAudio('cleanup-throws');
        $task = $this->insertCleanupTask($episodeId, 'cleanup-throws', $path);
        $disk = Mockery::mock();
        $disk->shouldReceive('exists')->once()->with($path)->andReturnTrue();
        $disk->shouldReceive('delete')->once()->with($path)->andThrow(new RuntimeException('storage offline'));
        Storage::shouldReceive('disk')->with('cleanup-throws')->andReturn($disk);

        try {
            app(PodcastMediaCleanupService::class)->process((int) $task->id);
            $this->fail('Storage exception must propagate for queue retry.');
        } catch (RuntimeException $exception) {
            $this->assertSame('storage offline', $exception->getMessage());
        }

        $this->assertSame($path, PodcastEpisode::findOrFail($episodeId)->audio_storage_path);
        $task->refresh();
        $this->assertSame('pending', $task->status);
        $this->assertSame(1, $task->attempts);
        $this->assertSame('storage offline', $task->last_error);
    }

    public function test_successful_retry_deletes_bytes_then_clears_pointer_and_completes_ledger(): void
    {
        [$episodeId, $path] = $this->insertEpisodeWithAudio('local', true);
        $task = $this->insertCleanupTask($episodeId, 'local', $path, [
            'attempts' => 1,
            'last_error' => 'previous transient failure',
        ]);

        app(PodcastMediaCleanupService::class)->process((int) $task->id);

        Storage::disk('local')->assertMissing($path);
        $episode = PodcastEpisode::findOrFail($episodeId);
        $this->assertNull($episode->audio_storage_path);
        $this->assertNull($episode->audio_storage_disk);
        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame(2, $task->attempts);
        $this->assertNotNull($task->completed_at);
        $this->assertNull($task->last_error);
    }

    public function test_episode_deletion_commits_cleanup_pointer_before_removing_domain_row(): void
    {
        [$episodeId, $path] = $this->insertEpisodeWithAudio('local', true);
        $episode = PodcastEpisode::findOrFail($episodeId);

        PodcastService::deleteEpisode($episode);

        $this->assertDatabaseMissing('podcast_episodes', ['id' => $episodeId]);
        Storage::disk('local')->assertExists($path);
        $task = PodcastMediaCleanupTask::query()
            ->where('path', $path)
            ->where('reason', 'episode_deleted')
            ->firstOrFail();
        $this->assertSame('pending', $task->status);

        app(PodcastMediaCleanupService::class)->process((int) $task->id);

        Storage::disk('local')->assertMissing($path);
        $this->assertSame('completed', $task->fresh()->status);
    }

    public function test_show_deletion_tracks_episode_media_before_removing_all_domain_rows(): void
    {
        [$episodeId, $path] = $this->insertEpisodeWithAudio('local', true);
        $show = PodcastShow::findOrFail($this->showId);

        PodcastService::deleteShow($show);

        $this->assertDatabaseMissing('podcast_shows', ['id' => $this->showId]);
        $this->assertDatabaseMissing('podcast_episodes', ['id' => $episodeId]);
        Storage::disk('local')->assertExists($path);
        $this->assertDatabaseHas('podcast_media_cleanup_tasks', [
            'tenant_id' => self::TENANT_ID,
            'kind' => PodcastMediaCleanupService::KIND_STORAGE,
            'disk' => 'local',
            'path' => $path,
            'reason' => 'show_deleted',
            'status' => 'pending',
        ]);
    }

    /** @return array{int,string} */
    private function insertEpisodeWithAudio(string $disk, bool $write = false): array
    {
        $path = 'podcasts/' . self::TENANT_ID . '/cleanup-' . uniqid('', true) . '.mp3';
        if ($write) {
            Storage::disk('local')->put($path, 'audio bytes');
        }

        $episodeId = DB::table('podcast_episodes')->insertGetId([
            'tenant_id' => self::TENANT_ID,
            'show_id' => $this->showId,
            'author_user_id' => $this->userId,
            'title' => 'Cleanup episode',
            'slug' => 'cleanup-episode-' . uniqid('', true),
            'audio_url' => 'podcast-hosted://quarantined',
            'audio_storage_disk' => $disk,
            'audio_storage_path' => $path,
            'media_processing_status' => 'failed',
            'media_scan_status' => 'infected',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$episodeId, $path];
    }

    /** @param array<string,mixed> $overrides */
    private function insertCleanupTask(
        int $episodeId,
        string $disk,
        string $path,
        array $overrides = [],
    ): PodcastMediaCleanupTask {
        return PodcastMediaCleanupTask::create(array_merge([
            'asset_key' => hash('sha256', 'storage' . "\0" . $disk . "\0" . $path),
            'kind' => PodcastMediaCleanupService::KIND_STORAGE,
            'disk' => $disk,
            'path' => $path,
            'source_episode_id' => $episodeId,
            'reason' => 'malware_rejected',
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => now(),
        ], $overrides));
    }
}
