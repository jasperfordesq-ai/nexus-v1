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
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Persistent reminder intent, independent from any one calculated schedule. */
final class EventReminderRule extends Model
{
    use HasTenantScope;

    protected $table = 'event_reminder_rules';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'user_id',
        'offset_minutes',
        'email_enabled',
        'in_app_enabled',
        'web_push_enabled',
        'fcm_enabled',
        'realtime_enabled',
        'enabled',
        'rule_version',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'offset_minutes' => 'integer',
        'email_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'web_push_enabled' => 'boolean',
        'fcm_enabled' => 'boolean',
        'realtime_enabled' => 'boolean',
        'enabled' => 'boolean',
        'rule_version' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(EventReminderSchedule::class, 'rule_id');
    }
}
