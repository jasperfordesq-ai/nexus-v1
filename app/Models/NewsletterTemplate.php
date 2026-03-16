<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterTemplate extends Model
{
    use HasTenantScope;

    protected $table = 'newsletter_templates';

    protected $fillable = [
        'tenant_id', 'name', 'description', 'category', 'subject',
        'preview_text', 'content', 'thumbnail', 'is_active',
        'use_count', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'use_count' => 'integer',
        'created_by' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
