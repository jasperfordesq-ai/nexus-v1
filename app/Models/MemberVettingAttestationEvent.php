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

/**
 * Append-only metadata history for safeguarding attestation decisions.
 */
class MemberVettingAttestationEvent extends Model
{
    use HasTenantScope;

    public $timestamps = false;

    protected $table = 'member_vetting_attestation_events';

    protected $fillable = [
        'attestation_id',
        'tenant_id',
        'user_id',
        'scheme_code',
        'attestation_code',
        'purpose_code',
        'scope_type',
        'scope_identifier',
        'event_type',
        'decision_before',
        'decision_after',
        'reason_code',
        'actor_user_id',
        'policy_version',
        'created_at',
    ];

    protected $casts = [
        'attestation_id' => 'integer',
        'tenant_id' => 'integer',
        'user_id' => 'integer',
        'actor_user_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function attestation(): BelongsTo
    {
        return $this->belongsTo(MemberVettingAttestation::class, 'attestation_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Safeguarding attestation events are append-only.');
        });

        static::deleting(static function (): never {
            throw new \LogicException('Safeguarding attestation events are append-only.');
        });
    }
}
