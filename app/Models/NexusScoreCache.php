<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusScoreCache extends Model
{
    use HasTenantScope;

    protected $table = 'nexus_score_cache';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'user_id', 'total_score',
        'engagement_score', 'quality_score', 'volunteer_score',
        'activity_score', 'badge_score', 'impact_score',
        'percentile', 'tier', 'calculated_at',
    ];

    protected $casts = [
        'total_score' => 'float',
        'engagement_score' => 'float',
        'quality_score' => 'float',
        'volunteer_score' => 'float',
        'activity_score' => 'float',
        'badge_score' => 'float',
        'impact_score' => 'float',
        'percentile' => 'integer',
        'calculated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
