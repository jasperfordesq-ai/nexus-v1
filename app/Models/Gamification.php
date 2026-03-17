<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Nexus\Core\TenantContext;

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

    /**
     * Award points to a user for an action.
     */
    public static function awardPoints(int $userId, string $action, int $points, ?string $description = null): void
    {
        try {
            $tenantId = TenantContext::getId();

            DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'points' => DB::raw("points + {$points}"),
                ]);
        } catch (\Exception $e) {
            // Column may not exist — silently fail
            error_log("Gamification::awardPoints error: " . $e->getMessage());
        }
    }
}
