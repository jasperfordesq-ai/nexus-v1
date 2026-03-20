<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountRelationship extends Model
{
    use HasTenantScope;

    protected $table = 'account_relationships';

    protected $fillable = [
        'tenant_id', 'parent_user_id', 'child_user_id',
        'relationship_type', 'permissions', 'status', 'approved_at',
    ];

    protected $casts = [
        'parent_user_id' => 'integer',
        'child_user_id'  => 'integer',
        'permissions'     => 'array',
        'approved_at'     => 'datetime',
    ];

    public function parentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function childUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'child_user_id');
    }
}
