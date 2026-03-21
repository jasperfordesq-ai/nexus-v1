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

class VolCustomField extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'vol_custom_fields';

    protected $fillable = [
        'tenant_id',
        'organization_id',
        'field_key',
        'field_label',
        'field_type',
        'applies_to',
        'is_required',
        'field_options',
        'display_order',
        'placeholder',
        'help_text',
        'is_active',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(VolOrganization::class, 'organization_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(VolCustomFieldValue::class, 'custom_field_id');
    }
}
