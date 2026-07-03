<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace Tests\Laravel\Unit\Console;

use App\Core\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Laravel\TestCase;

/**
 * MigratePodcastMediaTest
 *
 * Covers `podcasts:migrate-media`: verified copy to the target disk, row
 * repointing (audio_storage_disk + regenerated audio_url), idempotent
 * re-runs, opt-in source deletion, missing-source tolerance and dry-run.
 * Both disks are faked; DatabaseTransactions rolls back seeded rows.
 */
class MigratePodcastMediaTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 2;

    private int $showId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::setById(self::TENANT_ID);
        Storage::fake('local');
        Storage::fake('s3');

        $this->userId = DB::table('users')->insertGetId([
            'tenant_id'   => self::TENANT_ID,
            'name'        => 'PodMigrateUser',
            'first_name'  => 'Pod',
            'last_name'   => 'Migrate',
            'email'       => 'podmigrate.' . uniqid('', true) . '@example.test',
            'status'      => 'active',
            'balance'     => 0,
            'role'        => 'member',
            'is_approved' => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->showId = DB::table('podcast_shows')->insertGetId([
            'tenant_id'     => self::TENANT_ID,
            'owner_user_id' => $this->userId,
            'title'         => 'Migration Show',
            'slug'          => 'migration-show-' . uniqid('', true),
            'language'      => 'en',
            'visibility'    => 'public',
            'status'        => 'published',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    private function insertHostedEpisode(string $path, string $disk = 'local', string $contents = 'fake-mp3-bytes'): int
    {
        Storage::disk($disk)->put($path, $contents);

        return DB::table('podcast_episodes')->insertGetId([
            'tenant_id'          => self::TENANT_ID,
            'show_id'            => $this->showId,
            'author_user_id'     => $this->userId,
            'title'              => 'Hosted Episode',
            'slug'               => 'hosted-ep-' . uniqid('', true),
            'audio_url'          => 'https://old.example.test/audio.mp3',
            'audio_storage_path' => $path,
            'audio_storage_disk' => $disk,
            'audio_mime'         => 'audio/mpeg',
            'status'             => 'published',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    private function episodeRow(int $id): object
    {
        return DB::table('podcast_episodes')->where('id', $id)->first();
    }

    public function test_migrates_local_audio_to_target_disk_and_repoints_row(): void
    {
        $path = 'podcasts/' . self::TENANT_ID . '/shows/' . $this->showId . '/episodes/1/audio_test.mp3';
        $episodeId = $this->insertHostedEpisode($path, 'local', 'original-audio-bytes');

        $this->artisan('podcasts:migrate-media', ['--to' => 's3'])->assertExitCode(0);

        $row = $this->episodeRow($episodeId);
        $this->assertSame('s3', $row->audio_storage_disk);
        $this->assertSame($path, $row->audio_storage_path);
        $this->assertStringNotContainsString('old.example.test', $row->audio_url);

        Storage::disk('s3')->assertExists($path);
        $this->assertSame('original-audio-bytes', Storage::disk('s3')->get($path));
        // Without --delete-source, the original object stays put.
        Storage::disk('local')->assertExists($path);
    }

    public function test_second_run_is_idempotent(): void
    {
        $path = 'podcasts/' . self::TENANT_ID . '/idempotent.mp3';
        $episodeId = $this->insertHostedEpisode($path);

        $this->artisan('podcasts:migrate-media', ['--to' => 's3'])->assertExitCode(0);
        $firstUrl = $this->episodeRow($episodeId)->audio_url;

        // Re-running finds nothing left to migrate and succeeds.
        $this->artisan('podcasts:migrate-media', ['--to' => 's3'])
            ->expectsOutputToContain('No hosted podcast audio needs migrating.')
            ->assertExitCode(0);
        $this->assertSame($firstUrl, $this->episodeRow($episodeId)->audio_url);
    }

    public function test_delete_source_removes_original_after_verified_copy(): void
    {
        $path = 'podcasts/' . self::TENANT_ID . '/delete-source.mp3';
        $this->insertHostedEpisode($path);

        $this->artisan('podcasts:migrate-media', ['--to' => 's3', '--delete-source' => true])->assertExitCode(0);

        Storage::disk('s3')->assertExists($path);
        Storage::disk('local')->assertMissing($path);
    }

    public function test_missing_source_object_is_skipped_not_failed(): void
    {
        $episodeId = DB::table('podcast_episodes')->insertGetId([
            'tenant_id'          => self::TENANT_ID,
            'show_id'            => $this->showId,
            'author_user_id'     => $this->userId,
            'title'              => 'Ghost Episode',
            'slug'               => 'ghost-ep-' . uniqid('', true),
            'audio_url'          => 'https://old.example.test/ghost.mp3',
            'audio_storage_path' => 'podcasts/' . self::TENANT_ID . '/missing.mp3',
            'audio_storage_disk' => 'local',
            'status'             => 'published',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        $this->artisan('podcasts:migrate-media', ['--to' => 's3'])
            ->expectsOutputToContain('skipped_missing_source')
            ->assertExitCode(0);

        // Row untouched — nothing was copied, nothing repointed.
        $this->assertSame('local', $this->episodeRow($episodeId)->audio_storage_disk);
    }

    public function test_dry_run_copies_nothing(): void
    {
        $path = 'podcasts/' . self::TENANT_ID . '/dry-run.mp3';
        $episodeId = $this->insertHostedEpisode($path);

        $this->artisan('podcasts:migrate-media', ['--to' => 's3', '--dry-run' => true])->assertExitCode(0);

        Storage::disk('s3')->assertMissing($path);
        $this->assertSame('local', $this->episodeRow($episodeId)->audio_storage_disk);
    }

    public function test_unverifiable_target_disk_aborts_before_touching_anything(): void
    {
        $path = 'podcasts/' . self::TENANT_ID . '/abort.mp3';
        $episodeId = $this->insertHostedEpisode($path);

        $this->artisan('podcasts:migrate-media', ['--to' => 'not-a-disk'])->assertExitCode(1);

        $this->assertSame('local', $this->episodeRow($episodeId)->audio_storage_disk);
    }
}
