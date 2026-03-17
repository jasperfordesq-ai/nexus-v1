<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalTemplate extends Model
{
    use HasTenantScope;

    protected $table = 'goal_templates';

    protected $fillable = [
        'tenant_id', 'title', 'description', 'category',
        'default_target_value', 'default_milestones', 'is_public',
        'created_by',
    ];

    protected $casts = [
        'default_target_value' => 'float',
        'default_milestones'   => 'array',
        'is_public'            => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
