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

class FeedPost extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'feed_posts';

    protected $fillable = [
        'tenant_id', 'user_id', 'content', 'emoji', 'image_url', 'type',
        'parent_id', 'parent_type', 'visibility', 'group_id',
        'scheduled_at', 'publish_status',
    ];

    /**
     * Attributes hidden from JSON serialization to prevent data leakage.
     */
    protected $hidden = ['tenant_id'];

    protected $casts = [
        'user_id' => 'integer',
        'parent_id' => 'integer',
        'group_id' => 'integer',
        'likes_count' => 'integer',
        'comments_count' => 'integer',
        'is_pinned' => 'boolean',
        'is_hidden' => 'boolean',
        'scheduled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
