<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use App\Services\MarketplaceInventoryService;
use Illuminate\Http\JsonResponse;

/**
 * MarketplaceInventoryController — AG46 inventory tracking HTTP surface.
 *
 * Endpoints (v2, all auth-required):
 *   PATCH /v2/marketplace/seller/listings/{id}/inventory   updateInventory()
 */
class MarketplaceInventoryController extends BaseApiController
{
    protected bool $isV2Api = true;

    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('marketplace')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', 'The marketplace feature is not enabled for this community.', null, 403)
            );
        }
    }

    public function updateInventory(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('marketplace_inventory_update', 30, 60);

        $listing = MarketplaceListing::query()
            ->where('tenant_id', TenantContext::getId())
            ->where('id', $id)
            ->first();

        if (!$listing) {
            return $this->respondWithError('NOT_FOUND', 'Listing not found.', null, 404);
        }
        if ((int) $listing->user_id !== $userId) {
            return $this->respondWithError('FORBIDDEN', 'You can only update your own listing inventory.', null, 403);
        }

        $data = request()->validate([
            'inventory_count' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'is_oversold_protected' => 'nullable|boolean',
        ]);

        $updated = MarketplaceInventoryService::updateInventory($listing, $data);

        return $this->respondWithData([
            'id' => $updated->id,
            'inventory_count' => $updated->inventory_count,
            'low_stock_threshold' => $updated->low_stock_threshold,
            'is_oversold_protected' => (bool) $updated->is_oversold_protected,
            'status' => $updated->status,
        ]);
    }
}
