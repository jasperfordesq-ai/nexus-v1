<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

class SavedItem extends Model
{
    protected $table = 'saved_items';

    public $timestamps = false;

    protected $fillable = [
        'collection_id', 'user_id', 'tenant_id',
        'item_type', 'item_id', 'note', 'saved_at',
    ];

    protected $casts = [
        'saved_at' => 'datetime',
        'item_id' => 'int',
    ];
}
