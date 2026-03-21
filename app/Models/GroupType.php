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

class GroupType extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'group_types';

    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description', 'icon',
        'color', 'image_url', 'sort_order', 'is_active', 'is_hub',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_hub' => 'boolean',
    ];

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'type_id');
    }
}
