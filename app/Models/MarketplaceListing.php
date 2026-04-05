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
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceListing extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'marketplace_listings';

    protected $fillable = [
        'user_id',
        'category_id',
        'title',
        'tagline',
        'description',
        'condition',
        'price',
        'price_currency',
        'price_type',
        'time_credit_price',
        'quantity',
        'template_data',
        'location',
        'latitude',
        'longitude',
        'shipping_available',
        'local_pickup',
        'delivery_method',
        'seller_type',
        'status',
        'marketplace_status',
        'moderation_status',
        'moderation_notes',
        'moderated_by',
        'moderated_at',
        'promotion_type',
        'promoted_until',
        'expires_at',
        'renewed_at',
        'renewal_count',
        'video_url',
    ];

    /**
     * Attributes hidden from JSON serialization to prevent data leakage.
     */
    protected $hidden = ['tenant_id'];

    protected $casts = [
        'template_data' => 'array',
        'shipping_available' => 'boolean',
        'local_pickup' => 'boolean',
        'price' => 'decimal:2',
        'time_credit_price' => 'decimal:2',
        'latitude' => 'float',
        'longitude' => 'float',
        'moderated_at' => 'datetime',
        'promoted_until' => 'datetime',
        'expires_at' => 'datetime',
        'renewed_at' => 'datetime',
        'renewal_count' => 'integer',
        'quantity' => 'integer',
        'views_count' => 'integer',
        'saves_count' => 'integer',
        'contacts_count' => 'integer',
        'moderated_by' => 'integer',
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
        return $this->belongsTo(MarketplaceCategory::class, 'category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(MarketplaceImage::class)->orderBy('sort_order');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(MarketplaceOffer::class);
    }

    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'marketplace_saved_listings');
    }

    public function sellerProfile(): HasOneThrough
    {
        return $this->hasOneThrough(
            MarketplaceSellerProfile::class,
            User::class,
            'id',           // users.id
            'user_id',      // marketplace_seller_profiles.user_id
            'user_id',      // marketplace_listings.user_id
            'id'            // users.id
        );
    }

    // ---------------------------------------------------------------
    //  Scopes
    // ---------------------------------------------------------------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
                     ->where('moderation_status', 'approved');
    }

    public function scopeFree(Builder $query): Builder
    {
        return $query->where('price_type', 'free');
    }

    /**
     * Filter listings within a given radius (km) of a coordinate using the haversine formula.
     */
    public function scopeNearby(Builder $query, float $lat, float $lng, float $radiusKm): Builder
    {
        $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";

        return $query->whereNotNull('latitude')
                     ->whereNotNull('longitude')
                     ->whereRaw("{$haversine} <= ?", [$lat, $lng, $lat, $radiusKm])
                     ->orderByRaw("{$haversine} ASC", [$lat, $lng, $lat]);
    }
}
