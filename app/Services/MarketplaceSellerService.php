<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceSellerProfile;
use Illuminate\Support\Facades\DB;

/**
 * MarketplaceSellerService — Seller profile management for the marketplace module.
 */
class MarketplaceSellerService
{
    /**
     * Get or create a seller profile for a user.
     */
    public static function getOrCreateProfile(int $userId): MarketplaceSellerProfile
    {
        return MarketplaceSellerProfile::firstOrCreate(
            [
                'tenant_id' => TenantContext::getId(),
                'user_id' => $userId,
            ],
            [
                'joined_marketplace_at' => now(),
            ]
        );
    }

    /**
     * Get a seller profile by user ID.
     */
    public static function getByUserId(int $userId): ?MarketplaceSellerProfile
    {
        return MarketplaceSellerProfile::where('user_id', $userId)->first();
    }

    /**
     * Get a seller profile by ID.
     */
    public static function getById(int $id): ?MarketplaceSellerProfile
    {
        return MarketplaceSellerProfile::find($id);
    }

    /**
     * Update a seller profile.
     */
    public static function update(MarketplaceSellerProfile $profile, array $data): MarketplaceSellerProfile
    {
        $fillable = [
            'display_name', 'bio', 'cover_image_url', 'avatar_url',
            'seller_type', 'business_name', 'business_registration',
            'vat_number', 'business_address',
        ];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $data)) {
                $profile->{$field} = $data[$field];
            }
        }

        $profile->save();
        return $profile;
    }

    /**
     * Get the public seller profile view (for buyer-facing pages).
     */
    public static function getPublicProfile(int $sellerId): ?array
    {
        $profile = MarketplaceSellerProfile::with('user:id,first_name,last_name,avatar_url,is_verified,created_at')
            ->find($sellerId);

        if (!$profile) {
            return null;
        }

        $listingCount = MarketplaceListing::where('user_id', $profile->user_id)
            ->where('status', 'active')
            ->where('moderation_status', 'approved')
            ->count();

        return [
            'id' => $profile->id,
            'user_id' => $profile->user_id,
            'display_name' => $profile->display_name ?? trim(
                ($profile->user->first_name ?? '') . ' ' . ($profile->user->last_name ?? '')
            ),
            'bio' => $profile->bio,
            'avatar_url' => $profile->avatar_url ?? $profile->user->avatar_url ?? null,
            'cover_image_url' => $profile->cover_image_url,
            'seller_type' => $profile->seller_type,
            'business_name' => $profile->seller_type === 'business' ? $profile->business_name : null,
            'business_verified' => $profile->business_verified,
            'is_community_endorsed' => $profile->is_community_endorsed,
            'community_trust_score' => $profile->community_trust_score,
            'avg_rating' => $profile->avg_rating,
            'total_ratings' => $profile->total_ratings,
            'total_sales' => $profile->total_sales,
            'response_time_avg' => $profile->response_time_avg,
            'response_rate' => $profile->response_rate,
            'active_listings' => $listingCount,
            'member_since' => $profile->user->created_at?->toISOString(),
            'joined_marketplace_at' => $profile->joined_marketplace_at?->toISOString(),
        ];
    }

    /**
     * Get seller's listings (public view).
     */
    public static function getSellerListings(int $userId, int $limit = 20, ?string $cursor = null): array
    {
        return MarketplaceListingService::getAll([
            'user_id' => $userId,
            'limit' => $limit,
            'cursor' => $cursor,
        ]);
    }

    /**
     * Get seller dashboard stats.
     */
    public static function getDashboardStats(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $activeListings = MarketplaceListing::where('user_id', $userId)
            ->where('status', 'active')
            ->where('moderation_status', 'approved')
            ->count();

        $totalListings = MarketplaceListing::where('user_id', $userId)->count();

        $totalViews = MarketplaceListing::where('user_id', $userId)
            ->sum('views_count');

        $totalSaves = MarketplaceListing::where('user_id', $userId)
            ->sum('saves_count');

        $pendingOffers = DB::table('marketplace_offers')
            ->where('seller_id', $userId)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->count();

        $soldListings = MarketplaceListing::where('user_id', $userId)
            ->where('status', 'sold')
            ->count();

        return [
            'active_listings' => $activeListings,
            'total_listings' => $totalListings,
            'sold_listings' => $soldListings,
            'total_views' => (int) $totalViews,
            'total_saves' => (int) $totalSaves,
            'pending_offers' => $pendingOffers,
        ];
    }

    /**
     * Refresh cached stats on the seller profile.
     */
    public static function refreshCachedStats(MarketplaceSellerProfile $profile): void
    {
        $profile->total_sales = MarketplaceListing::where('user_id', $profile->user_id)
            ->where('status', 'sold')
            ->count();

        $profile->save();
    }
}
