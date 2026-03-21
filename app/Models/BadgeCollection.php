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

class BadgeCollection extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'badge_collections';

    protected $fillable = [
        'tenant_id', 'collection_key', 'name', 'description',
        'icon', 'bonus_xp', 'bonus_badge_key', 'display_order',
    ];

    protected $casts = [
        'bonus_xp' => 'integer',
        'display_order' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(BadgeCollectionItem::class, 'collection_id');
    }
}
