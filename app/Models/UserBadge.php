<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBadge extends Model
{
    protected $table = 'user_badges';

    public $timestamps = false;

    const CREATED_AT = 'awarded_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'badge_key',
        'name',
        'icon',
        'is_showcased',
        'showcase_order',
    ];

    protected $casts = [
        'is_showcased' => 'boolean',
        'showcase_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
