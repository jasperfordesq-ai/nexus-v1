<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FederationExternalPartner extends Model
{
    use HasFactory;

    protected $table = 'federation_external_partners';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'base_url',
        'api_path',
        'status',
        'created_by',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
    ];

    public function identities(): HasMany
    {
        return $this->hasMany(FederatedIdentity::class, 'partner_id');
    }
}
