<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Services;

use App\Core\TenantContext;
use App\Models\XpShopItem;
use App\Models\UserXpPurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * XPShopService — Eloquent/DB query builder service for the XP shop.
 *
 * Manages shop items, purchases, and user perks. All queries are tenant-scoped
 * via HasTenantScope trait on models or explicit where clauses.
 */
class XPShopService
{
    /**
     * Get all available shop items for the current tenant.
     */
    public function getItems(int $tenantId): array
    {
        return DB::table('xp_shop_items')
            ->where('tenant_id', $tenantId)
            ->where('is_active', 1)
            ->orderBy('display_order')
            ->orderBy('xp_cost')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Purchase an item (with explicit tenant_id parameter).
     */
    public function purchase(int $tenantId, int $userId, int $itemId): bool
    {
        $result = $this->purchaseItem($userId, $itemId);
        return $result['success'] ?? false;
    }

    /**
     * Get user's purchases.
     */
    public function getUserPurchases(int $tenantId, int $userId): array
    {
        return DB::table('user_xp_purchases as uxp')
            ->join('xp_shop_items as xsi', 'uxp.item_id', '=', 'xsi.id')
            ->where('uxp.tenant_id', $tenantId)
            ->where('uxp.user_id', $userId)
            ->select(['xsi.*', 'uxp.purchased_at', 'uxp.expires_at', 'uxp.xp_spent'])
            ->orderByDesc('uxp.purchased_at')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Get user's XP balance.
     */
    public function getBalance(int $tenantId, int $userId): int
    {
        $user = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->first(['xp']);

        return (int) ($user->xp ?? 0);
    }

    /**
     * Get items with user purchase status.
     */
    public function getItemsWithUserStatus(int $userId): array
    {
        $tenantId = TenantContext::getId();

        $items = XpShopItem::where('is_active', 1)
            ->orderBy('display_order')
            ->orderBy('xp_cost')
            ->get()
            ->map(fn ($r) => $r->toArray())
            ->all();

        // Get user's purchases grouped by item
        $purchases = DB::table('user_xp_purchases')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->selectRaw('item_id, COUNT(*) as purchase_count')
            ->groupBy('item_id')
            ->pluck('purchase_count', 'item_id')
            ->all();

        // Get user's current XP
        $userXP = DB::table('users')
            ->where('id', $userId)
            ->where('tenant_id', $tenantId)
            ->value('xp');
        $userXP = (int) ($userXP ?? 0);

        foreach ($items as &$item) {
            $item['user_purchases'] = $purchases[$item['id']] ?? 0;
            $item['can_purchase'] = $this->canPurchaseItem($userId, $item, $userXP);
            $item['reason'] = $this->getPurchaseBlockReason($userId, $item, $userXP);
            $item['cost_xp'] = $item['xp_cost'] ?? 0;
        }

        return [
            'items' => $items,
            'user_xp' => $userXP,
        ];
    }

    /**
     * Purchase an item for a user (uses TenantContext for tenant scoping).
     */
    public function purchaseItem(int $userId, $itemId): array
    {
        $tenantId = TenantContext::getId();

        $item = DB::table('xp_shop_items')
            ->where('id', $itemId)
            ->where('tenant_id', $tenantId)
            ->where('is_active', 1)
            ->first();

        if (!$item) {
            return ['success' => false, 'error' => 'Item not found'];
        }

        $itemArr = (array) $item;

        if (!$this->canPurchaseItem($userId, $itemArr)) {
            $reason = $this->getPurchaseBlockReason($userId, $itemArr, null);
            return ['success' => false, 'error' => $reason ?? 'Cannot purchase'];
        }

        DB::beginTransaction();
        try {
            // Deduct XP
            $affected = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->where('xp', '>=', $item->xp_cost)
                ->update(['xp' => DB::raw("xp - {$item->xp_cost}")]);

            if ($affected === 0) {
                DB::rollBack();
                return ['success' => false, 'error' => 'Not enough XP'];
            }

            // Record purchase
            $expiresAt = null;
            if ($item->item_type === 'perk') {
                $expiresAt = now()->addDays(30);
            }

            DB::table('user_xp_purchases')->insert([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'item_id' => $itemId,
                'xp_spent' => $item->xp_cost,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('XPShopService::purchaseItem error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Purchase failed'];
        }

        return [
            'success' => true,
            'item' => $itemArr,
            'xp_spent' => $item->xp_cost,
        ];
    }

    /**
     * Get all available items (active, for current tenant).
     */
    public function getAvailableItems(): array
    {
        return XpShopItem::where('is_active', 1)
            ->orderBy('display_order')
            ->orderBy('xp_cost')
            ->get()
            ->map(fn ($r) => $r->toArray())
            ->all();
    }

    /**
     * Get user's active perks.
     */
    public function getUserActivePerks(int $userId): array
    {
        $tenantId = TenantContext::getId();

        return DB::table('user_xp_purchases as uxp')
            ->join('xp_shop_items as xsi', 'uxp.item_id', '=', 'xsi.id')
            ->where('uxp.tenant_id', $tenantId)
            ->where('uxp.user_id', $userId)
            ->where('uxp.is_active', 1)
            ->where(function ($q) {
                $q->whereNull('uxp.expires_at')
                    ->orWhere('uxp.expires_at', '>', now());
            })
            ->select(['xsi.*', 'uxp.purchased_at', 'uxp.expires_at'])
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Check if user can purchase an item.
     */
    private function canPurchaseItem(int $userId, array $item, ?int $userXP = null): bool
    {
        if ($userXP === null) {
            $tenantId = TenantContext::getId();
            $userXP = (int) DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->value('xp');
        }

        if ($userXP < $item['xp_cost']) {
            return false;
        }

        if ($item['stock_limit'] !== null) {
            $totalPurchases = DB::table('user_xp_purchases')
                ->where('item_id', $item['id'])
                ->count();
            if ($totalPurchases >= $item['stock_limit']) {
                return false;
            }
        }

        if ($item['per_user_limit'] !== null) {
            $userPurchases = DB::table('user_xp_purchases')
                ->where('user_id', $userId)
                ->where('item_id', $item['id'])
                ->count();
            if ($userPurchases >= $item['per_user_limit']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get reason why purchase is blocked.
     */
    private function getPurchaseBlockReason(int $userId, array $item, ?int $userXP): ?string
    {
        if ($userXP === null) {
            $tenantId = TenantContext::getId();
            $userXP = (int) DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->value('xp');
        }

        if ($userXP < $item['xp_cost']) {
            return 'Not enough XP';
        }

        if ($item['stock_limit'] !== null) {
            $totalPurchases = DB::table('user_xp_purchases')
                ->where('item_id', $item['id'])
                ->count();
            if ($totalPurchases >= $item['stock_limit']) {
                return 'Out of stock';
            }
        }

        if ($item['per_user_limit'] !== null) {
            $userPurchases = DB::table('user_xp_purchases')
                ->where('user_id', $userId)
                ->where('item_id', $item['id'])
                ->count();
            if ($userPurchases >= $item['per_user_limit']) {
                return 'Already owned';
            }
        }

        return null;
    }
}
