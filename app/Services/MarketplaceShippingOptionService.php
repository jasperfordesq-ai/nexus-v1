<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\MarketplaceShippingOption;

/**
 * MarketplaceShippingOptionService — Seller shipping option management (MKT31).
 *
 * Manages shipping options for marketplace sellers, including CRUD operations
 * and default option management.
 */
class MarketplaceShippingOptionService
{
    /**
     * Get all shipping options for a seller.
     *
     * @param int $sellerId The seller profile ID
     * @return array
     */
    public static function getSellerOptions(int $sellerId): array
    {
        $options = MarketplaceShippingOption::where('seller_id', $sellerId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('courier_name')
            ->get();

        return $options->map(fn ($o) => self::formatOption($o))->all();
    }

    /**
     * Create a new shipping option for a seller.
     *
     * @param int   $sellerId The seller profile ID
     * @param array $data     Option attributes
     * @return MarketplaceShippingOption
     */
    public static function createOption(int $sellerId, array $data): MarketplaceShippingOption
    {
        $option = new MarketplaceShippingOption();
        $option->tenant_id = TenantContext::getId();
        $option->seller_id = $sellerId;
        $option->courier_name = $data['courier_name'];
        $option->courier_code = $data['courier_code'] ?? null;
        $option->price = $data['price'];
        $option->currency = $data['currency'] ?? 'EUR';
        $option->estimated_days = $data['estimated_days'] ?? null;
        $option->is_default = $data['is_default'] ?? false;
        $option->is_active = true;
        $option->save();

        // If this is set as default, unset other defaults
        if ($option->is_default) {
            self::clearOtherDefaults($sellerId, $option->id);
        }

        return $option;
    }

    /**
     * Update an existing shipping option.
     *
     * @param MarketplaceShippingOption $option The option to update
     * @param array                     $data   New attribute values
     * @return MarketplaceShippingOption
     */
    public static function updateOption(MarketplaceShippingOption $option, array $data): MarketplaceShippingOption
    {
        $fillable = ['courier_name', 'courier_code', 'price', 'currency', 'estimated_days', 'is_default', 'is_active'];

        foreach ($fillable as $field) {
            if (array_key_exists($field, $data)) {
                $option->{$field} = $data[$field];
            }
        }

        $option->save();

        // If this was set as default, unset other defaults
        if ($option->is_default) {
            self::clearOtherDefaults($option->seller_id, $option->id);
        }

        return $option;
    }

    /**
     * Delete a shipping option (soft-delete by deactivating).
     *
     * @param int $optionId The option ID to delete
     * @param int $sellerId The seller profile ID (ownership check)
     * @return void
     *
     * @throws \InvalidArgumentException if option not found or not owned by seller
     */
    public static function deleteOption(int $optionId, int $sellerId): void
    {
        $option = MarketplaceShippingOption::where('id', $optionId)
            ->where('seller_id', $sellerId)
            ->first();

        if (!$option) {
            throw new \InvalidArgumentException('Shipping option not found.');
        }

        $option->is_active = false;
        $option->save();
    }

    /**
     * Set a specific option as the default for a seller.
     *
     * @param int $optionId The option ID to make default
     * @param int $sellerId The seller profile ID (ownership check)
     * @return void
     *
     * @throws \InvalidArgumentException if option not found or not owned by seller
     */
    public static function setDefault(int $optionId, int $sellerId): void
    {
        $option = MarketplaceShippingOption::where('id', $optionId)
            ->where('seller_id', $sellerId)
            ->first();

        if (!$option) {
            throw new \InvalidArgumentException('Shipping option not found.');
        }

        // Unset all other defaults for this seller
        self::clearOtherDefaults($sellerId, $optionId);

        $option->is_default = true;
        $option->save();
    }

    // -----------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------

    /**
     * Format a shipping option for API response.
     */
    private static function formatOption(MarketplaceShippingOption $option): array
    {
        return [
            'id' => $option->id,
            'courier_name' => $option->courier_name,
            'courier_code' => $option->courier_code,
            'price' => $option->price,
            'currency' => $option->currency,
            'estimated_days' => $option->estimated_days,
            'is_default' => $option->is_default,
            'is_active' => $option->is_active,
            'created_at' => $option->created_at?->toISOString(),
            'updated_at' => $option->updated_at?->toISOString(),
        ];
    }

    /**
     * Unset the default flag on all options for a seller except the specified one.
     */
    private static function clearOtherDefaults(int $sellerId, int $exceptOptionId): void
    {
        MarketplaceShippingOption::where('seller_id', $sellerId)
            ->where('id', '!=', $exceptOptionId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
