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

/** Encrypted one-time delivery secret for a waitlist offered-place outbox row. */
final class EventWaitlistOfferEnvelope extends Model
{
    use HasTenantScope;

    protected $table = 'event_waitlist_offer_envelopes';

    protected $guarded = [];

    protected $hidden = [
        'tenant_id',
        'token_ciphertext',
        'claim_token_hash',
        'key_fingerprint',
        'aad_hash',
    ];

    protected $casts = [
        'queue_version' => 'integer',
        'envelope_version' => 'integer',
        'claimed_at' => 'datetime',
        'handed_off_at' => 'datetime',
        'erased_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(EventWaitlistEntry::class, 'waitlist_entry_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function access(): HasMany
    {
        return $this->hasMany(EventWaitlistOfferEnvelopeAccess::class, 'envelope_id');
    }
}
