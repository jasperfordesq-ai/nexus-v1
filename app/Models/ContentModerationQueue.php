<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentModerationQueue extends Model
{
    use HasTenantScope;

    protected $table = 'content_moderation_queue';

    protected $fillable = [
        'tenant_id',
        'content_type',
        'content_id',
        'author_id',
        'title',
        'status',
        'reviewer_id',
        'reviewed_at',
        'rejection_reason',
        'auto_flagged',
        'flag_reason',
    ];

    protected $casts = [
        'content_id' => 'integer',
        'author_id' => 'integer',
        'reviewer_id' => 'integer',
        'auto_flagged' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
