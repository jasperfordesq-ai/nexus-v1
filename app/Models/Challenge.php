<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Challenge extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'challenges';

    protected $fillable = [
        'tenant_id', 'title', 'description', 'challenge_type', 'action_type',
        'target_count', 'xp_reward', 'badge_reward', 'category',
        'start_date', 'end_date', 'starts_at', 'ends_at',
        'status', 'is_active',
    ];

    protected $casts = [
        'target_count' => 'integer',
        'xp_reward' => 'integer',
        'is_active' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function progress(): HasMany
    {
        return $this->hasMany(UserChallengeProgress::class);
    }
}
