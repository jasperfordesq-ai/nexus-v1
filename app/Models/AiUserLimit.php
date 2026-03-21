<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class AiUserLimit extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'ai_user_limits';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'daily_limit',
        'monthly_limit',
        'daily_used',
        'monthly_used',
        'last_reset_daily',
        'last_reset_monthly',
    ];

    protected $casts = [
        'daily_limit' => 'integer',
        'monthly_limit' => 'integer',
        'daily_used' => 'integer',
        'monthly_used' => 'integer',
        'last_reset_daily' => 'date',
        'last_reset_monthly' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get or create a limits record for a user, resetting counters if needed.
     */
    private static function getOrCreateRecord(int $userId, int $tenantId): array
    {
        $record = DB::table('ai_user_limits')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if ($record) {
            $record = (array) $record;
            // Check daily/monthly resets
            $today = date('Y-m-d');
            $currentMonth = date('Y-m');
            $updates = [];

            if (($record['last_reset_daily'] ?? '') !== $today) {
                $record['daily_used'] = 0;
                $record['last_reset_daily'] = $today;
                $updates['daily_used'] = 0;
                $updates['last_reset_daily'] = $today;
            }

            $lastResetMonth = substr($record['last_reset_monthly'] ?? '', 0, 7);
            if ($lastResetMonth !== $currentMonth) {
                $record['monthly_used'] = 0;
                $record['last_reset_monthly'] = $today;
                $updates['monthly_used'] = 0;
                $updates['last_reset_monthly'] = $today;
            }

            if (!empty($updates)) {
                $updates['updated_at'] = now();
                DB::table('ai_user_limits')
                    ->where('tenant_id', $tenantId)
                    ->where('user_id', $userId)
                    ->update($updates);
            }

            return $record;
        }

        // Create new record with defaults
        $dailyLimit = 50;
        $monthlyLimit = 1000;

        DB::table('ai_user_limits')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'daily_limit' => $dailyLimit,
            'monthly_limit' => $monthlyLimit,
            'daily_used' => 0,
            'monthly_used' => 0,
            'last_reset_daily' => date('Y-m-d'),
            'last_reset_monthly' => date('Y-m-d'),
            'created_at' => now(),
        ]);

        return self::getOrCreateRecord($userId, $tenantId);
    }

    /**
     * Check if user can make a request (within limits).
     *
     * @return array{allowed: bool, reason: string|null, daily_used: int, daily_limit: int, daily_remaining: int, monthly_used: int, monthly_limit: int, monthly_remaining: int}
     */
    public static function canMakeRequest(int $userId, int $tenantId): array
    {
        $limits = self::getOrCreateRecord($userId, $tenantId);

        $canMake = true;
        $reason = null;

        if ($limits['daily_used'] >= $limits['daily_limit']) {
            $canMake = false;
            $reason = 'daily_limit_reached';
        } elseif ($limits['monthly_used'] >= $limits['monthly_limit']) {
            $canMake = false;
            $reason = 'monthly_limit_reached';
        }

        return [
            'allowed' => $canMake,
            'reason' => $reason,
            'daily_used' => (int) $limits['daily_used'],
            'daily_limit' => (int) $limits['daily_limit'],
            'daily_remaining' => max(0, $limits['daily_limit'] - $limits['daily_used']),
            'monthly_used' => (int) $limits['monthly_used'],
            'monthly_limit' => (int) $limits['monthly_limit'],
            'monthly_remaining' => max(0, $limits['monthly_limit'] - $limits['monthly_used']),
        ];
    }

    /**
     * Increment usage counters for a user.
     */
    public static function incrementUsage(int $userId, int $tenantId): void
    {
        // Ensure record exists
        self::getOrCreateRecord($userId, $tenantId);

        DB::table('ai_user_limits')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->update([
                'daily_used' => DB::raw('daily_used + 1'),
                'monthly_used' => DB::raw('monthly_used + 1'),
                'updated_at' => now(),
            ]);
    }
}
