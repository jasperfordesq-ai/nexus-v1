<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $table = 'activity_log';

    protected $fillable = [
        'user_id', 'action', 'details', 'is_public', 'link_url',
        'ip_address', 'action_type', 'entity_type', 'entity_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_public' => 'boolean',
        'entity_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
