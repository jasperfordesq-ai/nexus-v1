<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class GroupChallenge extends Model
{
    use HasTenantScope;

    protected $table = 'group_challenges';

    protected $fillable = [
        'tenant_id',
        'group_id',
        'created_by',
        'title',
        'description',
        'metric',
        'target_value',
        'current_value',
        'reward_xp',
        'reward_badge',
        'status',
        'starts_at',
        'ends_at',
        'completed_at',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'group_id' => 'integer',
        'created_by' => 'integer',
        'target_value' => 'integer',
        'current_value' => 'integer',
        'reward_xp' => 'integer',
        'starts_at' => 'immutable_datetime',
        'ends_at' => 'immutable_datetime',
        'completed_at' => 'immutable_datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(GroupChallengeReward::class, 'challenge_id');
    }
}
