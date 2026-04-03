<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\GroupScheduledPostService;
use App\Services\GroupChallengeService;

class PublishScheduledGroupPostsCommand extends Command
{
    protected $signature = 'groups:publish-scheduled';
    protected $description = 'Publish due scheduled group posts and expire overdue challenges';

    public function handle(): int
    {
        $published = GroupScheduledPostService::publishDue();
        $this->info("Published {$published} scheduled posts.");

        $expired = GroupChallengeService::expireOverdue();
        $this->info("Expired {$expired} overdue challenges.");

        return Command::SUCCESS;
    }
}
