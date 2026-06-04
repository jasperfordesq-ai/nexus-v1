<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use App\Services\PodcastService;
use Illuminate\Console\Command;

class ReleaseScheduledPodcastEpisodes extends Command
{
    protected $signature = 'podcasts:release-due {--limit=200 : Maximum episodes to release per run}';

    protected $description = 'Announce podcast episodes whose scheduled publish time has arrived (notify subscribers + post to the feed)';

    public function handle(): int
    {
        $released = PodcastService::releaseDueEpisodes((int) $this->option('limit'));
        $this->info("Released {$released} scheduled podcast episode(s).");

        return Command::SUCCESS;
    }
}
