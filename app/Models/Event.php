<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'events';

    protected $fillable = [
        'tenant_id', 'user_id', 'title', 'description', 'location',
        'latitude', 'longitude', 'start_time', 'end_time', 'group_id',
        'category_id', 'max_attendees', 'is_online', 'online_link',
        'image_url', 'federated_visibility',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'max_attendees' => 'integer',
        'is_online' => 'boolean',
    ];

    /**
     * Appended attributes for frontend compatibility.
     *
     * Frontend expects `start_date`/`end_date` (aliases for start_time/end_time).
     */
    protected $appends = ['start_date', 'end_date'];

    public function getStartDateAttribute(): ?string
    {
        return $this->start_time?->toIso8601String();
    }

    public function getEndDateAttribute(): ?string
    {
        return $this->end_time?->toIso8601String();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_time', '>=', now())->orderBy('start_time');
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('end_time', '<', now())->orderByDesc('start_time');
    }
}
