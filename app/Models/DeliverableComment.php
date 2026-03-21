<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliverableComment extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'deliverable_comments';

    protected $fillable = [
        'tenant_id', 'deliverable_id', 'user_id', 'comment_text',
        'comment_type', 'parent_comment_id', 'mentioned_user_ids',
        'reactions', 'is_pinned', 'is_edited', 'edited_at',
        'is_deleted', 'deleted_at',
    ];

    protected $casts = [
        'deliverable_id' => 'integer',
        'user_id' => 'integer',
        'parent_comment_id' => 'integer',
        'mentioned_user_ids' => 'array',
        'reactions' => 'array',
        'is_pinned' => 'boolean',
        'is_edited' => 'boolean',
        'is_deleted' => 'boolean',
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(Deliverable::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_comment_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_comment_id');
    }
}
