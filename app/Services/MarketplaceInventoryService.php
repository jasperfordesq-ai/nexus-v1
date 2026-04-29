<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use App\I18n\LocaleContext;
use App\Models\MarketplaceListing;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * AG46 — Merchant inventory tracking.
 *
 * Atomic decrement on order; increment on cancel/refund; auto sold/active flip.
 * Low-stock notifications and restock fanout to saved-search watchers.
 */
class MarketplaceInventoryService
{
    /**
     * Decrement inventory for a listing during order placement.
     * Caller must be inside its own transaction OR rely on this method's
     * lockForUpdate (we acquire a row lock either way).
     *
     * @throws \DomainException OUT_OF_STOCK | INSUFFICIENT_STOCK
     */
    public static function decrementForOrder(int $listingId, int $quantity = 1): void
    {
        $listing = MarketplaceListing::query()
            ->where('id', $listingId)
            ->lockForUpdate()
            ->first();

        if (!$listing) {
            throw new \DomainException('LISTING_NOT_FOUND');
        }

        // NULL inventory_count = unlimited stock.
        if ($listing->inventory_count === null) {
            return;
        }

        $newCount = (int) $listing->inventory_count - max(1, $quantity);

        if ($newCount < 0 && (bool) $listing->is_oversold_protected) {
            throw new \DomainException('OUT_OF_STOCK');
        }

        $listing->inventory_count = $newCount;

        // Auto-sold-out at 0 — flip status to 'sold' (existing enum value).
        if ($newCount <= 0) {
            $listing->status = 'sold';
        }

        $listing->save();

        // Low-stock notification if (a) just hit threshold and (b) > 0.
        if (
            $newCount > 0
            && $listing->low_stock_threshold !== null
            && $newCount <= (int) $listing->low_stock_threshold
        ) {
            self::notifyLowStock($listing);
        }
    }

    /**
     * Increment inventory after an order is cancelled or refunded.
     */
    public static function incrementForCancellation(int $listingId, int $quantity = 1): void
    {
        $listing = MarketplaceListing::query()
            ->where('id', $listingId)
            ->lockForUpdate()
            ->first();

        if (!$listing || $listing->inventory_count === null) {
            return;
        }

        $listing->inventory_count = (int) $listing->inventory_count + max(1, $quantity);

        // Restock from sold → active automatically.
        if ($listing->status === 'sold' && $listing->inventory_count > 0) {
            $listing->status = 'active';
        }

        $listing->save();
    }

    /**
     * Seller manually updates inventory settings on a listing.
     * Triggers ListingRestocked event-style fanout if count goes from 0 → > 0.
     *
     * @param array{
     *   inventory_count?: int|null,
     *   low_stock_threshold?: int|null,
     *   is_oversold_protected?: bool,
     * } $data
     */
    public static function updateInventory(MarketplaceListing $listing, array $data): MarketplaceListing
    {
        $previousCount = $listing->inventory_count;

        if (array_key_exists('inventory_count', $data)) {
            $listing->inventory_count = $data['inventory_count'] === null
                ? null
                : max(0, (int) $data['inventory_count']);
        }
        if (array_key_exists('low_stock_threshold', $data)) {
            $listing->low_stock_threshold = $data['low_stock_threshold'] === null
                ? null
                : max(0, (int) $data['low_stock_threshold']);
        }
        if (array_key_exists('is_oversold_protected', $data)) {
            $listing->is_oversold_protected = (bool) $data['is_oversold_protected'];
        }

        // Auto status flip on manual restock from 0 → > 0.
        if (
            $listing->inventory_count !== null
            && $listing->inventory_count > 0
            && $listing->status === 'sold'
        ) {
            $listing->status = 'active';
        }

        $listing->save();

        // Fire restock fanout if count went 0 (or null/sold) → > 0.
        $wentFromZero = ($previousCount === null || (int) $previousCount === 0)
            && $listing->inventory_count !== null
            && $listing->inventory_count > 0;
        if ($wentFromZero) {
            self::fanoutRestock($listing);
        }

        return $listing->fresh();
    }

    /**
     * Notify the seller that stock is low.
     */
    private static function notifyLowStock(MarketplaceListing $listing): void
    {
        try {
            $seller = User::find($listing->user_id);
            if (!$seller) {
                return;
            }
            $title = $listing->title ?? '';
            LocaleContext::withLocale($seller, function () use ($listing, $seller, $title) {
                Notification::create([
                    'user_id' => $seller->id,
                    'message' => __('notifications.marketplace.low_stock', [
                        'title' => $title,
                        'count' => (int) $listing->inventory_count,
                    ]),
                    'link' => '/marketplace/' . $listing->id . '/edit',
                    'type' => 'marketplace_low_stock',
                    'created_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('[MarketplaceInventoryService] notifyLowStock failed: ' . $e->getMessage());
        }
    }

    /**
     * Notify saved-search watchers that a listing is back in stock.
     *
     * NOTE: MarketplaceSavedSearch tracks search filters, not per-listing
     * watchers. Persisted listing-level watchers are out of scope; for now
     * we run a simple keyword-match fanout against active saved searches
     * whose query/filters mention this listing's title or category.
     */
    private static function fanoutRestock(MarketplaceListing $listing): void
    {
        try {
            $tenantId = TenantContext::getId();

            $watchers = DB::table('marketplace_saved_searches')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->where(function ($q) use ($listing) {
                    if ($listing->title) {
                        $q->orWhere('search_query', 'LIKE', '%' . $listing->title . '%');
                    }
                    if ($listing->category_id) {
                        $q->orWhere('filters', 'LIKE', '%"category_id":' . (int) $listing->category_id . '%');
                    }
                })
                ->limit(200)
                ->get();

            foreach ($watchers as $w) {
                $user = User::find($w->user_id);
                if (!$user) {
                    continue;
                }
                LocaleContext::withLocale($user, function () use ($user, $listing) {
                    Notification::create([
                        'user_id' => $user->id,
                        'message' => __('notifications.marketplace.restocked', [
                            'title' => $listing->title ?? '',
                        ]),
                        'link' => '/marketplace/' . $listing->id,
                        'type' => 'marketplace_restocked',
                        'created_at' => now(),
                    ]);
                });
            }
        } catch (\Throwable $e) {
            Log::warning('[MarketplaceInventoryService] fanoutRestock failed: ' . $e->getMessage());
        }
    }
}
