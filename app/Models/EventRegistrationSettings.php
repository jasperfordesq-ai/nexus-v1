<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventRegistrationApprovalMode;
use App\Enums\EventRegistrationSettingsStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EventRegistrationSettings extends Model
{
    use HasTenantScope;

    protected $table = 'event_registration_settings';

    protected $guarded = ['id'];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'revision' => 'integer',
        'status' => EventRegistrationSettingsStatus::class,
        'approval_mode' => EventRegistrationApprovalMode::class,
        'event_starts_at_utc_snapshot' => 'immutable_datetime',
        'opens_at_utc' => 'immutable_datetime',
        'closes_at_utc' => 'immutable_datetime',
        'cancellation_cutoff_at_utc' => 'immutable_datetime',
        'per_member_limit' => 'integer',
        'guests_enabled' => 'boolean',
        'max_guests_per_registration' => 'integer',
        'guest_retention_days' => 'integer',
        'published_form_version' => 'integer',
        'published_at' => 'immutable_datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(EventRegistrationFormVersion::class, 'event_id', 'event_id');
    }
}
