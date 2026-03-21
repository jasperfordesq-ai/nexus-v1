<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrgTransferRequest extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'org_transfer_requests';

    protected $fillable = [
        'tenant_id', 'organization_id', 'requester_id', 'recipient_id',
        'amount', 'description', 'status', 'approved_by', 'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'requester_id' => 'integer',
        'recipient_id' => 'integer',
        'approved_by' => 'integer',
        'amount' => 'float',
        'approved_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(VolOrganization::class, 'organization_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(OrgTransaction::class, 'transfer_request_id');
    }
}
