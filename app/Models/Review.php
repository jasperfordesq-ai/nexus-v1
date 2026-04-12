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

class Review extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'reviews';

    protected $fillable = [
        'tenant_id', 'reviewer_id', 'receiver_id', 'transaction_id',
        'group_id', 'rating', 'comment', 'status',
        'review_type', 'dimensions',
        // Federated review fields — required for reputation portability
        'receiver_tenant_id', 'reviewer_tenant_id', 'show_cross_tenant',
    ];

    /**
     * Attributes hidden from JSON serialization to prevent data leakage.
     */
    protected $hidden = ['tenant_id'];

    protected $casts = [
        'rating' => 'integer',
        'dimensions' => 'array',
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

    /**
     * Include both local reviews (tenant-scoped by the global TenantScope)
     * AND federated reviews where `receiver_tenant_id` = current tenant and
     * `review_type = 'federated'` and `show_cross_tenant = 1`.
     *
     * We drop the global TenantScope and re-express the filter as a single
     * OR clause. This supports reputation portability: members keep their
     * reviews when they interact across tenants.
     */
    public function scopeWithFederated(Builder $query): Builder
    {
        $tenantId = \App\Core\TenantContext::getId();

        // Without an active tenant context we can't safely expand the scope,
        // so leave the query unchanged (global scope still applies).
        if (! $tenantId) {
            return $query;
        }

        $table = $this->getTable();

        return $query->withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where(function (Builder $q) use ($tenantId, $table) {
                $q->where($table . '.tenant_id', $tenantId)
                  ->orWhere(function (Builder $q2) use ($tenantId, $table) {
                      $q2->where($table . '.receiver_tenant_id', $tenantId)
                         ->where($table . '.review_type', 'federated')
                         ->where($table . '.show_cross_tenant', 1);
                  });
            });
    }

    public function scopeInGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('group_id', $groupId);
    }
}
