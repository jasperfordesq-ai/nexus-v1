<?php
// Copyright � 2024�2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

class Gamification extends Model
{
    use HasFactory, HasTenantScope;

    // Legacy Gamification model is a utility class that updates users.points
    // The gamifications table has only id + created_at — it is a stub table.
    // XP is tracked on the users table directly via awardPoints().
    protected $table = 'gamifications';

    const UPDATED_AT = null;

    protected $fillable = [];

    protected $casts = [];

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
                    'points' => DB::raw('points + ' . (int) $points),
                ]);
        } catch (\Exception $e) {
            // Column may not exist — silently fail
            error_log("Gamification::awardPoints error: " . $e->getMessage());
        }
    }
}
