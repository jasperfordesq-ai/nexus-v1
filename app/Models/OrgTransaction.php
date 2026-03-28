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

class OrgTransaction extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'org_transactions';

    const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'transfer_request_id',
        'sender_type', 'sender_id', 'receiver_type', 'receiver_id',
        'amount', 'description',
    ];

    protected $casts = [
        'organization_id' => 'integer',
        'transfer_request_id' => 'integer',
        'sender_id' => 'integer',
        'receiver_id' => 'integer',
        'amount' => 'decimal:2',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(VolOrganization::class, 'organization_id');
    }

    public function transferRequest(): BelongsTo
    {
        return $this->belongsTo(OrgTransferRequest::class, 'transfer_request_id');
    }
}
