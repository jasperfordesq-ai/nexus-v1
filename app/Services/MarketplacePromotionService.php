<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use App\Models\MarketplacePromotion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MarketplacePromotionService — Paid promotions for marketplace listings.
 *
 * Promotion types: bump, featured, top_of_category, homepage_carousel.
 * Prices come from MarketplaceConfigurationService.
 */
class MarketplacePromotionService
{
    // ─────────────────────────────────────────────────────────────────
    //  Product catalogue
    // ─────────────────────────────────────────────────────────────────

    /**
     * Promotion type definitions with durations (hours).
     */
    private const PROMOTION_TYPES = [
        'bump' => [
            'label' => 'Bump to Top',
            'description' => 'Move your listing to the top of search results for 24 hours.',
            'duration_hours' => 24,
            'config_key' => MarketplaceConfigurationService::CONFIG_BUMP_PRICE,
        ],
        'featured' => [
            'label' => 'Featured Listing',
            'description' => 'Highlighted badge and priority placement for 7 days.',
            'duration_hours' => 168, // 7 days
            'config_key' => MarketplaceConfigurationService::CONFIG_FEATURED_PRICE,
        ],
        'top_of_category' => [
            'label' => 'Top of Category',
            'description' => 'Pin your listing at the top of its category for 3 days.',
            'duration_hours' => 72, // 3 days
            'config_key' => null, // uses featured price * 1.5
        ],
        'homepage_carousel' => [
            'label' => 'Homepage Carousel',
            'description' => 'Displayed in the marketplace homepage carousel for 7 days.',
            'duration_hours' => 168, // 7 days
            'config_key' => null, // uses featured price * 2
        ],
    ];

    /**
     * Get available promotion products with pricing.
     *
     * @return array<string, array{type: string, label: string, description: string, price: float, currency: string, duration_hours: int}>
     */
    public static function getProducts(): array
    {
        $bumpPrice = (float) MarketplaceConfigurationService::get(
            MarketplaceConfigurationService::CONFIG_BUMP_PRICE,
            5.00
        );
        $featuredPrice = (float) MarketplaceConfigurationService::get(
            MarketplaceConfigurationService::CONFIG_FEATURED_PRICE,
            10.00
        );

        $products = [];
        foreach (self::PROMOTION_TYPES as $type => $info) {
            $price = match ($type) {
                'bump' => $bumpPrice,
                'featured' => $featuredPrice,
                'top_of_category' => round($featuredPrice * 1.5, 2),
                'homepage_carousel' => round($featuredPrice * 2, 2),
            };

            $products[$type] = [
                'type' => $type,
                'label' => $info['label'],
                'description' => $info['description'],
                'price' => $price,
                'currency' => 'EUR',
                'duration_hours' => $info['duration_hours'],
            ];
        }

        return $products;
    }

    // ─────────────────────────────────────────────────────────────────
    //  CRUD
    // ─────────────────────────────────────────────────────────────────

    /**
     * Create a promotion for a listing.
     */
    public static function createPromotion(int $userId, int $listingId, string $type): MarketplacePromotion
    {
        $products = self::getProducts();

        if (!isset($products[$type])) {
            throw new \InvalidArgumentException("Unknown promotion type: {$type}");
        }

        $product = $products[$type];
        $durationHours = $product['duration_hours'];
        $price = $product['price'];

        // Deactivate any existing active promotion of the same type on this listing
        MarketplacePromotion::where('marketplace_listing_id', $listingId)
            ->where('promotion_type', $type)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $promotion = MarketplacePromotion::create([
            'marketplace_listing_id' => $listingId,
            'user_id' => $userId,
            'promotion_type' => $type,
            'amount_paid' => $price,
            'currency' => $product['currency'],
            'started_at' => now(),
            'expires_at' => now()->addHours($durationHours),
            'is_active' => true,
        ]);

        // Update promoted_until on the listing for backward-compatible queries
        $maxExpiry = MarketplacePromotion::where('marketplace_listing_id', $listingId)
            ->where('is_active', true)
            ->max('expires_at');

        if ($maxExpiry) {
            MarketplaceListing::where('id', $listingId)->update(['promoted_until' => $maxExpiry]);
        }

        return $promotion;
    }

    /**
     * Get active promotions for a user.
     *
     * @return MarketplacePromotion[]
     */
    public static function getActivePromotions(int $userId): array
    {
        return MarketplacePromotion::where('user_id', $userId)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->with('listing:id,title,status')
            ->orderBy('expires_at', 'asc')
            ->get()
            ->all();
    }

    /**
     * Get the active promotion for a specific listing.
     */
    public static function getActivePromotionForListing(int $listingId): ?MarketplacePromotion
    {
        return MarketplacePromotion::where('marketplace_listing_id', $listingId)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->orderBy('expires_at', 'desc')
            ->first();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Scheduled maintenance
    // ─────────────────────────────────────────────────────────────────

    /**
     * Deactivate expired promotions.
     * Called by scheduler (hourly).
     *
     * @return int Number of promotions deactivated.
     */
    public static function deactivateExpired(): int
    {
        $count = MarketplacePromotion::where('is_active', true)
            ->where('expires_at', '<=', now())
            ->update(['is_active' => false]);

        if ($count > 0) {
            Log::info("MarketplacePromotionService: deactivated {$count} expired promotions");

            // Clean up promoted_until on listings where all promotions expired
            DB::statement("
                UPDATE marketplace_listings ml
                SET ml.promoted_until = NULL
                WHERE ml.promoted_until IS NOT NULL
                  AND NOT EXISTS (
                    SELECT 1 FROM marketplace_promotions mp
                    WHERE mp.marketplace_listing_id = ml.id
                      AND mp.is_active = 1
                      AND mp.expires_at > NOW()
                  )
            ");
        }

        return $count;
    }
}
