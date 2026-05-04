<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FederatedIdentity — maps a local User to an identity on a federation partner.
 *
 * Tenant-scoped by the local user's tenant so partner identity IDs cannot
 * collide across communities.
 */
class FederatedIdentity extends Model
{
    use HasFactory;

    protected $table = 'federated_identities';

    protected $fillable = [
        'tenant_id',
        'local_user_id',
        'partner_id',
        'external_user_id',
        'external_handle',
        'attestation_signature',
        'verified_at',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'local_user_id');
    }

    /**
     * The federation_external_partners row this identity belongs to.
     *
     * The relation targets the federation_external_partners table via
     * the FederationExternalPartner Eloquent model.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(FederationExternalPartner::class, 'partner_id');
    }
}
