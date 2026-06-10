<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Password history / reuse prevention.
 *
 * Keeps the last N password hashes per user (config: auth.password_history_depth,
 * env PASSWORD_HISTORY_DEPTH, default 5) and rejects a new password that matches
 * the current hash or any retained historical hash. Only Argon2id/bcrypt hashes
 * are stored — never plaintext.
 *
 * Both entry points fail open on infrastructure errors (e.g. table missing
 * mid-rollout): blocking every password change is worse than temporarily
 * skipping the reuse check, and failures are logged for follow-up.
 */
class PasswordHistoryService
{
    public const DEFAULT_DEPTH = 5;

    public static function depth(): int
    {
        $raw = config('auth.password_history_depth', self::DEFAULT_DEPTH);
        $depth = is_numeric($raw) ? (int) $raw : self::DEFAULT_DEPTH;

        return max(0, $depth);
    }

    /**
     * True when $newPassword matches the user's current hash or any of the
     * last N hashes retained for that user. A depth of 0 disables the check
     * against history but still rejects re-setting the current password.
     */
    public static function isReused(int $userId, int $tenantId, string $newPassword, ?string $currentHash = null): bool
    {
        if ($currentHash !== null && $currentHash !== '' && password_verify($newPassword, $currentHash)) {
            return true;
        }

        $depth = self::depth();
        if ($depth === 0) {
            return false;
        }

        try {
            $rows = DB::table('user_password_history')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit($depth)
                ->pluck('password_hash');
        } catch (\Throwable $e) {
            Log::warning('[PasswordHistory] reuse check skipped: ' . $e->getMessage(), [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
            ]);

            return false;
        }

        foreach ($rows as $hash) {
            if (is_string($hash) && $hash !== '' && password_verify($newPassword, $hash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record the hash a user is moving away from, then prune entries beyond
     * the configured depth. Call with the OLD hash at the moment the password
     * changes — the new password is covered by users.password_hash itself.
     */
    public static function record(int $userId, int $tenantId, ?string $oldHash): void
    {
        if ($oldHash === null || $oldHash === '') {
            return;
        }

        try {
            DB::table('user_password_history')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'password_hash' => $oldHash,
                'created_at' => now(),
            ]);

            self::prune($userId, $tenantId);
        } catch (\Throwable $e) {
            Log::warning('[PasswordHistory] record failed: ' . $e->getMessage(), [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
            ]);
        }
    }

    private static function prune(int $userId, int $tenantId): void
    {
        $depth = max(1, self::depth());

        $keepIds = DB::table('user_password_history')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($depth)
            ->pluck('id');

        if ($keepIds->isEmpty()) {
            return;
        }

        DB::table('user_password_history')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereNotIn('id', $keepIds->all())
            ->delete();
    }
}
