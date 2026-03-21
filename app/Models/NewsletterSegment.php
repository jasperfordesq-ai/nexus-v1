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

class NewsletterSegment extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'newsletter_segments';

    protected $fillable = [
        'tenant_id', 'name', 'description', 'rules', 'is_active',
        'created_by',
    ];

    protected $casts = [
        'rules' => 'array',
        'is_active' => 'boolean',
        'created_by' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
