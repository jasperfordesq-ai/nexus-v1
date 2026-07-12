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

/** Nullable Event channel and cadence overrides for one member and scope. */
final class EventNotificationPreference extends Model
{
    use HasTenantScope;

    protected $table = 'event_notification_preferences';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'event_id',
        'category_id',
        'email_enabled',
        'in_app_enabled',
        'web_push_enabled',
        'fcm_enabled',
        'realtime_enabled',
        'cadence',
        'reminders_enabled',
        'preference_version',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'email_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'web_push_enabled' => 'boolean',
        'fcm_enabled' => 'boolean',
        'realtime_enabled' => 'boolean',
        'reminders_enabled' => 'boolean',
        'preference_version' => 'integer',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
