<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * PushLog — delivery observability for device push (web + FCM).
 *
 * One row per fanOutPush() that actually delivered or genuinely failed. Pure
 * "user has no device / push disabled" cases are NOT logged (they are not
 * delivery events). All writes are best-effort and guarded so push and the
 * HTTP request are never affected by a logging failure.
 */
class PushLog extends Model
{
    protected $table = 'push_log';

    // Only created_at is tracked.
    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'user_id', 'activity_type', 'title',
        'web_ok', 'fcm_sent', 'fcm_failed', 'status', 'error', 'created_at',
    ];

    protected $casts = [
        'web_ok' => 'boolean',
        'fcm_sent' => 'integer',
        'fcm_failed' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Record a push delivery outcome. Computes a coarse status from the
     * per-channel results and inserts a row — unless nothing was sent and
     * nothing failed (no targets / push disabled), in which case it is a
     * no-op so the log stays meaningful and low-volume.
     *
     * WebPush returns only a bool, and `false` usually means "no browser
     * subscription" rather than a real failure, so a bare `false` is NOT
     * treated as a failure here; only exceptions/errors collected in $errors
     * (and FCM's failed count) count as failures.
     *
     * @param string[] $errors
     */
    public static function record(?int $tenantId, int $userId, string $activityType, ?string $title, ?bool $webOk, int $fcmSent, int $fcmFailed, array $errors = []): void
    {
        try {
            if (!Schema::hasTable('push_log')) {
                return;
            }

            $anySent = ($webOk === true) || $fcmSent > 0;
            $anyFail = $fcmFailed > 0 || count($errors) > 0;

            if (!$anySent && !$anyFail) {
                return; // no targets / push disabled — not a delivery event
            }

            $status = ($anySent && $anyFail) ? 'partial' : ($anySent ? 'delivered' : 'failed');

            $errorText = empty($errors) ? null : mb_substr(implode(' | ', $errors), 0, 2000);

            DB::table('push_log')->insert([
                'tenant_id'     => $tenantId,
                'user_id'       => $userId > 0 ? $userId : null,
                'activity_type' => mb_substr($activityType, 0, 64),
                'title'         => $title !== null ? mb_substr($title, 0, 255) : null,
                'web_ok'        => $webOk === null ? null : ($webOk ? 1 : 0),
                'fcm_sent'      => max(0, $fcmSent),
                'fcm_failed'    => max(0, $fcmFailed),
                'status'        => $status,
                'error'         => $errorText,
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            // Observability must never break delivery.
            Log::debug('[PushLog] record failed: ' . $e->getMessage());
        }
    }

    /**
     * Aggregate push delivery stats for a tenant over the last $days.
     * Safe to call before the table exists (returns zeros).
     *
     * @return array<string,mixed>
     */
    public static function stats(int $tenantId, int $days = 7): array
    {
        $empty = [
            'window_days'   => $days,
            'total'         => 0,
            'delivered'     => 0,
            'partial'       => 0,
            'failed'        => 0,
            'fcm_sent'      => 0,
            'fcm_failed'    => 0,
            'web_delivered' => 0,
        ];

        try {
            if (!Schema::hasTable('push_log')) {
                return $empty;
            }

            $since = now()->subDays(max(1, $days));
            $base = DB::table('push_log')
                ->where('tenant_id', $tenantId)
                ->where('created_at', '>=', $since);

            $byStatus = (clone $base)
                ->select('status', DB::raw('COUNT(*) as c'))
                ->groupBy('status')
                ->pluck('c', 'status');

            $sums = (clone $base)
                ->selectRaw('COALESCE(SUM(fcm_sent),0) as s, COALESCE(SUM(fcm_failed),0) as f, COALESCE(SUM(web_ok),0) as w')
                ->first();

            return [
                'window_days'   => $days,
                'total'         => (int) $byStatus->sum(),
                'delivered'     => (int) ($byStatus['delivered'] ?? 0),
                'partial'       => (int) ($byStatus['partial'] ?? 0),
                'failed'        => (int) ($byStatus['failed'] ?? 0),
                'fcm_sent'      => (int) ($sums->s ?? 0),
                'fcm_failed'    => (int) ($sums->f ?? 0),
                'web_delivered' => (int) ($sums->w ?? 0),
            ];
        } catch (\Throwable $e) {
            Log::debug('[PushLog] stats failed: ' . $e->getMessage());
            return $empty;
        }
    }
}
