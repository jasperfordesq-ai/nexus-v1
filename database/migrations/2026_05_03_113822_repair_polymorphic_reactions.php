<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair reactions polluted by the legacy post-only API.
 *
 * Until 2026-05-03, the React frontend posted ALL feed-item reactions to
 * /v2/posts/{id}/reactions, and the backend stored them with target_type='post'.
 * For listing/event/goal/poll/review/volunteer/challenge cards this was wrong:
 * the row carried the entity's id but the wrong target_type, so the feed
 * reload (which queries by target_type) never surfaced them. Users saw their
 * reactions disappear on refresh — the recurring "like doesn't persist" bug.
 *
 * This migration walks every reactions row with target_type='post' and:
 *   1. Skips rows that already point at a real feed_posts row (unchanged).
 *   2. For orphan rows, looks up feed_activity for the same tenant_id and
 *      source_id=target_id, picking the source_type whose created_at is
 *      closest in time to the reaction's created_at. That gives the correct
 *      polymorphic target_type (listing, event, goal, etc.).
 *   3. Updates the row in place when a unique closest match is found.
 *   4. Deletes rows pointing at target_id=0 (level_up / badge_earned cards
 *      that aren't reactable in the new model).
 *
 * Also normalises legacy likes.target_type='volunteering' → 'volunteer' so
 * the canonical singular form (matching feed_activity.source_type) is used
 * everywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reactions') || !Schema::hasTable('feed_activity')) {
            return;
        }

        // 1. Drop orphan rows pointing at non-reactable id=0 cards
        DB::table('reactions')
            ->where('target_type', 'post')
            ->where('target_id', 0)
            ->delete();

        // 2. Repair post-typed rows whose target_id doesn't match any feed_posts row
        $orphans = DB::select(
            "SELECT r.id, r.tenant_id, r.target_id, r.created_at
             FROM reactions r
             LEFT JOIN feed_posts fp
               ON fp.id = r.target_id AND fp.tenant_id = r.tenant_id
             WHERE r.target_type = 'post'
               AND fp.id IS NULL"
        );

        $repaired = 0;
        $deleted = 0;
        foreach ($orphans as $row) {
            // Find the feed_activity entry whose created_at is closest to the reaction's
            $candidates = DB::select(
                "SELECT source_type, created_at,
                        ABS(TIMESTAMPDIFF(SECOND, created_at, ?)) AS gap
                 FROM feed_activity
                 WHERE tenant_id = ? AND source_id = ?
                 ORDER BY gap ASC
                 LIMIT 1",
                [$row->created_at, $row->tenant_id, $row->target_id]
            );

            if (empty($candidates)) {
                // No feed_activity match — drop the orphan (irrecoverable)
                DB::table('reactions')->where('id', $row->id)->delete();
                $deleted++;
                continue;
            }

            $sourceType = $candidates[0]->source_type;

            // Skip if the resolved type isn't reactable
            $reactableTypes = [
                'post', 'listing', 'event', 'goal', 'poll',
                'review', 'volunteer', 'challenge', 'resource',
            ];
            if (!in_array($sourceType, $reactableTypes, true)) {
                DB::table('reactions')->where('id', $row->id)->delete();
                $deleted++;
                continue;
            }

            // Update — but guard against unique-key collision with an existing
            // (tenant_id, user_id, target_type, target_id) row for the canonical type.
            // If a collision exists, the orphan is redundant; drop it.
            try {
                DB::table('reactions')
                    ->where('id', $row->id)
                    ->update(['target_type' => $sourceType]);
                $repaired++;
            } catch (\Throwable $e) {
                DB::table('reactions')->where('id', $row->id)->delete();
                $deleted++;
            }
        }

        // 3. Normalise likes.target_type='volunteering' → 'volunteer' so the
        //    canonical name (matching feed_activity.source_type) is used.
        if (Schema::hasTable('likes')) {
            // Drop any rows that would collide with a canonical row for the same user/target
            $colliders = DB::select(
                "SELECT old.id
                 FROM likes old
                 INNER JOIN likes new
                   ON new.tenant_id = old.tenant_id
                   AND new.user_id = old.user_id
                   AND new.target_id = old.target_id
                   AND new.target_type = 'volunteer'
                 WHERE old.target_type = 'volunteering'"
            );
            foreach ($colliders as $c) {
                DB::table('likes')->where('id', $c->id)->delete();
            }

            DB::table('likes')
                ->where('target_type', 'volunteering')
                ->update(['target_type' => 'volunteer']);
        }

        if ($repaired > 0 || $deleted > 0) {
            \Illuminate\Support\Facades\Log::info(
                "[reactions repair] repaired={$repaired} deleted={$deleted} orphan rows"
            );
        }
    }

    public function down(): void
    {
        // Irreversible — the original (incorrect) target_type information is lost
        // by design. Rolling back would re-pollute the table.
    }
};
