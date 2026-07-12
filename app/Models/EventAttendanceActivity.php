<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Append-only staff activity ledger for attendance state changes. */
final class EventAttendanceActivity extends Model
{
    use HasTenantScope;

    protected $table = 'event_attendance_activity';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'event_id',
        'attendance_id',
        'user_id',
        'actor_user_id',
        'attendance_version',
        'action',
        'from_status',
        'to_status',
        'idempotency_key',
        'reason',
        'metadata',
        'created_at',
    ];

    protected $hidden = [
        'tenant_id',
        'reason',
        'metadata',
        'idempotency_key',
    ];

    protected $casts = [
        'attendance_version' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Event attendance activity is immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Event attendance activity is immutable.');
        });
    }
}
