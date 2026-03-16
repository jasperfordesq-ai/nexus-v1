<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Gamification extends Model
{
    use HasTenantScope;

    // Legacy Gamification model is a utility class that updates users.points
    // There is no dedicated gamification table — XP is tracked on the users table
    // This model exists as a placeholder for future gamification_actions table
    protected $table = 'users';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'points',
        'reason',
    ];

    protected $casts = [
        'points' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
