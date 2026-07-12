<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Durable claim record for an optional attendance-related ledger effect. */
final class EventAttendanceCreditClaim extends Model
{
    use HasTenantScope;

    protected $table = 'event_attendance_credit_claims';

    protected $fillable = [
        'tenant_id',
        'event_id',
        'attendance_id',
        'user_id',
        'claim_type',
        'idempotency_key',
        'funding_source_type',
        'funding_source_id',
        'payer_user_id',
        'payee_user_id',
        'amount',
        'unit',
        'status',
        'transaction_id',
        'parent_claim_id',
        'failure_code',
        'reversal_code',
        'metadata',
        'claimed_at',
        'completed_at',
        'failed_at',
        'reversed_at',
    ];

    protected $hidden = [
        'tenant_id',
        'idempotency_key',
        'failure_code',
        'reversal_code',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'claimed_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Event attendance credit claims cannot be deleted.');
        });
    }
}
