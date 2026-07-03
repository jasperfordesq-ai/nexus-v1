<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use App\Services\FeedActivityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Backfill feed_activity rows for active listings that were never published to
 * the feed (created before feed publishing existed, or via a path that bypassed
 * it). Idempotent — FeedActivityService::recordActivity upserts on the
 * (tenant_id, source_type, source_id) unique key.
 */
class BackfillListingFeedActivity extends Command
{
    protected $signature = 'feed:backfill-listings
        {--tenant= : Restrict to a single tenant id}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Publish active listings that are missing a feed_activity row into the feed';

    public function handle(FeedActivityService $feed): int
    {
        $tenant = $this->option('tenant');
        $dryRun = (bool) $this->option('dry-run');

        $query = DB::table('listings')
            ->where('listings.status', 'active')
            ->where(function ($q) {
                $q->whereNull('listings.moderation_status')
                  ->orWhere('listings.moderation_status', 'approved');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('feed_activity')
                  ->whereColumn('feed_activity.tenant_id', 'listings.tenant_id')
                  ->where('feed_activity.source_type', 'listing')
                  ->whereColumn('feed_activity.source_id', 'listings.id');
            });

        if ($tenant !== null) {
            $query->where('listings.tenant_id', (int) $tenant);
        }

        $listings = $query
            ->select('listings.id', 'listings.tenant_id', 'listings.user_id', 'listings.title', 'listings.description', 'listings.image_url')
            ->orderBy('listings.tenant_id')
            ->get();

        if ($listings->isEmpty()) {
            $this->info('No active listings are missing a feed row. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "Publishing {$listings->count()} listing(s) to the feed...");

        $count = 0;
        foreach ($listings as $listing) {
            $this->line("  tenant {$listing->tenant_id} · listing #{$listing->id} · " . Str::limit((string) $listing->title, 50));

            if (! $dryRun) {
                $feed->recordActivity(
                    (int) $listing->tenant_id,
                    (int) $listing->user_id,
                    'listing',
                    (int) $listing->id,
                    [
                        'title'     => $listing->title,
                        'content'   => Str::limit((string) $listing->description, 500),
                        'image_url' => $listing->image_url,
                    ]
                );
            }

            $count++;
        }

        $this->info(($dryRun ? '[dry-run] Would publish ' : 'Published ') . "{$count} listing(s) to the feed.");

        return self::SUCCESS;
    }
}
