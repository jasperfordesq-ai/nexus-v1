<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUserLimit extends Model
{
    use HasTenantScope;

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
}
