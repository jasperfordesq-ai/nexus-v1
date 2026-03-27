<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyReward extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'daily_rewards';

    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'reward_date',
        'xp_earned',
        'streak_day',
        'milestone_bonus',
        'claimed_at',
    ];

    protected $casts = [
        'reward_date' => 'date',
        'xp_earned' => 'integer',
        'streak_day' => 'integer',
        'milestone_bonus' => 'integer',
        'claimed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
