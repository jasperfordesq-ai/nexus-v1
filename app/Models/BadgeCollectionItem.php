<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgeCollectionItem extends Model
{
    use HasFactory;

    protected $table = 'badge_collection_items';

    public $timestamps = false;

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
