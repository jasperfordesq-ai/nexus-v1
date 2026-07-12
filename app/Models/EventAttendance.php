<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Canonical durable attendance fact for one member at one concrete event. */
final class EventAttendance extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'event_attendance';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'user_id',
        'attendance_status',
        'attendance_version',
        'status_changed_at',
        'status_changed_by',
        'checked_in_at',
        'checked_in_by',
        'checked_out_at',
        'hours_credited',
        'notes',
    ];

    protected $hidden = [
        'tenant_id',
        'notes',
    ];

    protected $casts = [
        'attendance_version' => 'integer',
        'status_changed_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'checked_out_at' => 'datetime',
        'hours_credited' => 'decimal:2',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function activity(): HasMany
    {
        return $this->hasMany(EventAttendanceActivity::class, 'attendance_id');
    }

    public function creditClaims(): HasMany
    {
        return $this->hasMany(EventAttendanceCreditClaim::class, 'attendance_id');
    }
}
