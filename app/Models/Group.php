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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'groups';

    protected $fillable = [
        'tenant_id', 'owner_id', 'name', 'description', 'image_url',
        'cover_image_url', 'visibility', 'location', 'latitude', 'longitude',
        'type_id', 'parent_id', 'is_featured', 'has_children',
        'cached_member_count', 'federated_visibility',
    ];

    protected $appends = ['members_count', 'member_count'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_featured' => 'boolean',
        'has_children' => 'boolean',
        'cached_member_count' => 'integer',
    ];

    /**
     * Accessor: React frontend expects 'members_count'.
     */
    public function getMembersCountAttribute(): int
    {
        return $this->cached_member_count ?? 0;
    }

    /**
     * Accessor: Some endpoints use 'member_count'.
     */
    public function getMemberCountAttribute(): int
    {
        return $this->cached_member_count ?? 0;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
                     ->withPivot('role', 'status')
                     ->withTimestamps();
    }

    public function activeMembers(): BelongsToMany
    {
        return $this->members()->wherePivot('status', 'active');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(GroupType::class, 'type_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function discussions(): HasMany
    {
        return $this->hasMany(GroupDiscussion::class);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', 'public');
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('parent_id')->orWhere('parent_id', 0);
        });
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }
}
