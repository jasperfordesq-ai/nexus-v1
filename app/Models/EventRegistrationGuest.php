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

final class EventRegistrationGuest extends Model
{
    use HasTenantScope;

    protected $table = 'event_registration_guests';

    protected $guarded = ['id'];

    protected $hidden = [
        'tenant_id',
        'display_name_ciphertext',
        'email_ciphertext',
        'phone_ciphertext',
        'identity_fingerprint',
        'consent_text_hash',
        'notification_consent_text_hash',
    ];

    protected $casts = [
        'guest_number' => 'integer',
        'revision' => 'integer',
        'notification_consent' => 'boolean',
        'notification_consented_at' => 'immutable_datetime',
        'ticket_entitlement_id' => 'integer',
        'consented_at' => 'immutable_datetime',
        'retention_due_at' => 'immutable_datetime',
        'withdrawn_at' => 'immutable_datetime',
        'anonymised_at' => 'immutable_datetime',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'registration_id');
    }
}
