<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasTenantScope;

    protected $table = 'reviews';

    protected $fillable = [
        'tenant_id', 'reviewer_id', 'receiver_id', 'transaction_id',
        'group_id', 'rating', 'comment', 'status',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function scopeForReceiver(Builder $query, int $userId): Builder
    {
        return $query->where('receiver_id', $userId);
    }

    public function scopeInGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('group_id', $groupId);
    }
}
