<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedPost extends Model
{
    use HasTenantScope;

    protected $table = 'feed_posts';

    protected $fillable = [
        'tenant_id', 'user_id', 'content', 'emoji', 'image_url',
        'parent_id', 'parent_type', 'visibility',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'parent_id' => 'integer',
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
