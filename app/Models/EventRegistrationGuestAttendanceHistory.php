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

final class EventRegistrationGuestAttendanceHistory extends Model
{
    use HasTenantScope;

    public $timestamps = false;

    protected $table = 'event_registration_guest_attendance_history';

    protected $guarded = ['id'];

    protected $hidden = ['tenant_id', 'idempotency_hash', 'request_hash'];

    protected $casts = [
        'attendance_version' => 'integer',
        'from_status' => EventAttendanceState::class,
        'to_status' => EventAttendanceState::class,
        'metadata' => 'array',
        'created_at' => 'immutable_datetime',
    ];
}
