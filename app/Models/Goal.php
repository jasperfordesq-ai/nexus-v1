<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    use HasTenantScope;

    protected $table = 'goals';

    protected $fillable = [
        'tenant_id', 'user_id', 'title', 'description',
        'deadline', 'is_public', 'status', 'mentor_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'mentor_id' => 'integer',
        'is_public' => 'boolean',
        'deadline' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }
}
