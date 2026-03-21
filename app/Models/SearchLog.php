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

class SearchLog extends Model
{
    use HasFactory, HasTenantScope;

    protected $table = 'search_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'query',
        'search_type',
        'result_count',
        'filters',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'result_count' => 'integer',
        'filters' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
