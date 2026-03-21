<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Error404Log extends Model
{
    use HasFactory;

    protected $table = 'error_404_log';

    public $timestamps = false;

    protected $fillable = [
        'url',
        'referer',
        'user_agent',
        'ip_address',
        'user_id',
        'hit_count',
        'first_seen_at',
        'last_seen_at',
        'resolved',
        'redirect_id',
        'notes',
    ];

    protected $casts = [
        'hit_count' => 'integer',
        'resolved' => 'boolean',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
