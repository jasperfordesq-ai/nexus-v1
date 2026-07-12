<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventRegistrationFormStatus;
use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EventRegistrationFormVersion extends Model
{
    use HasTenantScope;

    protected $table = 'event_registration_form_versions';

    protected $guarded = ['id'];

    protected $hidden = ['tenant_id', 'definition_hash'];

    protected $casts = [
        'version_number' => 'integer',
        'revision' => 'integer',
        'status' => EventRegistrationFormStatus::class,
        'published_at' => 'immutable_datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(EventRegistrationFormQuestion::class, 'form_version_id')
            ->orderBy('position')
            ->orderBy('id');
    }
}
