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

class GroupPost extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'group_posts';

    protected $fillable = [
        'tenant_id', 'discussion_id', 'user_id', 'content',
    ];

    protected $casts = [
        'discussion_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(GroupDiscussion::class, 'discussion_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
