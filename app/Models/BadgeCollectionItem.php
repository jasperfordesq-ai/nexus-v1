<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// INTENTIONAL: No tenant scope — badge_collection_items has no tenant_id column.
// Scoped indirectly via parent BadgeCollection (collection_id -> badge_collections.tenant_id).
class BadgeCollectionItem extends Model
{
    use HasFactory;

    protected $table = 'badge_collection_items';

    // M9: Timestamps enabled once 2026_04_12_000002 migration adds created_at/updated_at.
    // Migration uses Schema::hasColumn guards so this is safe before/after migrate.

    protected $fillable = [
        'collection_id', 'badge_key', 'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(BadgeCollection::class, 'collection_id');
    }
}
