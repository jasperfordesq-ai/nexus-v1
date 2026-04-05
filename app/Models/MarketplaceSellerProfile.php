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
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketplaceSellerProfile extends Model
{
    use HasFactory;
    use HasTenantScope;

    protected $table = 'marketplace_seller_profiles';

    protected $fillable = [
        'user_id',
        'display_name',
        'bio',
        'cover_image_url',
        'avatar_url',
        'seller_type',
        'business_name',
        'business_registration',
        'vat_number',
        'business_address',
        'business_verified',
        'stripe_account_id',
        'stripe_onboarding_complete',
        'response_time_avg',
        'response_rate',
        'total_sales',
        'total_revenue',
        'avg_rating',
        'total_ratings',
        'community_trust_score',
        'is_community_endorsed',
        'joined_marketplace_at',
    ];

    /**
     * Attributes hidden from JSON serialization to prevent data leakage.
     */
    protected $hidden = ['tenant_id', 'stripe_account_id'];

    protected $casts = [
        'business_address' => 'array',
        'business_verified' => 'boolean',
        'stripe_onboarding_complete' => 'boolean',
        'is_community_endorsed' => 'boolean',
        'joined_marketplace_at' => 'datetime',
        'avg_rating' => 'float',
        'community_trust_score' => 'float',
        'response_rate' => 'float',
        'total_sales' => 'integer',
        'total_revenue' => 'decimal:2',
        'total_ratings' => 'integer',
        'response_time_avg' => 'integer',
    ];

    // ---------------------------------------------------------------
    //  Relationships
    // ---------------------------------------------------------------

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all marketplace listings for this seller (through user_id).
     */
    public function listings(): HasMany
    {
        return $this->hasMany(MarketplaceListing::class, 'user_id', 'user_id');
    }
}
