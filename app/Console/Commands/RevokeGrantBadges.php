<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Console\Commands;

use App\Core\TenantContext;
use App\Services\GamificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Revoke transaction-based badges that were wrongly awarded from system
 * grants (signup welcome credits / admin wallet grants) before
 * GamificationService::realExchangesOnly() excluded grants from badge
 * counting. New members were getting "First Exchange", "First Earn" and
 * "First Spend" from their signup credits without ever exchanging anything.
 *
 * For each user holding an affected badge, the corrected real-exchange
 * counts are recomputed and the badge is revoked if the user no longer
 * qualifies. Revocation also removes the matching 'earn_badge' XP award
 * (recalculating the user's level) and the badge's feed card.
 *
 * Dry-run by default; pass --apply to write changes.
 */
class RevokeGrantBadges extends Command
{
    protected $signature = 'gamification:revoke-grant-badges
        {--tenant= : Restrict to a single tenant id}
        {--apply : Actually revoke (default is a dry-run report)}';

    protected $description = 'Revoke exchange/earn/spend badges that were wrongly awarded from signup credits and admin grants';

    /** Badge types whose thresholds are directly comparable to corrected counts. */
    private const QUANTITY_TYPES = ['earn', 'spend', 'transaction', 'diversity'];

    /**
     * Quality badge types that all require at least one real exchange.
     * Only revoked when the user has ZERO real exchanges (recomputing full
     * qualification for these is expensive and unnecessary: with no real
     * exchanges none of them can be legitimately held).
     */
    private const QUALITY_TYPES = ['reliability', 'bridge_builder', 'mentor', 'reciprocity', 'community_champion'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $tenantOption = $this->option('tenant');

        $tenantIds = $tenantOption !== null
            ? [(int) $tenantOption]
            : DB::table('tenants')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $totalRevoked = 0;
        $totalUsers = 0;

        foreach ($tenantIds as $tenantId) {
            TenantContext::setById($tenantId);

            // Map badge_key => definition for the affected types (definitions may
            // be DB-seeded per tenant, so resolve inside the tenant loop).
            $affected = [];
            foreach (GamificationService::getBadgeDefinitions() as $def) {
                if (in_array($def['type'], self::QUANTITY_TYPES, true) || in_array($def['type'], self::QUALITY_TYPES, true)) {
                    $affected[$def['key']] = $def;
                }
            }

            if ($affected === []) {
                continue;
            }

            $rows = DB::table('user_badges')
                ->where('tenant_id', $tenantId)
                ->whereIn('badge_key', array_keys($affected))
                ->get(['id', 'user_id', 'badge_key', 'name']);

            if ($rows->isEmpty()) {
                continue;
            }

            foreach ($rows->groupBy('user_id') as $userId => $userBadges) {
                $userId = (int) $userId;
                $counts = GamificationService::getRealExchangeCounts($userId);

                $revoke = [];
                foreach ($userBadges as $row) {
                    $def = $affected[$row->badge_key];
                    $stillQualifies = in_array($def['type'], self::QUANTITY_TYPES, true)
                        ? $counts[$def['type']] >= $def['threshold']
                        : $counts['transaction'] > 0;

                    if (! $stillQualifies) {
                        $revoke[] = $row;
                    }
                }

                if ($revoke === []) {
                    continue;
                }

                $totalUsers++;
                $keys = implode(', ', array_map(fn ($r) => $r->badge_key, $revoke));
                $this->line(($apply ? '' : '[dry-run] ') . "tenant {$tenantId} · user #{$userId} · revoking: {$keys}");
                $totalRevoked += count($revoke);

                if ($apply) {
                    $this->revokeForUser($tenantId, $userId, $revoke);
                }
            }
        }

        $mode = $apply ? 'Revoked' : '[dry-run] Would revoke';
        $this->info("{$mode} {$totalRevoked} badge(s) across {$totalUsers} user(s).");
        if (! $apply && $totalRevoked > 0) {
            $this->info('Re-run with --apply to write these changes.');
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int, object{id: int|string, user_id: int|string, badge_key: string, name: ?string}> $revoke
     */
    private function revokeForUser(int $tenantId, int $userId, array $revoke): void
    {
        $revokedKeys = [];
        $xpToRemove = 0;

        DB::transaction(function () use ($tenantId, $userId, $revoke, &$revokedKeys, &$xpToRemove) {
            foreach ($revoke as $row) {
                DB::table('user_badges')->where('id', $row->id)->delete();
                $revokedKeys[] = $row->badge_key;

                // Remove the 'earn_badge' XP that came with this badge (award
                // writes description "Badge: {name}" — see awardBadge()).
                if (! empty($row->name)) {
                    $xpLog = DB::table('user_xp_log')
                        ->where('tenant_id', $tenantId)
                        ->where('user_id', $userId)
                        ->where('action', 'earn_badge')
                        ->where('description', 'Badge: ' . $row->name)
                        ->orderBy('id')
                        ->first(['id', 'xp_amount']);

                    if ($xpLog) {
                        DB::table('user_xp_log')->where('id', $xpLog->id)->delete();
                        $xpToRemove += (int) $xpLog->xp_amount;
                    }
                }
            }

            if ($xpToRemove > 0) {
                DB::update(
                    'UPDATE users SET xp = GREATEST(0, xp - ?) WHERE id = ? AND tenant_id = ?',
                    [$xpToRemove, $userId, $tenantId]
                );
            }

            // Recalculate level from the corrected XP total.
            $xp = (int) (DB::table('users')->where('id', $userId)->where('tenant_id', $tenantId)->value('xp') ?? 0);
            DB::update(
                'UPDATE users SET level = ? WHERE id = ? AND tenant_id = ?',
                [GamificationService::calculateLevel($xp), $userId, $tenantId]
            );

            // Remove the user's "badge earned" feed card if it showcases a
            // revoked badge (one card per user — source_id = user id).
            $card = DB::table('feed_activity')
                ->where('tenant_id', $tenantId)
                ->where('source_type', 'badge_earned')
                ->where('source_id', $userId)
                ->first(['id', 'metadata']);

            if ($card) {
                $meta = json_decode((string) ($card->metadata ?? ''), true);
                if (! is_array($meta) || in_array($meta['badge_key'] ?? null, $revokedKeys, true)) {
                    DB::table('feed_activity')->where('id', $card->id)->delete();
                }
            }
        });
    }
}
