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

class NewsletterSubscriber extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'newsletter_subscribers';

    protected $fillable = [
        'tenant_id', 'email', 'first_name', 'last_name', 'user_id',
        'source', 'status', 'confirmation_token', 'unsubscribe_token',
        'confirmed_at', 'unsubscribed_at', 'unsubscribe_reason',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
