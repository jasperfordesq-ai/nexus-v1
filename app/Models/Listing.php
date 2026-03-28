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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Listing extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'listings';

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'description',
        'location',
        'latitude',
        'longitude',
        'type',
        'status',
        'image_url',
        'sdg_goals',
        'price',
        'subcategory_id',
        'federated_visibility',
        'service_type',
        'direct_messaging_disabled',
        'exchange_workflow_required',
        'hours_estimate',
        'renewed_at',
        'renewal_count',
        'view_count',
        'contact_count',
        'save_count',
        'is_featured',
        'featured_until',
    ];

    protected $casts = [
        'sdg_goals' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'price' => 'decimal:2',
        'hours_estimate' => 'decimal:2',
        'direct_messaging_disabled' => 'boolean',
        'exchange_workflow_required' => 'boolean',
        'is_featured' => 'boolean',
        'renewed_at' => 'datetime',
        'featured_until' => 'datetime',
        'reviewed_at' => 'datetime',
        'view_count' => 'integer',
        'contact_count' => 'integer',
        'save_count' => 'integer',
        'renewal_count' => 'integer',
    ];

    // ---------------------------------------------------------------
    //  Relationships
    // ---------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_saved_listings');
    }

    public function skillTags(): HasMany
    {
        return $this->hasMany(ListingSkillTag::class);
    }

    // ---------------------------------------------------------------
    //  Scopes
    // ---------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true)
                      ->where('featured_until', '>', now());
    }
}
