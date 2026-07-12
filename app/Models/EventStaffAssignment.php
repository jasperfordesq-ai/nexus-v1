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
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class EventStaffAssignment extends Model
{
    use HasTenantScope;

    protected $table = 'event_staff_assignments';

    protected $guarded = ['*'];

    protected $casts = [
        'role' => EventStaffRole::class,
        'status' => EventStaffAssignmentStatus::class,
        'assignment_version' => 'integer',
        'granted_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('event_staff_assignment_delete_forbidden');
        });
    }

    public function isEffective(?DateTimeInterface $at = null): bool
    {
        if ($this->status !== EventStaffAssignmentStatus::Active) {
            return false;
        }

        $at ??= now();

        return $this->expires_at === null || $this->expires_at->isAfter($at);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(EventStaffAssignmentHistory::class, 'assignment_id')
            ->orderBy('assignment_version');
    }
}
