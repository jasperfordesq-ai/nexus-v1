<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BlockedUser — represents a block relationship between two users.
 *
 * Uses the legacy `user_blocks` table which does NOT have a tenant_id column.
 * Block checks are global (user IDs are unique across tenants).
 */
class BlockedUser extends Model
{
    protected $table = 'user_blocks';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'blocked_user_id',
        'reason',
    ];

    protected $casts = [
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
