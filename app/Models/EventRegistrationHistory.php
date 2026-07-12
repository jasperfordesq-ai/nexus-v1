<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventCapacityRegistrationState;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable evidence for one canonical registration transition. */
final class EventRegistrationHistory extends Model
{
    use HasTenantScope;

    public $timestamps = false;

    protected $table = 'event_registration_history';

    protected $guarded = [];

    protected $casts = [
        'registration_version' => 'integer',
        'from_state' => EventCapacityRegistrationState::class,
        'to_state' => EventCapacityRegistrationState::class,
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'registration_id');
    }
}
