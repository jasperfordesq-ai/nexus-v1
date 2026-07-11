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

/**
 * A community's metadata-only safeguarding decision for one member and purpose.
 *
 * This model intentionally has no certificate, reference, result, date-of-issue,
 * expiry, document, identity, or free-text evidence fields.
 */
class MemberVettingAttestation extends Model
{
    use HasTenantScope;

    public const DECISION_CONFIRMED = 'confirmed';
    public const DECISION_REVOKED = 'revoked';

    protected $table = 'member_vetting_attestations';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'scheme_code',
        'attestation_code',
        'purpose_code',
        'scope_type',
        'scope_identifier',
        'decision',
        'confirmed_by',
        'confirmed_at',
        'revoked_by',
        'revoked_at',
        'revocation_reason_code',
        'policy_version',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'user_id' => 'integer',
        'confirmed_by' => 'integer',
        'confirmed_at' => 'datetime',
        'revoked_by' => 'integer',
        'revoked_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MemberVettingAttestationEvent::class, 'attestation_id');
    }
}
