<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HelpArticle extends Model
{
    use HasFactory;

    protected $table = 'help_articles';

    protected $fillable = [
        'title', 'slug', 'content', 'module_tag', 'is_public',
        'view_count',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'view_count' => 'integer',
    ];

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeForModules(Builder $query, array $modules): Builder
    {
        return $query->whereIn('module_tag', array_unique(array_merge($modules, ['core', 'getting_started'])));
    }
}
