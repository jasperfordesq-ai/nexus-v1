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

class VolCustomFieldValue extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'vol_custom_field_values';

    protected $fillable = [
        'tenant_id',
        'custom_field_id',
        'entity_type',
        'entity_id',
        'field_value',
    ];

    public function customField(): BelongsTo
    {
        return $this->belongsTo(VolCustomField::class, 'custom_field_id');
    }
}
