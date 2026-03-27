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

class CustomBadge extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'custom_badges';

    protected $fillable = [
        'tenant_id',
        'badge_key',
        'name',
        'description',
        'icon',
        'icon_url',
        'badge_type',
        'trigger_type',
        'trigger_condition',
        'xp_reward',
        'is_active',
        'created_by',
        'category',
    ];

    protected $casts = [
        'xp_reward' => 'integer',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
