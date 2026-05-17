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

class Goal extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'goals';

    protected $fillable = [
        'tenant_id', 'user_id', 'title', 'description',
        'deadline', 'is_public', 'status', 'mentor_id',
        'current_value', 'target_value', 'checkin_frequency',
        'last_checkin_at', 'completed_at', 'template_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'mentor_id' => 'integer',
        'is_public' => 'boolean',
        'deadline' => 'date',
        'current_value' => 'float',
        'target_value' => 'float',
        'streak_count' => 'integer',
        'best_streak_count' => 'integer',
        'last_checkin_at' => 'datetime',
        'completed_at' => 'datetime',
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
