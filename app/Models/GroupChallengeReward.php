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

final class GroupChallengeReward extends Model
{
    use HasTenantScope;

    public $timestamps = false;

    protected $table = 'group_challenge_rewards';

    protected $fillable = [
        'tenant_id',
        'challenge_id',
        'user_id',
        'reward_xp',
        'awarded_at',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'challenge_id' => 'integer',
        'user_id' => 'integer',
        'reward_xp' => 'integer',
        'awarded_at' => 'immutable_datetime',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(GroupChallenge::class, 'challenge_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
