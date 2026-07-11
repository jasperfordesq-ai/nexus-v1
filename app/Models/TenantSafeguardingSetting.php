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

class TenantSafeguardingSetting extends Model
{
    use HasTenantScope;

    protected $table = 'tenant_safeguarding_settings';
    protected $primaryKey = 'tenant_id';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'jurisdiction',
        'policy_version',
        'configured_by',
        'configured_at',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'configured_by' => 'integer',
        'configured_at' => 'datetime',
    ];

    public function configurer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by');
    }
}
