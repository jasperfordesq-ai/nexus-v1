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
 * A community's safeguarding decision for one member and purpose.
 *
 * Certificate evidence, reference numbers, results and documents remain outside
 * NEXUS. A decision may include encrypted operational scope/private notes and
 * the minimum dates needed to expire and renew the community confirmation.
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
        'certification_codes',
        'purpose_code',
        'scope_type',
        'scope_identifier',
        'scope_summary_encrypted',
        'private_notes_encrypted',
        'review_due_at',
        'authority_expires_at',
        'renewal_reminder_90_sent_at',
        'renewal_reminder_30_sent_at',
        'renewal_reminder_7_sent_at',
        'renewal_due_notified_at',
        'expiry_notified_at',
        'decision',
        'confirmed_by',
        'confirmed_at',
        'revoked_by',
        'revoked_at',
        'revocation_reason_code',
        'policy_version',
    ];

    protected $hidden = [
        'scope_summary_encrypted',
        'private_notes_encrypted',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'user_id' => 'integer',
        'certification_codes' => 'array',
        'review_due_at' => 'date',
        'authority_expires_at' => 'date',
        'renewal_reminder_90_sent_at' => 'datetime',
        'renewal_reminder_30_sent_at' => 'datetime',
        'renewal_reminder_7_sent_at' => 'datetime',
        'renewal_due_notified_at' => 'datetime',
        'expiry_notified_at' => 'datetime',
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
