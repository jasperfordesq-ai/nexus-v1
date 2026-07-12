<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventAttendanceState;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventRegistrationGuestAttendance extends Model
{
    use HasTenantScope;

    protected $table = 'event_registration_guest_attendance';

    protected $guarded = ['id'];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'attendance_status' => EventAttendanceState::class,
        'attendance_version' => 'integer',
        'status_changed_at' => 'immutable_datetime',
        'checked_in_at' => 'immutable_datetime',
        'checked_out_at' => 'immutable_datetime',
        'attended_at' => 'immutable_datetime',
        'no_show_at' => 'immutable_datetime',
    ];

    public function guest(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationGuest::class, 'guest_id');
    }
}
