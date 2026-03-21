<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdeaMedia extends Model
{
    use HasTenantScope;

    protected $table = 'idea_media';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'idea_id', 'media_type', 'url', 'caption', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function idea(): BelongsTo
    {
        return $this->belongsTo(ChallengeIdea::class, 'idea_id');
    }
}
