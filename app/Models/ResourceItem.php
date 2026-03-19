<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceItem extends Model
{
    use HasTenantScope;

    protected $table = 'resources';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'title',
        'description',
        'file_path',
        'file_type',
        'file_size',
        'category_id',
        'downloads',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'downloads' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
