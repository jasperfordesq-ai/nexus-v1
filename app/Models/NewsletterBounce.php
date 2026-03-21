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

class NewsletterBounce extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'newsletter_bounces';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'email', 'newsletter_id', 'queue_id',
        'bounce_type', 'bounce_reason', 'bounce_code', 'bounced_at',
    ];

    protected $casts = [
        'newsletter_id' => 'integer',
        'queue_id' => 'integer',
        'bounced_at' => 'datetime',
    ];

    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }
}
