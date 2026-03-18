<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListingSkillTag extends Model
{
    use HasTenantScope;

    protected $table = 'listing_skill_tags';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'listing_id',
        'tag',
    ];

    protected $casts = [
        'listing_id' => 'integer',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
