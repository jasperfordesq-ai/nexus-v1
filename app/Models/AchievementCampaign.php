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

class AchievementCampaign extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'achievement_campaigns';

    const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'campaign_type', 'badge_key',
        'xp_amount', 'target_criteria', 'schedule_type', 'scheduled_at',
        'recurrence_pattern', 'status', 'created_by',
        'executed_at', 'target_audience', 'audience_config', 'schedule',
        'activated_at', 'last_run_at', 'total_awards',
    ];

    protected $casts = [
        'xp_amount' => 'integer',
        'total_awards' => 'integer',
        'audience_config' => 'array',
        'activated_at' => 'datetime',
        'last_run_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
