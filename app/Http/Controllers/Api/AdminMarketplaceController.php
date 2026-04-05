<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceSellerProfile;
use App\Services\MarketplaceConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * AdminMarketplaceController — Admin endpoints for marketplace management.
 *
 * All endpoints require admin auth and the marketplace feature to be enabled.
 */
class AdminMarketplaceController extends BaseApiController
{
    protected bool $isV2Api = true;

    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('marketplace')) {
            abort(403, 'Marketplace feature is not enabled for this tenant.');
        }
    }

    // -----------------------------------------------------------------
    //  Dashboard
    // -----------------------------------------------------------------

    /**
     * GET /v2/admin/marketplace/dashboard
     */
    public function dashboard(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('admin_marketplace_dashboard', 30, 60);

        $tenantId = TenantContext::getId();

        $totalListings = MarketplaceListing::count();
        $activeListings = MarketplaceListing::where('status', 'active')
            ->where('moderation_status', 'approved')
            ->count();
        $pendingModeration = MarketplaceListing::where('moderation_status', 'pending')->count();
        $totalSellers = MarketplaceSellerProfile::count();

        $totalOrders = 0;
        $revenue = 0;
        if (DB::getSchemaBuilder()->hasTable('marketplace_orders')) {
            $totalOrders = DB::table('marketplace_orders')
                ->where('tenant_id', $tenantId)
                ->whereNotIn('status', ['cancelled', 'refunded'])
                ->count();
            $revenue = (float) DB::table('marketplace_orders')
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->sum('total_price');
        }

        return $this->respondWithData([
            'total_listings' => $totalListings,
            'active_listings' => $activeListings,
            'pending_moderation' => $pendingModeration,
            'total_sellers' => $totalSellers,
            'total_orders' => $totalOrders,
            'revenue' => $revenue,
            'currency' => 'EUR',
        ]);
    }

    // -----------------------------------------------------------------
    //  Listings Management
    // -----------------------------------------------------------------

    /**
     * GET /v2/admin/marketplace/listings
     */
    public function listings(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('admin_marketplace_listings', 30, 60);

        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $moderationStatus = $this->query('moderation_status');
        $status = $this->query('status');
        $search = $this->query('q');

        $query = MarketplaceListing::with([
            'user:id,first_name,last_name,avatar_url',
            'category:id,name,slug',
            'images' => fn ($q) => $q->where('is_primary', true)->limit(1),
        ]);

        if ($moderationStatus) {
            $query->where('moderation_status', $moderationStatus);
        }
        if ($status) {
            $query->where('status', $status);
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();
        $listings = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $items = $listings->map(fn ($l) => [
            'id' => $l->id,
            'title' => $l->title,
            'price' => $l->price,
            'price_currency' => $l->price_currency,
            'price_type' => $l->price_type,
            'status' => $l->status,
            'moderation_status' => $l->moderation_status,
            'moderation_notes' => $l->moderation_notes,
            'seller_type' => $l->seller_type,
            'views_count' => $l->views_count,
            'image' => $l->images->first() ? $l->images->first()->image_url : null,
            'category' => $l->category?->name,
            'user' => $l->user ? [
                'id' => $l->user->id,
                'name' => trim($l->user->first_name . ' ' . $l->user->last_name),
            ] : null,
            'created_at' => $l->created_at?->toISOString(),
        ])->all();

        return $this->respondWithPaginatedCollection($items, $total, $page, $perPage);
    }

    /**
     * POST /v2/admin/marketplace/listings/{id}/approve
     */
    public function approveListing(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('admin_marketplace_moderate', 30, 60);

        $listing = MarketplaceListing::findOrFail($id);
        $listing->moderation_status = 'approved';
        $listing->moderated_by = auth()->id();
        $listing->moderated_at = now();
        $listing->moderation_notes = null;

        // Auto-activate if in draft
        if ($listing->status === 'draft') {
            $listing->status = 'active';
        }

        $listing->save();

        return $this->respondWithData(['message' => 'Listing approved.']);
    }

    /**
     * POST /v2/admin/marketplace/listings/{id}/reject
     */
    public function rejectListing(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('admin_marketplace_moderate', 30, 60);

        $data = request()->validate([
            'notes' => 'required|string|max:1000',
        ]);

        $listing = MarketplaceListing::findOrFail($id);
        $listing->moderation_status = 'rejected';
        $listing->moderated_by = auth()->id();
        $listing->moderated_at = now();
        $listing->moderation_notes = $data['notes'];
        $listing->status = 'removed';
        $listing->save();

        return $this->respondWithData(['message' => 'Listing rejected.']);
    }

    /**
     * DELETE /v2/admin/marketplace/listings/{id}
     */
    public function destroyListing(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('admin_marketplace_moderate', 15, 60);

        $listing = MarketplaceListing::findOrFail($id);
        $listing->status = 'removed';
        $listing->moderation_status = 'rejected';
        $listing->moderated_by = auth()->id();
        $listing->moderated_at = now();
        $listing->save();

        return $this->respondWithData(['message' => 'Listing removed.']);
    }

    // -----------------------------------------------------------------
    //  Seller Management
    // -----------------------------------------------------------------

    /**
     * GET /v2/admin/marketplace/sellers
     */
    public function sellers(): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('admin_marketplace_sellers', 30, 60);

        $page = $this->queryInt('page', 1, 1);
        $perPage = $this->queryInt('per_page', 20, 1, 100);
        $sellerType = $this->query('seller_type');
        $verified = $this->query('verified');

        $query = MarketplaceSellerProfile::with('user:id,first_name,last_name,avatar_url,email');

        if ($sellerType) {
            $query->where('seller_type', $sellerType);
        }
        if ($verified === 'true') {
            $query->where('business_verified', true);
        } elseif ($verified === 'false') {
            $query->where('seller_type', 'business')->where('business_verified', false);
        }

        $total = $query->count();
        $sellers = $query->orderBy('id', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $items = $sellers->map(fn ($s) => [
            'id' => $s->id,
            'user_id' => $s->user_id,
            'display_name' => $s->display_name ?? trim(($s->user->first_name ?? '') . ' ' . ($s->user->last_name ?? '')),
            'seller_type' => $s->seller_type,
            'business_name' => $s->business_name,
            'business_verified' => $s->business_verified,
            'is_community_endorsed' => $s->is_community_endorsed,
            'total_sales' => $s->total_sales,
            'avg_rating' => $s->avg_rating,
            'total_ratings' => $s->total_ratings,
            'active_listings' => MarketplaceListing::where('user_id', $s->user_id)->where('status', 'active')->count(),
            'joined_marketplace_at' => $s->joined_marketplace_at?->toISOString(),
            'user' => $s->user ? [
                'id' => $s->user->id,
                'name' => trim($s->user->first_name . ' ' . $s->user->last_name),
                'email' => $s->user->email,
                'avatar_url' => $s->user->avatar_url,
            ] : null,
        ])->all();

        return $this->respondWithPaginatedCollection($items, $total, $page, $perPage);
    }

    /**
     * POST /v2/admin/marketplace/sellers/{id}/verify
     */
    public function verifySeller(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('admin_marketplace_sellers', 15, 60);

        $seller = MarketplaceSellerProfile::findOrFail($id);

        if ($seller->seller_type !== 'business') {
            return $this->respondWithError('VALIDATION_ERROR', 'Only business sellers can be verified.', null, 422);
        }

        $seller->business_verified = true;
        $seller->save();

        return $this->respondWithData(['message' => 'Seller verified.']);
    }

    /**
     * POST /v2/admin/marketplace/sellers/{id}/suspend
     */
    public function suspendSeller(int $id): JsonResponse
    {
        $this->ensureFeature();
        $this->rateLimit('admin_marketplace_sellers', 15, 60);

        $seller = MarketplaceSellerProfile::findOrFail($id);

        // Deactivate all their listings
        MarketplaceListing::where('user_id', $seller->user_id)
            ->where('status', 'active')
            ->update(['status' => 'removed', 'moderation_status' => 'rejected']);

        return $this->respondWithData(['message' => 'Seller suspended and all listings deactivated.']);
    }
}
