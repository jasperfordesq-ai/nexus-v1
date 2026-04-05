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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MarketplaceCollection extends Model
{
    use HasTenantScope;

    protected $table = 'marketplace_collections';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_public',
        'item_count',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'is_public' => 'boolean',
        'item_count' => 'integer',
    ];

    // ───────────────────────────────────────────────────────────
    //  Relationships
    // ───────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MarketplaceCollectionItem::class, 'collection_id');
    }

    public function listings(): BelongsToMany
    {
        return $this->belongsToMany(
            MarketplaceListing::class,
            'marketplace_collection_items',
            'collection_id',
            'marketplace_listing_id'
        )->withPivot('note')->withTimestamps();
    }
}
