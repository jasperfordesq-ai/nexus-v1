<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

/**
 * TD10 — FK CASCADE Policy Audit (READ-ONLY)
 *
 * Connects to the CURRENT database, lists every FK constraint with its
 * ON DELETE behavior, and cross-references the recommended policy encoded
 * below (derived from docs/database/cascade-policy.md). Emits a markdown
 * table of MISMATCHES so ops can spot drift.
 *
 * NOTHING is modified. Safe to run on production.
 *
 * Usage:
 *   docker exec nexus-php-app php scripts/audit-fk-cascade.php
 *   docker exec nexus-php-app php scripts/audit-fk-cascade.php > /tmp/fk-audit.md
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

/** Bootstrap Laravel so we can use DB facade. */
$app = require $root . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

/**
 * Recommended policy map. Keys are "<table>.<column>". Values are one of
 * 'CASCADE', 'SET NULL', 'RESTRICT'. Only entries present here are checked;
 * the rest are treated as Category D (unknown / needs decision).
 *
 * Keep in sync with docs/database/cascade-policy.md.
 */
$policy = [
    // ---- Category B (should be SET NULL) ----
    'transactions.giver_id'                  => 'SET NULL',
    'search_logs.user_id'                    => 'SET NULL',
    'admin_actions.admin_id'                 => 'SET NULL',
    'reviews.reviewer_id'                    => 'SET NULL',
    'reviews.receiver_id'                    => 'SET NULL',
    'exchange_ratings.rater_id'              => 'SET NULL',
    'exchange_ratings.rated_id'              => 'SET NULL',
    'messages.receiver_id'                   => 'SET NULL',
    'broker_message_copies.sender_id'        => 'SET NULL',
    'broker_message_copies.receiver_id'      => 'SET NULL',
    'broker_review_archives.decided_by'      => 'SET NULL',
    'credit_donations.donor_id'              => 'SET NULL',
    'listings.user_id'                       => 'SET NULL',
    'feed_posts.user_id'                     => 'SET NULL',
    'posts.author_id'                        => 'SET NULL',
    'comments.user_id'                       => 'SET NULL',
    'vol_logs.user_id'                       => 'SET NULL',
    'newsletters.created_by'                 => 'SET NULL',
    'tenant_invite_codes.created_by'         => 'SET NULL',
    'event_series.created_by'                => 'SET NULL',
    'recurring_shift_patterns.created_by'    => 'SET NULL',
    'community_fund_transactions.admin_id'   => 'SET NULL',
    'community_fund_transactions.user_id'    => 'SET NULL',

    // ---- Category A (should stay CASCADE) — subset ----
    'push_subscriptions.user_id'             => 'CASCADE',
    'notification_settings.user_id'          => 'CASCADE',
    'notification_queue.user_id'             => 'CASCADE',
    'notifications.user_id'                  => 'CASCADE',
    'user_preferences.user_id'               => 'CASCADE',
    'user_settings.user_id'                  => 'CASCADE',
    'user_interests.user_id'                 => 'CASCADE',
    'user_categories.user_id'                => 'CASCADE',
    'user_category_affinity.user_id'         => 'CASCADE',
    'user_distance_preference.user_id'       => 'CASCADE',
    'user_messaging_restrictions.user_id'    => 'CASCADE',
    'user_safeguarding_preferences.user_id'  => 'CASCADE',
    'match_cache.user_id'                    => 'CASCADE',
    'match_preferences.user_id'              => 'CASCADE',
    'match_history.user_id'                  => 'CASCADE',
    'nexus_score_cache.user_id'              => 'CASCADE',
    'nexus_score_history.user_id'            => 'CASCADE',
    'nexus_score_milestones.user_id'         => 'CASCADE',
    'webauthn_credentials.user_id'           => 'CASCADE',
    'social_identities.user_id'              => 'CASCADE',
    'revoked_tokens.user_id'                 => 'CASCADE',
    'user_badges.user_id'                    => 'CASCADE',
    'connections.requester_id'               => 'CASCADE',
    'connections.receiver_id'                => 'CASCADE',
    'likes.user_id'                          => 'CASCADE',
    'post_likes.user_id'                     => 'CASCADE',
    'reactions.user_id'                      => 'CASCADE',
    'message_reactions.user_id'              => 'CASCADE',
    'group_members.user_id'                  => 'CASCADE',
];

$dbName = DB::getDatabaseName();

$rows = DB::select(
    'SELECT rc.TABLE_NAME AS child_table,
            kcu.COLUMN_NAME AS child_column,
            rc.REFERENCED_TABLE_NAME AS parent_table,
            kcu.REFERENCED_COLUMN_NAME AS parent_column,
            rc.DELETE_RULE AS on_delete,
            rc.UPDATE_RULE AS on_update,
            rc.CONSTRAINT_NAME AS fk_name
       FROM information_schema.REFERENTIAL_CONSTRAINTS rc
       JOIN information_schema.KEY_COLUMN_USAGE kcu
         ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
        AND kcu.CONSTRAINT_NAME   = rc.CONSTRAINT_NAME
        AND kcu.TABLE_NAME        = rc.TABLE_NAME
      WHERE rc.CONSTRAINT_SCHEMA = ?
      ORDER BY rc.TABLE_NAME, kcu.COLUMN_NAME',
    [$dbName]
);

$mismatches   = [];
$uncategorised = [];
$matches       = 0;

foreach ($rows as $r) {
    $key = $r->child_table . '.' . $r->child_column;
    $current = strtoupper($r->on_delete);
    if (isset($policy[$key])) {
        if ($policy[$key] !== $current) {
            $mismatches[] = [
                'child'       => $key,
                'parent'      => $r->parent_table . '.' . $r->parent_column,
                'current'     => $current,
                'recommended' => $policy[$key],
                'fk'          => $r->fk_name,
            ];
        } else {
            $matches++;
        }
    } else {
        $uncategorised[] = $key;
    }
}

// ---- Output ----
echo "# FK CASCADE Policy Audit (read-only)\n\n";
echo "Database: `{$dbName}`\n\n";
echo "Generated: " . date('c') . "\n\n";
echo "Total FKs scanned: " . count($rows) . "\n\n";
echo "- Policy matches:     {$matches}\n";
echo "- Policy mismatches:  " . count($mismatches) . "\n";
echo "- Uncategorised (D):  " . count($uncategorised) . "\n\n";

if (count($mismatches) === 0) {
    echo "## No mismatches\n\nAll FKs listed in `docs/database/cascade-policy.md` match their recommended ON DELETE behaviour.\n";
} else {
    echo "## Mismatches\n\n";
    echo "| Child | Parent | Current | Recommended | FK name |\n";
    echo "|---|---|---|---|---|\n";
    foreach ($mismatches as $m) {
        echo "| `{$m['child']}` | `{$m['parent']}` | {$m['current']} | **{$m['recommended']}** | `{$m['fk']}` |\n";
    }
    echo "\n";
}

echo "## Notes\n\n";
echo "- This tool is READ-ONLY. It performs no schema modifications.\n";
echo "- Mismatches are candidates for review — not all will become migrations (some require product/legal sign-off).\n";
echo "- Uncategorised FKs are Category D in cascade-policy.md.\n";
