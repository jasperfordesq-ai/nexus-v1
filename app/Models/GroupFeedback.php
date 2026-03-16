<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupFeedback extends Model
{
    protected $table = 'group_feedback';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'group_id', 'user_id', 'rating', 'comment',
    ];

    protected $casts = [
        'group_id' => 'integer',
        'user_id' => 'integer',
        'rating' => 'integer',
        'created_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
