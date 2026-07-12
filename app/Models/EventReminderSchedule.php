<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Versioned calculation and durable aggregate state for one reminder due time. */
final class EventReminderSchedule extends Model
{
    use HasTenantScope;

    protected $table = 'event_reminder_schedules';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'user_id',
        'rule_id',
        'registration_id',
        'offset_minutes',
        'rule_version',
        'registration_version',
        'event_calendar_sequence',
        'schedule_version',
        'scheduled_for',
        'deliver_until',
        'status',
        'reason_code',
        'outbox_id',
        'queued_at',
        'delivered_at',
        'cancelled_at',
        'superseded_at',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'rule_id' => 'integer',
        'registration_id' => 'integer',
        'offset_minutes' => 'integer',
        'rule_version' => 'integer',
        'registration_version' => 'integer',
        'event_calendar_sequence' => 'integer',
        'schedule_version' => 'integer',
        'outbox_id' => 'integer',
        'scheduled_for' => 'datetime',
        'deliver_until' => 'datetime',
        'queued_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'superseded_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(EventReminderRule::class, 'rule_id');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'registration_id');
    }
}
