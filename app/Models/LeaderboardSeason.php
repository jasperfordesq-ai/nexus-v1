<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaderboardSeason extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'leaderboard_seasons';

    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id',
        'name',
        'season_type',
        'start_date',
        'end_date',
        'is_active',
        'is_finalized',
        'status',
        'rewards',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'is_finalized' => 'boolean',
        'rewards' => 'array',
    ];
}
