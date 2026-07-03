<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use App\Core\TenantContext;
use App\Models\PodcastEpisode;
use App\Services\PodcastService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Move hosted podcast audio between storage disks (e.g. local -> s3 when a
 * platform outgrows local storage). Each episode is copied, checksum-verified
 * and repointed atomically; already-migrated episodes are filtered out, so an
 * interrupted run is resumed by re-running the command. Source objects are
 * kept unless --delete-source is passed.
 */
class MigratePodcastMedia extends Command
{
    protected $signature = 'podcasts:migrate-media
        {--to=s3 : Target filesystem disk}
        {--tenant= : Only migrate episodes belonging to this tenant id}
        {--show= : Only migrate episodes belonging to this show id}
        {--limit=0 : Maximum episodes to migrate this run (0 = all)}
        {--delete-source : Delete each source object after a verified copy}
        {--dry-run : Report what would migrate without copying anything}';

    protected $description = 'Copy hosted podcast audio to another storage disk (verified, idempotent, resumable)';

    public function handle(): int
    {
        $target = (string) $this->option('to');

        $verify = PodcastService::verifyMediaDisk($target);
        if (!$verify['ok']) {
            $this->error("Target disk '{$target}' failed verification: " . ($verify['error'] ?? 'unknown error'));
            $this->line('Run `php artisan podcasts:storage-doctor --disk=' . $target . '` for the full report.');

            return Command::FAILURE;
        }

        $query = PodcastEpisode::withoutGlobalScopes()
            ->whereNotNull('audio_storage_path')
            ->where('audio_storage_path', '<>', '')
            ->where(function ($q) use ($target): void {
                $q->whereNull('audio_storage_disk')->orWhere('audio_storage_disk', '<>', $target);
            })
            ->orderBy('id');

        if ((string) $this->option('tenant') !== '') {
            $query->where('tenant_id', (int) $this->option('tenant'));
        }
        if ((string) $this->option('show') !== '') {
            $query->where('show_id', (int) $this->option('show'));
        }
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $episodes = $query->get();
        if ($episodes->isEmpty()) {
            $this->info('No hosted podcast audio needs migrating.');

            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($episodes as $episode) {
                $this->line(sprintf(
                    '[dry-run] episode %d (tenant %d, show %d): %s -> %s  %s',
                    $episode->id,
                    $episode->tenant_id,
                    $episode->show_id,
                    $episode->audio_storage_disk ?: 'local',
                    $target,
                    $episode->audio_storage_path
                ));
            }
            $this->info(sprintf('%d episode(s) would be migrated to %s.', $episodes->count(), $target));

            return Command::SUCCESS;
        }

        $deleteSource = (bool) $this->option('delete-source');
        $counts = [];
        foreach ($episodes as $episode) {
            try {
                $outcome = (string) TenantContext::runForTenant(
                    (int) $episode->tenant_id,
                    fn (): string => PodcastService::migrateEpisodeMedia($episode, $target, $deleteSource)
                );
            } catch (\Throwable $e) {
                $outcome = 'failed_exception';
                Log::warning('[podcasts:migrate-media] episode migration failed', [
                    'episode_id' => (int) $episode->id,
                    'target_disk' => $target,
                    'error' => $e->getMessage(),
                ]);
                $this->warn(sprintf('episode %d failed: %s', $episode->id, $e->getMessage()));
            }

            $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
        }

        $this->info(sprintf('Processed %d episode(s):', $episodes->count()));
        ksort($counts);
        foreach ($counts as $outcome => $count) {
            $this->line(sprintf('  %-28s %d', $outcome, $count));
        }

        $failures = 0;
        foreach ($counts as $outcome => $count) {
            if (str_starts_with($outcome, 'failed')) {
                $failures += $count;
            }
        }

        return $failures > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
