<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedActivity extends Model
{
    use HasTenantScope;

    protected $table = 'feed_activity';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'source_type', 'source_id', 'user_id',
        'title', 'content', 'image_url', 'metadata',
        'group_id', 'is_visible', 'is_hidden', 'created_at',
    ];

    protected $casts = [
        'source_id' => 'integer',
        'user_id' => 'integer',
        'group_id' => 'integer',
        'is_visible' => 'boolean',
        'is_hidden' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
