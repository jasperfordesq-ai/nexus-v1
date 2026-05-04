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
 * The repair is intentionally conservative: rows are updated only when a
 * single tenant-scoped feed_activity match can be identified and no canonical
 * reaction already exists. Ambiguous rows are left in place for auditability.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('reactions') || !Schema::hasTable('feed_activity')) {
            return;
        }

        DB::transaction(function (): void {
            $orphans = DB::select(
                "SELECT r.id, r.tenant_id, r.user_id, r.target_id, r.created_at
                 FROM reactions r
                 LEFT JOIN feed_posts fp
                   ON fp.id = r.target_id AND fp.tenant_id = r.tenant_id
                 WHERE r.target_type = 'post'
                   AND fp.id IS NULL"
            );

            $repaired = 0;
            $skipped = 0;

            foreach ($orphans as $row) {
                if ((int) $row->target_id === 0) {
                    $skipped++;
                    continue;
                }

                $candidates = DB::select(
                    "SELECT source_type,
                            ABS(TIMESTAMPDIFF(SECOND, created_at, ?)) AS gap
                     FROM feed_activity
                     WHERE tenant_id = ? AND source_id = ?
                     ORDER BY gap ASC
                     LIMIT 2",
                    [$row->created_at, $row->tenant_id, $row->target_id]
                );

                if (count($candidates) !== 1) {
                    $skipped++;
                    continue;
                }

                $sourceType = $candidates[0]->source_type;
                $reactableTypes = [
                    'post',
                    'listing',
                    'event',
                    'goal',
                    'poll',
                    'review',
                    'volunteer',
                    'challenge',
                    'resource',
                ];

                if (!in_array($sourceType, $reactableTypes, true)) {
                    $skipped++;
                    continue;
                }

                $collision = DB::table('reactions')
                    ->where('tenant_id', $row->tenant_id)
                    ->where('user_id', $row->user_id)
                    ->where('target_id', $row->target_id)
                    ->where('target_type', $sourceType)
                    ->where('id', '<>', $row->id)
                    ->exists();

                if ($collision) {
                    $skipped++;
                    continue;
                }

                $repaired += DB::table('reactions')
                    ->where('tenant_id', $row->tenant_id)
                    ->where('id', $row->id)
                    ->update(['target_type' => $sourceType]);
            }

            if (Schema::hasTable('likes')) {
                $tenantIds = DB::table('likes')
                    ->where('target_type', 'volunteering')
                    ->distinct()
                    ->pluck('tenant_id');

                foreach ($tenantIds as $tenantId) {
                    DB::update(
                        "UPDATE likes old
                         LEFT JOIN likes new
                           ON new.tenant_id = old.tenant_id
                          AND new.user_id = old.user_id
                          AND new.target_id = old.target_id
                          AND new.target_type = 'volunteer'
                         SET old.target_type = 'volunteer'
                         WHERE old.tenant_id = ?
                           AND old.target_type = 'volunteering'
                           AND new.id IS NULL",
                        [$tenantId]
                    );
                }
            }

            if ($repaired > 0 || $skipped > 0) {
                \Illuminate\Support\Facades\Log::info(
                    "[reactions repair] repaired={$repaired} skipped={$skipped} orphan rows"
                );
            }
        });
    }

    public function down(): void
    {
        // Irreversible: the original incorrect target_type information is not
        // recoverable once a row has been safely canonicalised.
    }
};
