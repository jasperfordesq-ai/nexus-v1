<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;

class AppreciationReaction extends Model
{
    protected $table = 'appreciation_reactions';

    public $timestamps = false;

    protected $fillable = [
        'appreciation_id', 'user_id', 'reaction_type',
        'tenant_id', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
