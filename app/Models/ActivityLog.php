<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ActivityLog extends Model
{
    use HasTenantScope;
    protected $table = 'activity_log';

    protected $fillable = [
        'user_id', 'action', 'details', 'is_public', 'link_url',
        'ip_address', 'action_type', 'entity_type', 'entity_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_public' => 'boolean',
        'entity_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log an activity entry.
     * Legacy-compatible static method.
     */
    public static function log(
        int $userId,
        string $action,
        string $details = '',
        bool $isPublic = false,
        ?string $linkUrl = null,
        string $actionType = 'system',
        ?string $entityType = null,
        ?int $entityId = null
    ): int {
        $ip = \App\Core\ClientIp::get();

        $id = DB::table('activity_log')->insertGetId([
            'tenant_id' => \App\Core\TenantContext::getId(),
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
            'is_public' => $isPublic ? 1 : 0,
            'link_url' => $linkUrl,
            'ip_address' => $ip,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'created_at' => now(),
        ]);

        return (int) $id;
    }
}
