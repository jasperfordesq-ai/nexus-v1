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

/** Immutable audit evidence for offer-envelope access and erasure. */
final class EventWaitlistOfferEnvelopeAccess extends Model
{
    use HasTenantScope;

    public $timestamps = false;

    protected $table = 'event_waitlist_offer_envelope_access';

    protected $guarded = [];

    protected $hidden = ['tenant_id', 'claim_id_hash', 'idempotency_key'];

    protected $casts = [
        'queue_version' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function envelope(): BelongsTo
    {
        return $this->belongsTo(EventWaitlistOfferEnvelope::class, 'envelope_id');
    }
}
