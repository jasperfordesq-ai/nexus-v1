<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use App\Core\TenantContext;

class EventRsvp extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'event_rsvps';

    protected $fillable = [
        'tenant_id', 'event_id', 'user_id', 'status',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a user's RSVP status for an event.
     */
    public static function getUserStatus(int $eventId, int $userId): ?string
    {
        $tenantId = TenantContext::getId();

        $row = DB::table('event_rsvps as r')
            ->join('events as e', 'r.event_id', '=', 'e.id')
            ->where('r.event_id', $eventId)
            ->where('r.user_id', $userId)
            ->where('e.tenant_id', $tenantId)
            ->value('r.status');

        return $row ?: null;
    }

    /**
     * RSVP to an event (insert or update).
     */
    public static function rsvp(int $eventId, int $userId, string $status): bool
    {
        $tenantId = TenantContext::getId();

        $existing = DB::table('event_rsvps as r')
            ->join('events as e', 'r.event_id', '=', 'e.id')
            ->where('r.event_id', $eventId)
            ->where('r.user_id', $userId)
            ->where('e.tenant_id', $tenantId)
            ->select('r.id')
            ->first();

        if ($existing) {
            DB::table('event_rsvps')
                ->where('id', $existing->id)
                ->where('event_id', $eventId)
                ->update(['status' => $status]);
        } else {
            DB::table('event_rsvps')->insert([
                'event_id' => $eventId,
                'user_id' => $userId,
                'status' => $status,
                'tenant_id' => $tenantId,
                'created_at' => now(),
            ]);
        }

        return true;
    }
}
