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

class LeaderboardCache extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'leaderboard_cache';

    const CREATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'leaderboard_type',
        'period',
        'score',
        'rank_position',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'rank_position' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
