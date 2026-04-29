<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appreciation extends Model
{
    protected $table = 'appreciations';

    protected $fillable = [
        'sender_id', 'receiver_id', 'tenant_id',
        'message', 'context_type', 'context_id',
        'is_public', 'reactions_count',
    ];

    protected $casts = [
        'is_public' => 'bool',
        'reactions_count' => 'int',
    ];

    public function reactions(): HasMany
    {
        return $this->hasMany(AppreciationReaction::class, 'appreciation_id');
    }
}
