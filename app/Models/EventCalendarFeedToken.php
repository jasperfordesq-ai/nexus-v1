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

final class EventCalendarFeedToken extends Model
{
    use HasTenantScope;

    protected $table = 'event_calendar_feed_tokens';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'token_hash',
        'token_prefix',
        'label',
        'locale',
        'last_used_at',
        'revoked_at',
    ];

    protected $hidden = ['tenant_id', 'token_hash'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
