<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BlockedUser — represents a block relationship between two users.
 *
 * Block checks are scoped by tenant.
 */
class BlockedUser extends Model
{
    use HasTenantScope;

    protected $table = 'user_blocks';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'blocked_user_id',
        'reason',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'user_id' => 'integer',
        'blocked_user_id' => 'integer',
    ];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_user_id');
    }
}
