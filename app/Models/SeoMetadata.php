<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoMetadata extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'seo_metadata';

    protected $fillable = [
        'tenant_id',
        'entity_type',
        'entity_id',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'og_image_url',
        'noindex',
    ];

    protected $casts = [
        'noindex' => 'boolean',
    ];
}
