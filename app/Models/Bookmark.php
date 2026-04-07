<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use App\Models\Concerns\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bookmark extends Model
{
    use HasTenantScope;

    protected $table = 'bookmarks';

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'bookmarkable_type',
        'bookmarkable_id',
        'collection_id',
        'created_at',
    ];

    protected $hidden = ['tenant_id'];

    protected $casts = [
        'user_id' => 'integer',
        'bookmarkable_id' => 'integer',
        'collection_id' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(BookmarkCollection::class, 'collection_id');
    }
}
