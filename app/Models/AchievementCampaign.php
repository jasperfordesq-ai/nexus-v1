<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;

class AchievementCampaign extends Model
{
    use HasTenantScope;

    protected $table = 'achievement_campaigns';

    protected $fillable = [
        'tenant_id', 'name', 'description', 'campaign_type', 'badge_key',
        'xp_amount', 'target_audience', 'audience_config', 'schedule',
        'status', 'activated_at', 'last_run_at', 'total_awards',
    ];

    protected $casts = [
        'xp_amount' => 'integer',
        'total_awards' => 'integer',
        'audience_config' => 'array',
        'activated_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];
}
