<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupDiscussion extends Model
{
    use HasTenantScope;

    protected $table = 'group_discussions';

    protected $fillable = [
        'tenant_id', 'group_id', 'user_id', 'title', 'is_pinned',
    ];

    protected $casts = [
        'group_id' => 'integer',
        'user_id' => 'integer',
        'is_pinned' => 'boolean',
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
