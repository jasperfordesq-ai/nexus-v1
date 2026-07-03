<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use App\Services\PodcastService;
use Illuminate\Console\Command;

class PodcastStorageDoctor extends Command
{
    protected $signature = 'podcasts:storage-doctor {--disk=s3 : Filesystem disk to verify (e.g. s3, local)}';

    protected $description = 'Verify a storage disk works end-to-end (write/read/delete probe) before switching podcast media onto it';

    public function handle(): int
    {
        $disk = (string) $this->option('disk');
        $result = PodcastService::verifyMediaDisk($disk);

        $this->line(sprintf('Disk:   %s (driver: %s)', $result['disk'], $result['driver'] ?? 'unknown'));
        foreach ($result['checks'] as $check => $passed) {
            $this->line(sprintf('  %-18s %s', $check, $passed ? 'OK' : 'FAIL'));
        }

        if ($result['ok']) {
            $this->info('Storage disk verified: safe to use for podcast media.');

            return Command::SUCCESS;
        }

        $this->error('Storage verification failed: ' . ($result['error'] ?? 'unknown error'));

        return Command::FAILURE;
    }
}
