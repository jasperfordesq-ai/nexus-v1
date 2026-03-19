<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;

class NewsletterAnalytics extends Model
{
    use HasTenantScope;

    protected $table = 'newsletter_engagement_patterns';

    protected $fillable = [
        'tenant_id', 'email', 'opens_by_hour', 'clicks_by_hour',
        'total_opens', 'total_clicks', 'best_hour', 'last_updated',
    ];

    protected $casts = [
        'opens_by_hour' => 'array',
        'clicks_by_hour' => 'array',
        'total_opens' => 'integer',
        'total_clicks' => 'integer',
        'best_hour' => 'integer',
        'last_updated' => 'datetime',
    ];
}
