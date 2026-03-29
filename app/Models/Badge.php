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

class Badge extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'badges';

    protected $fillable = [
        'tenant_id',
        'badge_key',
        'name',
        'description',
        'icon',
        'color',
        'image_url',
        'xp_value',
        'rarity',
        'category',
        'sort_order',
        'points_required',
        'is_hidden',
        'is_active',
        'badge_tier',
        'badge_class',
        'threshold',
        'threshold_type',
        'evaluation_method',
        'is_enabled',
        'config_json',
    ];

    protected $casts = [
        'xp_value' => 'integer',
        'sort_order' => 'integer',
        'points_required' => 'integer',
        'is_hidden' => 'boolean',
        'is_active' => 'boolean',
        'is_enabled' => 'boolean',
        'threshold' => 'integer',
    ];

    public function userBadges(): HasMany
    {
        return $this->hasMany(UserBadge::class, 'badge_key', 'badge_key');
    }
}
