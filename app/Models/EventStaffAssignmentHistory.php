<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventStaffAssignmentStatus;
use App\Enums\EventStaffRole;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only grant/revoke evidence; database triggers enforce immutability. */
final class EventStaffAssignmentHistory extends Model
{
    use HasTenantScope;

    public const UPDATED_AT = null;

    protected $table = 'event_staff_assignment_history';

    protected $guarded = ['*'];

    protected $casts = [
        'role' => EventStaffRole::class,
        'from_status' => EventStaffAssignmentStatus::class,
        'to_status' => EventStaffAssignmentStatus::class,
        'assignment_version' => 'integer',
        'previous_expires_at' => 'immutable_datetime',
        'new_expires_at' => 'immutable_datetime',
        'metadata' => 'array',
        'created_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('event_staff_assignment_history_immutable');
        });
        static::deleting(static function (): never {
            throw new LogicException('event_staff_assignment_history_immutable');
        });
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(EventStaffAssignment::class, 'assignment_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
