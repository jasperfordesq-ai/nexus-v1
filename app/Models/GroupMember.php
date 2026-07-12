<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Core\TenantContext;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupMember extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'group_members';

    protected $fillable = [
        'group_id', 'user_id', 'role', 'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $membership): void {
            // The legacy schema defaults tenant_id to 1. Force the ambient
            // tenant even when the attribute appears set by that DB default.
            // The parent group is authoritative, which also makes an explicit
            // cross-tenant tenant_id input harmless.
            $groupTenantId = Group::withoutGlobalScopes()
                ->whereKey((int) $membership->group_id)
                ->value('tenant_id');

            if ($groupTenantId || TenantContext::getId()) {
                $membership->tenant_id = (int) ($groupTenantId ?: TenantContext::getId());
            }
        });
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
