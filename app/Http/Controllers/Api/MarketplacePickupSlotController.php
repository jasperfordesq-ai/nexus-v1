<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\TenantContext;
use App\Models\MarketplacePickupSlot;
use App\Services\MarketplacePickupSlotService;
use App\Services\MarketplaceSellerService;
use Illuminate\Http\JsonResponse;

/**
 * MarketplacePickupSlotController — AG45 click-and-collect HTTP surface.
 *
 * Endpoints (v2, all auth-required unless noted):
 *   GET    /v2/marketplace/seller/pickup-slots                slotsIndex()
 *   POST   /v2/marketplace/seller/pickup-slots                slotsStore()
 *   PUT    /v2/marketplace/seller/pickup-slots/{id}           slotsUpdate()
 *   DELETE /v2/marketplace/seller/pickup-slots/{id}           slotsDestroy()
 *   POST   /v2/marketplace/seller/pickup-scan                 scanQr()
 *   GET    /v2/marketplace/listings/{id}/pickup-slots         listForListing()  (public)
 *   POST   /v2/marketplace/orders/{id}/pickup-reservation     reserve()
 *   GET    /v2/marketplace/me/pickups                         myReservations()
 */
class MarketplacePickupSlotController extends BaseApiController
{
    protected bool $isV2Api = true;

    private function ensureFeature(): void
    {
        if (!TenantContext::hasFeature('marketplace')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                $this->respondWithError('FEATURE_DISABLED', __('api.marketplace_feature_disabled'), null, 403)
            );
        }
    }

    // -----------------------------------------------------------------
    //  Seller — slot CRUD
    // -----------------------------------------------------------------

    public function slotsIndex(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('marketplace_pickup_slots_read', 60, 60);

        $profile = MarketplaceSellerService::getOrCreateProfile($userId);
        $slots = MarketplacePickupSlotService::listForSeller(
            $profile->id,
            $this->query('from'),
            $this->query('to')
        );

        return $this->respondWithData($slots);
    }

    public function slotsStore(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('marketplace_pickup_slots_write', 30, 60);

        $data = request()->validate([
            'slot_start' => 'required|date',
            'slot_end' => 'required|date|after:slot_start',
            'capacity' => 'nullable|integer|min:1|max:1000',
            'is_recurring' => 'nullable|boolean',
            'recurring_pattern' => 'nullable|string|max:64',
            'is_active' => 'nullable|boolean',
        ]);

        $profile = MarketplaceSellerService::getOrCreateProfile($userId);
        $slot = MarketplacePickupSlotService::create($profile->id, $data);

        return $this->respondWithData([
            'id' => $slot->id,
            'slot_start' => $slot->slot_start?->toISOString(),
            'slot_end' => $slot->slot_end?->toISOString(),
            'capacity' => $slot->capacity,
            'booked_count' => $slot->booked_count,
            'is_recurring' => (bool) $slot->is_recurring,
            'recurring_pattern' => $slot->recurring_pattern,
            'is_active' => (bool) $slot->is_active,
        ], null, 201);
    }

    public function slotsUpdate(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('marketplace_pickup_slots_write', 30, 60);

        $profile = MarketplaceSellerService::getOrCreateProfile($userId);
        $slot = MarketplacePickupSlot::query()
            ->where('tenant_id', TenantContext::getId())
            ->where('id', $id)
            ->where('seller_id', $profile->id)
            ->first();

        if (!$slot) {
            return $this->respondWithError('NOT_FOUND', __('api.marketplace_pickup_slot_not_found'), null, 404);
        }

        $data = request()->validate([
            'slot_start' => 'sometimes|date',
            'slot_end' => 'sometimes|date',
            'capacity' => 'sometimes|integer|min:1|max:1000',
            'is_recurring' => 'sometimes|boolean',
            'recurring_pattern' => 'nullable|string|max:64',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $updated = MarketplacePickupSlotService::update($slot, $data);
        } catch (\DomainException $exception) {
            return $this->respondWithError(
                $exception->getMessage(),
                $this->pickupDomainMessage($exception->getMessage()),
                null,
                422,
            );
        }

        return $this->respondWithData([
            'id' => $updated->id,
            'slot_start' => $updated->slot_start?->toISOString(),
            'slot_end' => $updated->slot_end?->toISOString(),
            'capacity' => $updated->capacity,
            'booked_count' => $updated->booked_count,
            'is_active' => (bool) $updated->is_active,
        ]);
    }

    public function slotsDestroy(int $id): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('marketplace_pickup_slots_write', 30, 60);

        $profile = MarketplaceSellerService::getOrCreateProfile($userId);
        $slot = MarketplacePickupSlot::query()
            ->where('tenant_id', TenantContext::getId())
            ->where('id', $id)
            ->where('seller_id', $profile->id)
            ->first();

        if (!$slot) {
            return $this->respondWithError('NOT_FOUND', __('api.marketplace_pickup_slot_not_found'), null, 404);
        }

        try {
            MarketplacePickupSlotService::delete($slot);
        } catch (\DomainException $exception) {
            return $this->respondWithError(
                $exception->getMessage(),
                $this->pickupDomainMessage($exception->getMessage()),
                null,
                409,
            );
        }

        return $this->respondWithData(['deleted' => true]);
    }

    // -----------------------------------------------------------------
    //  Seller — QR scan
    // -----------------------------------------------------------------

    public function scanQr(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('marketplace_pickup_scan', 60, 60);

        $data = request()->validate([
            'qr_code' => 'required|string|max:64',
        ]);

        try {
            $reservation = MarketplacePickupSlotService::scanQr($data['qr_code'], $userId);
        } catch (\DomainException $e) {
            return $this->respondWithError(
                $e->getMessage(),
                $this->pickupDomainMessage($e->getMessage()),
                null,
                422
            );
        }

        return $this->respondWithData([
            'id' => $reservation->id,
            'order_id' => $reservation->order_id,
            'listing_id' => $reservation->listing_id,
            'status' => $reservation->status,
            'picked_up_at' => $reservation->picked_up_at?->toISOString(),
        ]);
    }

    // -----------------------------------------------------------------
    //  Buyer — list slots, reserve, my pickups
    // -----------------------------------------------------------------

    public function listForListing(int $listingId): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('marketplace_pickup_slots_public', 60, 60);
        $offerId = $this->queryInt('offer_id', null, 1);

        return $this->respondWithData(
            MarketplacePickupSlotService::listAvailableForListing(
                $listingId,
                viewerUserId: $userId,
                offerId: $offerId,
            )
        );
    }

    public function reserve(int $orderId): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('marketplace_pickup_reserve', 20, 60);

        $data = request()->validate([
            'slot_id' => 'required|integer',
        ]);

        try {
            $reservation = MarketplacePickupSlotService::reserve(
                (int) $data['slot_id'],
                $orderId,
                $userId
            );
        } catch (\DomainException $e) {
            return $this->respondWithError(
                $e->getMessage(),
                $this->pickupDomainMessage($e->getMessage()),
                null,
                422
            );
        }

        return $this->respondWithData([
            'id' => $reservation->id,
            'slot_id' => $reservation->slot_id,
            'order_id' => $reservation->order_id,
            'qr_code' => $reservation->qr_code,
            'status' => $reservation->status,
            'reserved_at' => $reservation->reserved_at?->toISOString(),
        ], null, 201);
    }

    public function myReservations(): JsonResponse
    {
        $this->ensureFeature();
        $userId = $this->requireAuth();
        $this->rateLimit('marketplace_pickup_my', 60, 60);

        return $this->respondWithData(
            MarketplacePickupSlotService::listForBuyer($userId)
        );
    }

    private function pickupDomainMessage(string $code): string
    {
        $key = match ($code) {
            'SELLER_PROFILE_NOT_FOUND' => 'api.marketplace_pickup_seller_profile_not_found',
            'SLOT_END_BEFORE_START' => 'api.marketplace_pickup_slot_end_before_start',
            'CAPACITY_BELOW_BOOKINGS' => 'api.marketplace_pickup_capacity_below_bookings',
            'SLOT_HAS_RESERVATIONS' => 'api.marketplace_pickup_slot_has_reservations',
            'SLOT_NOT_FOUND' => 'api.marketplace_pickup_slot_not_found',
            'QR_NOT_FOUND' => 'api.marketplace_pickup_qr_not_found',
            'NOT_FOR_SELLER' => 'api.marketplace_pickup_not_for_seller',
            'ORDER_NOT_PAID' => 'api.marketplace_pickup_order_not_paid',
            'ALREADY_PICKED_UP' => 'api.marketplace_pickup_already_picked_up',
            'RESERVATION_CANCELLED' => 'api.marketplace_pickup_reservation_cancelled',
            'ORDER_NOT_FOUND' => 'api.marketplace_pickup_order_not_found',
            'ORDER_NOT_PICKUP_ELIGIBLE' => 'api.marketplace_pickup_order_not_eligible',
            'SLOT_INACTIVE' => 'api.marketplace_pickup_slot_inactive',
            'SLOT_PAST' => 'api.marketplace_pickup_slot_past',
            'SLOT_FULL' => 'api.marketplace_pickup_slot_full',
            'SLOT_SELLER_MISMATCH' => 'api.marketplace_pickup_slot_seller_mismatch',
            'DUPLICATE_RESERVATION' => 'api.marketplace_pickup_duplicate_reservation',
            default => 'api.marketplace_pickup_operation_failed',
        };

        return __($key);
    }
}
