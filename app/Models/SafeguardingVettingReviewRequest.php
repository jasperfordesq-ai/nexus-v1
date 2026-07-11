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

class SafeguardingVettingReviewRequest extends Model
{
    use HasTenantScope;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const SOURCE_MEMBER_REQUEST = 'member_request';
    public const SOURCE_LEGACY_MIGRATION = 'legacy_migration';

    protected $table = 'safeguarding_vetting_review_requests';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'jurisdiction',
        'scheme_code',
        'attestation_code',
        'purpose_code',
        'scope_type',
        'scope_identifier',
        'policy_version',
        'status',
        'request_source',
        'requested_by',
        'requested_at',
        'handled_by',
        'handled_at',
        'resolution_code',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'user_id' => 'integer',
        'requested_by' => 'integer',
        'requested_at' => 'datetime',
        'handled_by' => 'integer',
        'handled_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
