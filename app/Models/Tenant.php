<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $table = 'tenants';

    protected $fillable = [
        'name', 'slug', 'domain', 'configuration', 'path', 'depth',
        'parent_id', 'allows_subtenants', 'is_active',
    ];

    protected $casts = [
        'configuration' => 'array',
        'depth' => 'integer',
        'parent_id' => 'integer',
        'allows_subtenants' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Return direct children of a tenant as a plain array. */
    public static function getChildren(int $tenantId): array
    {
        return static::where('parent_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_active', 'depth'])
            ->toArray();
    }

    /**
     * Return ancestor chain from root → tenant as a plain array.
     * Each entry has: id, name, slug.
     */
    public static function getBreadcrumb(int $tenantId): array
    {
        $crumbs = [];
        $current = static::find($tenantId, ['id', 'name', 'slug', 'parent_id']);

        while ($current) {
            array_unshift($crumbs, [
                'id'   => $current->id,
                'name' => $current->name,
                'slug' => $current->slug,
            ]);
            $current = $current->parent_id
                ? static::find($current->parent_id, ['id', 'name', 'slug', 'parent_id'])
                : null;
        }

        return $crumbs;
    }
}
