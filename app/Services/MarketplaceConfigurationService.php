<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * MarketplaceConfigurationService — typed config for the marketplace module.
 *
 * Stores tenant-scoped marketplace configuration in the tenant_settings table
 * with a 'marketplace.' prefix. Follows the same pattern as JobConfigurationService.
 */
class MarketplaceConfigurationService
{
    // =========================================================================
    // Config keys
    // =========================================================================

    public const CONFIG_ENABLED = 'marketplace.enabled';
    public const CONFIG_ALLOW_SHIPPING = 'marketplace.allow_shipping';
    public const CONFIG_ALLOW_FREE_ITEMS = 'marketplace.allow_free_items';
    public const CONFIG_ALLOW_BUSINESS_SELLERS = 'marketplace.allow_business_sellers';
    public const CONFIG_ALLOW_HYBRID_PRICING = 'marketplace.allow_hybrid_pricing';
    public const CONFIG_ALLOW_COMMUNITY_DELIVERY = 'marketplace.allow_community_delivery';

    public const CONFIG_STRIPE_ENABLED = 'marketplace.stripe_enabled';
    public const CONFIG_ESCROW_ENABLED = 'marketplace.escrow_enabled';
    public const CONFIG_PLATFORM_FEE_PERCENT = 'marketplace.platform_fee_percent';
    public const CONFIG_ESCROW_AUTO_RELEASE_DAYS = 'marketplace.escrow_auto_release_days';

    public const CONFIG_MODERATION_ENABLED = 'marketplace.moderation_enabled';
    public const CONFIG_DSA_COMPLIANCE = 'marketplace.dsa_compliance';
    public const CONFIG_AUTO_APPROVE_TRUSTED = 'marketplace.auto_approve_trusted';

    public const CONFIG_PROMOTIONS_ENABLED = 'marketplace.promotions_enabled';
    public const CONFIG_BUMP_PRICE = 'marketplace.bump_price';
    public const CONFIG_FEATURED_PRICE = 'marketplace.featured_price';

    public const CONFIG_MAX_IMAGES = 'marketplace.max_images';
    public const CONFIG_MAX_ACTIVE_LISTINGS = 'marketplace.max_active_listings';
    public const CONFIG_LISTING_DURATION_DAYS = 'marketplace.listing_duration_days';

    // =========================================================================
    // Defaults
    // =========================================================================

    public const DEFAULTS = [
        self::CONFIG_ENABLED                   => false,
        self::CONFIG_ALLOW_SHIPPING            => false,
        self::CONFIG_ALLOW_FREE_ITEMS          => true,
        self::CONFIG_ALLOW_BUSINESS_SELLERS    => true,
        self::CONFIG_ALLOW_HYBRID_PRICING      => false,
        self::CONFIG_ALLOW_COMMUNITY_DELIVERY  => false,

        self::CONFIG_STRIPE_ENABLED            => false,
        self::CONFIG_ESCROW_ENABLED            => false,
        self::CONFIG_PLATFORM_FEE_PERCENT      => 5,
        self::CONFIG_ESCROW_AUTO_RELEASE_DAYS  => 14,

        self::CONFIG_MODERATION_ENABLED        => true,
        self::CONFIG_DSA_COMPLIANCE            => false,
        self::CONFIG_AUTO_APPROVE_TRUSTED      => false,

        self::CONFIG_PROMOTIONS_ENABLED        => false,
        self::CONFIG_BUMP_PRICE                => 5.00,
        self::CONFIG_FEATURED_PRICE            => 10.00,

        self::CONFIG_MAX_IMAGES                => 20,
        self::CONFIG_MAX_ACTIVE_LISTINGS       => 50,
        self::CONFIG_LISTING_DURATION_DAYS     => 30,
    ];

    /** Cache TTL in seconds (5 minutes). */
    private const CACHE_TTL = 300;

    // =========================================================================
    // Public API
    // =========================================================================

    /**
     * Get a single marketplace config value for the current tenant.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $tenantId = TenantContext::getId();
        $allStored = self::getStoredValues($tenantId);

        if (array_key_exists($key, $allStored)) {
            return $allStored[$key];
        }

        return $default ?? self::DEFAULTS[$key] ?? null;
    }

    /**
     * Set a marketplace config value for the current tenant.
     */
    public static function set(string $key, mixed $value): void
    {
        $tenantId = TenantContext::getId();
        $storedValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        $settingType = self::detectType($value);

        try {
            $existing = DB::table('tenant_settings')
                ->where('tenant_id', $tenantId)
                ->where('setting_key', $key)
                ->first();

            if ($existing) {
                DB::table('tenant_settings')
                    ->where('tenant_id', $tenantId)
                    ->where('setting_key', $key)
                    ->update(['setting_value' => $storedValue, 'setting_type' => $settingType, 'updated_at' => now()]);
            } else {
                DB::table('tenant_settings')->insert([
                    'tenant_id' => $tenantId, 'setting_key' => $key,
                    'setting_value' => $storedValue, 'setting_type' => $settingType,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            Cache::forget("marketplace_config:{$tenantId}");
        } catch (\Throwable $e) {
            Log::error('MarketplaceConfigurationService::set failed', ['key' => $key, 'tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get all marketplace config values (merged with defaults).
     */
    public static function getAll(): array
    {
        $tenantId = TenantContext::getId();
        return array_merge(self::DEFAULTS, self::getStoredValues($tenantId));
    }

    // =========================================================================
    // Convenience getters
    // =========================================================================

    public static function maxImages(): int
    {
        return (int) self::get(self::CONFIG_MAX_IMAGES, 20);
    }

    public static function maxActiveListings(): int
    {
        return (int) self::get(self::CONFIG_MAX_ACTIVE_LISTINGS, 50);
    }

    public static function listingDurationDays(): int
    {
        return (int) self::get(self::CONFIG_LISTING_DURATION_DAYS, 30);
    }

    public static function moderationEnabled(): bool
    {
        return (bool) self::get(self::CONFIG_MODERATION_ENABLED, true);
    }

    public static function allowShipping(): bool
    {
        return (bool) self::get(self::CONFIG_ALLOW_SHIPPING, false);
    }

    public static function allowFreeItems(): bool
    {
        return (bool) self::get(self::CONFIG_ALLOW_FREE_ITEMS, true);
    }

    public static function allowBusinessSellers(): bool
    {
        return (bool) self::get(self::CONFIG_ALLOW_BUSINESS_SELLERS, true);
    }

    public static function allowHybridPricing(): bool
    {
        return (bool) self::get(self::CONFIG_ALLOW_HYBRID_PRICING, false);
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    private static function getStoredValues(int $tenantId): array
    {
        return Cache::remember("marketplace_config:{$tenantId}", self::CACHE_TTL, function () use ($tenantId) {
            try {
                $rows = DB::table('tenant_settings')
                    ->where('tenant_id', $tenantId)
                    ->where('setting_key', 'LIKE', 'marketplace.%')
                    ->get(['setting_key', 'setting_value', 'setting_type']);

                $result = [];
                foreach ($rows as $row) {
                    $result[$row->setting_key] = self::castValue($row->setting_value, $row->setting_type ?? 'string');
                }
                return $result;
            } catch (\Throwable $e) {
                Log::warning('MarketplaceConfigurationService: failed to load config', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
                return [];
            }
        });
    }

    private static function castValue(string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => in_array(strtolower($value), ['true', '1', 'yes'], true),
            'integer' => (int) $value,
            'float', 'decimal' => (float) $value,
            default => $value,
        };
    }

    private static function detectType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            default => 'string',
        };
    }
}
