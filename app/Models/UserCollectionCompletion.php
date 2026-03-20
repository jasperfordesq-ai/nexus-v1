<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCollectionCompletion extends Model
{
    protected $table = 'user_collection_completions';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'collection_id', 'bonus_claimed',
    ];

    protected $casts = [
        'bonus_claimed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(BadgeCollection::class, 'collection_id');
    }
}
