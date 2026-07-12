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

/** AES-GCM vault record for one external guardian-delivery token. */
final class EventGuardianConsentDeliveryEnvelope extends Model
{
    use HasTenantScope;

    protected $table = 'event_guardian_consent_delivery_envelopes';

    protected $guarded = [];

    protected $hidden = [
        'tenant_id',
        'token_ciphertext',
        'claim_token_hash',
        'key_fingerprint',
        'aad_hash',
    ];

    protected $casts = [
        'consent_version' => 'integer',
        'envelope_version' => 'integer',
        'claimed_at' => 'immutable_datetime',
        'handed_off_at' => 'immutable_datetime',
        'erased_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
    ];

    public function consent(): BelongsTo
    {
        return $this->belongsTo(EventGuardianConsent::class, 'consent_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
