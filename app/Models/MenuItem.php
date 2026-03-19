<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use HasTenantScope;
    protected $table = 'menu_items';

    protected $fillable = [
        'menu_id', 'parent_id', 'type', 'label', 'url', 'route_name',
        'page_id', 'icon', 'css_class', 'target', 'sort_order',
        'visibility_rules', 'is_active',
    ];

    protected $casts = [
        'menu_id' => 'integer',
        'parent_id' => 'integer',
        'page_id' => 'integer',
        'sort_order' => 'integer',
        'visibility_rules' => 'array',
        'is_active' => 'boolean',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
