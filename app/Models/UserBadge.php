<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class UserBadge extends Model
{
    protected $table = 'user_badges';

    public $timestamps = true;

    const CREATED_AT = 'awarded_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'badge_key',
        'name',
        'icon',
        'is_showcased',
        'showcase_order',
    ];

    protected $casts = [
        'is_showcased' => 'boolean',
        'showcase_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all badges for a user.
     */
    public static function getForUser(int $userId): array
    {
        return DB::table('user_badges')
            ->where('user_id', $userId)
            ->orderByDesc('awarded_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Update showcased badges for a user (max 3).
     */
    public static function updateShowcase(int $userId, array $badgeKeys): bool
    {
        // Clear all showcased
        DB::table('user_badges')
            ->where('user_id', $userId)
            ->update(['is_showcased' => 0, 'showcase_order' => 0]);

        // Set new showcased (max 3)
        $order = 0;
        foreach (array_slice($badgeKeys, 0, 3) as $key) {
            DB::table('user_badges')
                ->where('user_id', $userId)
                ->where('badge_key', $key)
                ->update(['is_showcased' => 1, 'showcase_order' => $order++]);
        }

        return true;
    }

    /**
     * Get showcased badges for a user.
     */
    public static function getShowcased(int $userId): array
    {
        return DB::table('user_badges')
            ->where('user_id', $userId)
            ->where('is_showcased', 1)
            ->orderBy('showcase_order')
            ->limit(3)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }
}
