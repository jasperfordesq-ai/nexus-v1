<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Like extends Model
{
    use HasTenantScope;

    protected $table = 'likes';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'user_id', 'target_type', 'target_id', 'created_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'target_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
