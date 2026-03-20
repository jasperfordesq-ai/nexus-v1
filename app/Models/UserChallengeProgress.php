<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserChallengeProgress extends Model
{
    use HasTenantScope;

    protected $table = 'user_challenge_progress';

    protected $fillable = [
        'tenant_id', 'user_id', 'challenge_id', 'current_count',
        'completed_at', 'reward_claimed',
    ];

    protected $casts = [
        'current_count' => 'integer',
        'reward_claimed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
