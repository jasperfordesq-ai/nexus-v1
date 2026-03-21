<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoRedirect extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'seo_redirects';

    protected $fillable = [
        'tenant_id',
        'source_url',
        'destination_url',
        'hits',
    ];

    protected $casts = [
        'hits' => 'integer',
    ];
}
