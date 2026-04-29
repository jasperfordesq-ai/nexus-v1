<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavedCollection extends Model
{
    protected $table = 'saved_collections';

    protected $fillable = [
        'user_id', 'tenant_id', 'name', 'description',
        'is_public', 'color', 'icon', 'items_count',
    ];

    protected $casts = [
        'is_public' => 'bool',
        'items_count' => 'int',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SavedItem::class, 'collection_id');
    }
}
