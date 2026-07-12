<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Core\TenantContext;
use App\Enums\GroupStatus;
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
        'owner_id', 'name', 'description', 'image_url',
        'cover_image_url', 'visibility', 'location', 'latitude', 'longitude',
        'primary_color', 'accent_color',
        'type_id', 'template_id', 'template_features', 'parent_id', 'is_featured', 'has_children',
        'cached_member_count', 'federated_visibility',
        'source_idea_id', 'source_challenge_id',
    ];

    protected $appends = ['members_count', 'member_count'];

    /**
     * Attributes hidden from JSON serialization to prevent data leakage.
     */
    protected $hidden = ['tenant_id'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_featured' => 'boolean',
        'has_children' => 'boolean',
        'cached_member_count' => 'integer',
        'status' => GroupStatus::class,
        'is_active' => 'boolean',
        'template_features' => 'array',
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
        $tenantId = (int) ($this->getAttribute('tenant_id') ?: TenantContext::getId());

        return $this->belongsToMany(User::class, 'group_members')
                     ->wherePivot('tenant_id', $tenantId)
                     ->withPivot('role', 'status', 'tenant_id')
                     ->withTimestamps();
    }

    /**
     * Attach members with automatic tenant_id population.
     *
     * The group_members.tenant_id defaults to 1 in the DB, which causes
     * tenant-scoped queries to fail. This ensures attach() always sets
     * tenant_id to match the group's tenant.
     */
    public function attachMember(int $userId, array $attributes = []): void
    {
        // SECURITY: Always scope to the GROUP's tenant, not the ambient
        // TenantContext. A super-admin or cross-tenant actor operating on a
        // Group loaded from another tenant must still produce pivot rows
        // tagged with the group's real tenant_id — otherwise group_members
        // rows would leak across tenants.
        $groupTenantId = $this->tenant_id;
        if (!$groupTenantId) {
            // Fall back only if model somehow lacks tenant_id (shouldn't happen
            // for persisted groups); this keeps behaviour safe instead of
            // producing a silently broken row.
            $groupTenantId = \App\Core\TenantContext::getId();
        }
        $attributes['tenant_id'] = $groupTenantId;
        $this->members()->attach($userId, $attributes);
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

    /** @param list<GroupStatus|string> $statuses */
    public function scopeInStates(Builder $query, array $statuses): Builder
    {
        $values = array_map(
            static fn (GroupStatus|string $status): string => $status instanceof GroupStatus
                ? $status->value
                : $status,
            $statuses,
        );

        return $query->whereIn('status', $values);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', GroupStatus::Active->value);
    }

    public function scopeJoinable(Builder $query): Builder
    {
        return $query->active();
    }

    public function scopeWritable(Builder $query): Builder
    {
        return $query->active();
    }

    /**
     * Overview visibility. Child resources use the stricter GroupAccessService
     * member-content decision and must not infer access from this scope.
     */
    public function scopeViewableBy(
        Builder $query,
        int|null $userId,
        bool $isTenantAdmin = false,
    ): Builder {
        if ($userId === null) {
            return $query->whereRaw('1 = 0');
        }

        $tenantId = (int) TenantContext::getId();
        $query->whereExists(static function ($actor) use ($userId, $tenantId): void {
            $actor->selectRaw('1')
                ->from('users as group_actor')
                ->where('group_actor.id', $userId)
                ->where('group_actor.tenant_id', $tenantId);
        });

        if ($isTenantAdmin) {
            return $query;
        }

        return $query->where(function (Builder $visibility) use ($userId, $tenantId): void {
            $visibility->where('status', GroupStatus::Active->value)
                ->orWhere('owner_id', $userId)
                ->orWhere(function (Builder $memberState) use ($userId, $tenantId): void {
                    $memberState
                        ->where('status', GroupStatus::Dormant->value)
                        ->whereIn('id', function ($membership) use ($userId, $tenantId): void {
                            $membership->select('group_id')
                                ->from('group_members')
                                ->where('tenant_id', $tenantId)
                                ->where('user_id', $userId)
                                ->where('status', 'active');
                        });
                });
        });
    }

    public function scopeManageableBy(
        Builder $query,
        int $userId,
        bool $isTenantAdmin = false,
    ): Builder {
        $tenantId = (int) TenantContext::getId();
        $query->whereExists(static function ($actor) use ($userId, $tenantId): void {
            $actor->selectRaw('1')
                ->from('users as group_actor')
                ->where('group_actor.id', $userId)
                ->where('group_actor.tenant_id', $tenantId);
        });

        if ($isTenantAdmin) {
            return $query;
        }

        return $query->where(function (Builder $manageable) use ($userId, $tenantId): void {
            $manageable->where('owner_id', $userId)
                ->orWhereIn('id', function ($membership) use ($userId, $tenantId): void {
                    $membership->select('group_id')
                        ->from('group_members')
                        ->where('tenant_id', $tenantId)
                        ->where('user_id', $userId)
                        ->where('status', 'active')
                        ->whereIn('role', ['owner', 'admin']);
                });
        });
    }
}
