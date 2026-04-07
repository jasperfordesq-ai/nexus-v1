<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostView extends Model
{
    use HasTenantScope;

    protected $table = 'post_views';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'post_id',
        'user_id',
        'ip_hash',
        'viewed_at',
    ];

    protected $hidden = ['tenant_id', 'ip_hash'];

    protected $casts = [
        'post_id' => 'integer',
        'user_id' => 'integer',
        'viewed_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
