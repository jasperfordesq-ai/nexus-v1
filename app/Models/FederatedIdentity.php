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
 * NOT tenant-scoped: federation is cross-tenant by design. Tenant isolation is
 * enforced by the local_user → users.tenant_id relationship.
 */
class FederatedIdentity extends Model
{
    use HasFactory;

    protected $table = 'federated_identities';

    protected $fillable = [
        'local_user_id',
        'partner_id',
        'external_user_id',
        'external_handle',
        'attestation_signature',
        'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'local_user_id');
    }

    /**
     * The federation_external_partners row this identity belongs to.
     *
     * Note: there is currently no FederationPartner Eloquent model — the
     * federation code accesses `federation_external_partners` via the DB
     * facade. We still expose a relationship for future refactors; callers
     * that need the partner row today should query via DB facade.
     */
    public function partner(): BelongsTo
    {
        // Lazy: use a generic Model pointed at federation_external_partners.
        return $this->belongsTo(Model::class, 'partner_id');
    }
}
