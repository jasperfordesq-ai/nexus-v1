<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceCollectionItem extends Model
{
    use HasTenantScope;

    protected $table = 'marketplace_collection_items';

    public $timestamps = false;

    protected $fillable = [
        'collection_id',
        'marketplace_listing_id',
        'note',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // ───────────────────────────────────────────────────────────
    //  Relationships
    // ───────────────────────────────────────────────────────────

    public function collection(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCollection::class, 'collection_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(MarketplaceListing::class, 'marketplace_listing_id');
    }
}
