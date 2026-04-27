<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use InvalidArgumentException;

/**
 * Time-credit ↔ marketplace loyalty bridge.
 *
 * Members earn hours via Caring Community participation. Participating
 * marketplace merchants opt in to accept those hours as a discount on
 * their CHF cash listings — at an exchange rate they set themselves
 * and capped at a max-discount-per-order percentage.
 *
 * Each merchant absorbs the discount cost as their own loyalty programme.
 * No tenant-treasury subsidy. No pegged exchange rate.
 */
class CaringLoyaltyService
{
    /** Maximum credits a single redemption can apply (defensive cap). */
    private const MAX_CREDITS_PER_REDEMPTION = 1000.0;

    // ─── Read APIs ─────────────────────────────────────────────────────────────

    /**
     * Calculate the maximum credits a member can apply to a given order.
     *
     * Returns a structured envelope describing whether redemption is possible
     * and how much. The frontend uses this to render the "use my time credits"
     * card with live preview values.
     *
     * @return array{
     *   accepts: bool,
     *   member_credits: float,
     *   exchange_rate_chf: float,
     *   max_discount_pct: int,
     *   max_credits_usable: float,
     *   max_discount_chf: float,
     *   reason?: string
     * }
     */
    public function calculateAvailableDiscount(int $memberId, int $sellerId, float $orderTotalChf): array
    {
        $tenantId = TenantContext::getId();

        $base = [
            'accepts'            => false,
            'member_credits'     => 0.0,
            'exchange_rate_chf'  => 0.0,
            'max_discount_pct'   => 0,
            'max_credits_usable' => 0.0,
            'max_discount_chf'   => 0.0,
        ];

        if (!Schema::hasTable('marketplace_seller_loyalty_settings')) {
            return $base + ['reason' => 'feature_unavailable'];
        }

        if ($orderTotalChf <= 0) {
            return $base + ['reason' => 'invalid_order_total'];
        }

        $settings = DB::table('marketplace_seller_loyalty_settings')
            ->where('tenant_id', $tenantId)
            ->where('seller_user_id', $sellerId)
            ->first();

        if (!$settings || !(int) $settings->accepts_time_credits) {
            return $base + ['reason' => 'merchant_disabled'];
        }

        $rate    = (float) $settings->loyalty_chf_per_hour;
        $maxPct  = (int) $settings->loyalty_max_discount_pct;

        if ($rate <= 0 || $maxPct <= 0) {
            return $base + ['reason' => 'merchant_misconfigured'];
        }

        // Member's wallet balance (hours)
        $memberCredits = (float) (DB::table('users')
            ->where('id', $memberId)
            ->where('tenant_id', $tenantId)
            ->value('balance') ?? 0);

        // Maximum CHF discount allowed by merchant policy
        $maxDiscountChf = round(($orderTotalChf * $maxPct) / 100, 2);

        // Convert that ceiling into hours, then cap by member's actual balance
        $maxCreditsByPolicy = $rate > 0 ? round($maxDiscountChf / $rate, 2) : 0.0;
        $maxCreditsUsable   = min($memberCredits, $maxCreditsByPolicy, self::MAX_CREDITS_PER_REDEMPTION);
        $maxCreditsUsable   = max(0.0, round($maxCreditsUsable, 2));

        // Recompute the actual max discount the member can claim
        $effectiveDiscountChf = round($maxCreditsUsable * $rate, 2);

        return [
            'accepts'            => true,
            'member_credits'     => round($memberCredits, 2),
            'exchange_rate_chf'  => $rate,
            'max_discount_pct'   => $maxPct,
            'max_credits_usable' => $maxCreditsUsable,
            'max_discount_chf'   => $effectiveDiscountChf,
        ];
    }

    /**
     * Apply a redemption.
     *
     * Atomically:
     *   1. Validates merchant accepts credits
     *   2. Validates member has the requested credits
     *   3. Validates the discount fits within merchant's max-discount-pct cap
     *   4. Debits the member's wallet
     *   5. Inserts a caring_loyalty_redemption row
     *
     * @return array{discount_chf: float, redemption_id: int, new_wallet_balance: float}
     *
     * @throws InvalidArgumentException Validation failures
     * @throws RuntimeException Insufficient balance / merchant disabled
     */
    public function redeem(
        int $memberId,
        int $sellerId,
        ?int $listingId,
        float $creditsToUse,
        float $orderTotalChf
    ): array {
        $tenantId = TenantContext::getId();

        if ($creditsToUse <= 0) {
            throw new InvalidArgumentException('Credits to use must be greater than 0');
        }
        if ($orderTotalChf <= 0) {
            throw new InvalidArgumentException('Order total must be greater than 0');
        }
        if (round($creditsToUse, 2) != $creditsToUse) {
            throw new InvalidArgumentException('Credits must have at most 2 decimal places');
        }
        if ($creditsToUse > self::MAX_CREDITS_PER_REDEMPTION) {
            throw new InvalidArgumentException('Credits to use exceed per-redemption maximum');
        }
        if ($memberId === $sellerId) {
            throw new RuntimeException('Cannot redeem credits at your own listing');
        }

        if (!Schema::hasTable('marketplace_seller_loyalty_settings')) {
            throw new RuntimeException('Loyalty programme not available on this tenant');
        }
        if (!Schema::hasTable('caring_loyalty_redemptions')) {
            throw new RuntimeException('Loyalty programme not available on this tenant');
        }

        // Lock seller settings row first (deterministic order: settings then user)
        $result = DB::transaction(function () use ($tenantId, $memberId, $sellerId, $listingId, $creditsToUse, $orderTotalChf) {
            $settings = DB::table('marketplace_seller_loyalty_settings')
                ->where('tenant_id', $tenantId)
                ->where('seller_user_id', $sellerId)
                ->lockForUpdate()
                ->first();

            if (!$settings || !(int) $settings->accepts_time_credits) {
                throw new RuntimeException('This merchant is no longer accepting time credits');
            }

            $rate   = (float) $settings->loyalty_chf_per_hour;
            $maxPct = (int) $settings->loyalty_max_discount_pct;

            if ($rate <= 0 || $maxPct <= 0) {
                throw new RuntimeException('Merchant loyalty configuration invalid');
            }

            $discountChf = round($creditsToUse * $rate, 2);
            $maxDiscountChf = round(($orderTotalChf * $maxPct) / 100, 2);

            if ($discountChf > $maxDiscountChf + 0.005) {
                throw new RuntimeException('Discount exceeds maximum allowed for this order');
            }

            // Lock the member's row to prevent racing wallet debits
            $member = DB::table('users')
                ->where('id', $memberId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (!$member) {
                throw new RuntimeException('Member account not found');
            }

            $currentBalance = (float) $member->balance;
            if ($currentBalance < $creditsToUse) {
                throw new RuntimeException('Not enough time credits in wallet');
            }

            // Debit wallet
            $newBalance = round($currentBalance - $creditsToUse, 2);
            DB::table('users')
                ->where('id', $memberId)
                ->where('tenant_id', $tenantId)
                ->update([
                    'balance'    => $newBalance,
                    'updated_at' => now(),
                ]);

            $redemptionId = DB::table('caring_loyalty_redemptions')->insertGetId([
                'tenant_id'              => $tenantId,
                'member_user_id'         => $memberId,
                'merchant_user_id'       => $sellerId,
                'marketplace_listing_id' => $listingId,
                'marketplace_order_id'   => null,
                'credits_used'           => $creditsToUse,
                'exchange_rate_chf'      => $rate,
                'discount_chf'           => $discountChf,
                'order_total_chf'        => $orderTotalChf,
                'status'                 => 'applied',
                'redeemed_at'            => now(),
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            return [
                'discount_chf'       => $discountChf,
                'redemption_id'      => (int) $redemptionId,
                'new_wallet_balance' => $newBalance,
            ];
        });

        Log::info('CaringLoyaltyService: redemption applied', [
            'tenant_id'      => $tenantId,
            'member_id'      => $memberId,
            'merchant_id'    => $sellerId,
            'listing_id'     => $listingId,
            'credits_used'   => $creditsToUse,
            'discount_chf'   => $result['discount_chf'],
            'redemption_id'  => $result['redemption_id'],
        ]);

        return $result;
    }

    // ─── History APIs ─────────────────────────────────────────────────────────

    /**
     * List the authenticated member's redemption history.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listMemberHistory(int $memberId, int $limit = 50): array
    {
        $tenantId = TenantContext::getId();
        $limit    = max(1, min(200, $limit));

        if (!Schema::hasTable('caring_loyalty_redemptions')) {
            return [];
        }

        $rows = DB::table('caring_loyalty_redemptions as r')
            ->leftJoin('users as merchant', function ($join) {
                $join->on('merchant.id', '=', 'r.merchant_user_id')
                    ->on('merchant.tenant_id', '=', 'r.tenant_id');
            })
            ->leftJoin('marketplace_listings as l', function ($join) {
                $join->on('l.id', '=', 'r.marketplace_listing_id')
                    ->on('l.tenant_id', '=', 'r.tenant_id');
            })
            ->where('r.tenant_id', $tenantId)
            ->where('r.member_user_id', $memberId)
            ->orderByDesc('r.redeemed_at')
            ->limit($limit)
            ->select([
                'r.id',
                'r.credits_used',
                'r.exchange_rate_chf',
                'r.discount_chf',
                'r.order_total_chf',
                'r.status',
                'r.redeemed_at',
                'r.marketplace_listing_id',
                'merchant.id as merchant_id',
                'merchant.name as merchant_name',
                'merchant.first_name as merchant_first_name',
                'merchant.last_name as merchant_last_name',
                'l.title as listing_title',
            ])
            ->get();

        return $rows->map(fn ($row) => $this->formatRedemption($row))->all();
    }

    /**
     * Admin: list all redemptions for the current tenant.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listTenantRedemptions(int $limit = 50): array
    {
        $tenantId = TenantContext::getId();
        $limit    = max(1, min(500, $limit));

        if (!Schema::hasTable('caring_loyalty_redemptions')) {
            return [];
        }

        $rows = DB::table('caring_loyalty_redemptions as r')
            ->leftJoin('users as member', function ($join) {
                $join->on('member.id', '=', 'r.member_user_id')
                    ->on('member.tenant_id', '=', 'r.tenant_id');
            })
            ->leftJoin('users as merchant', function ($join) {
                $join->on('merchant.id', '=', 'r.merchant_user_id')
                    ->on('merchant.tenant_id', '=', 'r.tenant_id');
            })
            ->leftJoin('marketplace_listings as l', function ($join) {
                $join->on('l.id', '=', 'r.marketplace_listing_id')
                    ->on('l.tenant_id', '=', 'r.tenant_id');
            })
            ->where('r.tenant_id', $tenantId)
            ->orderByDesc('r.redeemed_at')
            ->limit($limit)
            ->select([
                'r.id',
                'r.credits_used',
                'r.exchange_rate_chf',
                'r.discount_chf',
                'r.order_total_chf',
                'r.status',
                'r.redeemed_at',
                'r.marketplace_listing_id',
                'member.id as member_id',
                'member.name as member_name',
                'member.first_name as member_first_name',
                'member.last_name as member_last_name',
                'merchant.id as merchant_id',
                'merchant.name as merchant_name',
                'merchant.first_name as merchant_first_name',
                'merchant.last_name as merchant_last_name',
                'l.title as listing_title',
            ])
            ->get();

        return $rows->map(fn ($row) => $this->formatRedemption($row, includeMember: true))->all();
    }

    /**
     * Aggregate stats for the current tenant.
     *
     * @return array{total_redemptions: int, total_credits: float, total_discount_chf: float}
     */
    public function tenantStats(): array
    {
        $tenantId = TenantContext::getId();

        if (!Schema::hasTable('caring_loyalty_redemptions')) {
            return ['total_redemptions' => 0, 'total_credits' => 0.0, 'total_discount_chf' => 0.0];
        }

        $row = DB::table('caring_loyalty_redemptions')
            ->where('tenant_id', $tenantId)
            ->where('status', 'applied')
            ->selectRaw('COUNT(*) as total_redemptions, COALESCE(SUM(credits_used), 0) as total_credits, COALESCE(SUM(discount_chf), 0) as total_discount_chf')
            ->first();

        return [
            'total_redemptions'   => (int) ($row->total_redemptions ?? 0),
            'total_credits'       => round((float) ($row->total_credits ?? 0), 2),
            'total_discount_chf'  => round((float) ($row->total_discount_chf ?? 0), 2),
        ];
    }

    // ─── Seller settings ──────────────────────────────────────────────────────

    /**
     * Get a seller's loyalty settings (or defaults if none exist).
     *
     * @return array{seller_user_id: int, accepts_time_credits: bool, loyalty_chf_per_hour: float, loyalty_max_discount_pct: int}
     */
    public function getSellerSettings(int $sellerId): array
    {
        $tenantId = TenantContext::getId();

        $defaults = [
            'seller_user_id'           => $sellerId,
            'accepts_time_credits'     => false,
            'loyalty_chf_per_hour'     => 25.00,
            'loyalty_max_discount_pct' => 50,
        ];

        if (!Schema::hasTable('marketplace_seller_loyalty_settings')) {
            return $defaults;
        }

        $row = DB::table('marketplace_seller_loyalty_settings')
            ->where('tenant_id', $tenantId)
            ->where('seller_user_id', $sellerId)
            ->first();

        if (!$row) {
            return $defaults;
        }

        return [
            'seller_user_id'           => $sellerId,
            'accepts_time_credits'     => (bool) $row->accepts_time_credits,
            'loyalty_chf_per_hour'     => round((float) $row->loyalty_chf_per_hour, 2),
            'loyalty_max_discount_pct' => (int) $row->loyalty_max_discount_pct,
        ];
    }

    /**
     * Upsert a seller's loyalty settings.
     */
    public function updateSellerSettings(
        int $sellerId,
        bool $acceptsTimeCredits,
        float $chfPerHour,
        int $maxDiscountPct
    ): array {
        $tenantId = TenantContext::getId();

        if ($chfPerHour <= 0 || $chfPerHour > 9999) {
            throw new InvalidArgumentException('CHF per hour must be between 0 and 9999');
        }
        if ($maxDiscountPct < 0 || $maxDiscountPct > 100) {
            throw new InvalidArgumentException('Max discount percent must be between 0 and 100');
        }
        if (!Schema::hasTable('marketplace_seller_loyalty_settings')) {
            throw new RuntimeException('Loyalty programme not available on this tenant');
        }

        DB::table('marketplace_seller_loyalty_settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'seller_user_id' => $sellerId],
            [
                'accepts_time_credits'     => $acceptsTimeCredits ? 1 : 0,
                'loyalty_chf_per_hour'     => round($chfPerHour, 2),
                'loyalty_max_discount_pct' => $maxDiscountPct,
                'updated_at'               => now(),
                'created_at'               => now(),
            ]
        );

        return $this->getSellerSettings($sellerId);
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    private function formatRedemption(object $row, bool $includeMember = false): array
    {
        $merchantName = $this->buildName(
            $row->merchant_first_name ?? null,
            $row->merchant_last_name ?? null,
            $row->merchant_name ?? null
        );

        $payload = [
            'id'                     => (int) $row->id,
            'credits_used'           => round((float) $row->credits_used, 2),
            'exchange_rate_chf'      => round((float) $row->exchange_rate_chf, 2),
            'discount_chf'           => round((float) $row->discount_chf, 2),
            'order_total_chf'        => round((float) $row->order_total_chf, 2),
            'status'                 => (string) $row->status,
            'redeemed_at'            => $row->redeemed_at,
            'merchant_id'            => isset($row->merchant_id) ? (int) $row->merchant_id : null,
            'merchant_name'          => $merchantName,
            'marketplace_listing_id' => isset($row->marketplace_listing_id) ? (int) $row->marketplace_listing_id : null,
            'listing_title'          => $row->listing_title ?? null,
        ];

        if ($includeMember) {
            $memberName = $this->buildName(
                $row->member_first_name ?? null,
                $row->member_last_name ?? null,
                $row->member_name ?? null
            );
            $payload['member_id']   = isset($row->member_id) ? (int) $row->member_id : null;
            $payload['member_name'] = $memberName;
        }

        return $payload;
    }

    private function buildName(?string $first, ?string $last, ?string $fallback): string
    {
        $combined = trim(((string) $first) . ' ' . ((string) $last));
        if ($combined !== '') {
            return $combined;
        }
        return (string) ($fallback ?? '');
    }
}
