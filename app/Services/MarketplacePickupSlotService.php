<?php
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

declare(strict_types=1);

namespace App\Services;

use App\Core\TenantContext;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceOrder;
use App\Models\MarketplacePickupReservation;
use App\Models\MarketplacePickupSlot;
use App\Models\MarketplaceSellerProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AG45 — Click-and-collect: pickup slot CRUD, atomic reservation, QR scanning.
 *
 * Slots are owned by a marketplace_seller_profiles row. Reservations decrement
 * remaining capacity inside a transaction with row-level lock to prevent
 * over-booking.
 */
class MarketplacePickupSlotService
{
    // -----------------------------------------------------------------
    //  Seller — slot management
    // -----------------------------------------------------------------

    /**
     * List slots for a seller in a date window.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function listForSeller(int $sellerProfileId, ?string $from = null, ?string $to = null): array
    {
        $tenantId = TenantContext::getId();
        $q = MarketplacePickupSlot::query()
            ->where('tenant_id', $tenantId)
            ->where('seller_id', $sellerProfileId)
            ->orderBy('slot_start', 'asc');

        if ($from) {
            $q->where('slot_start', '>=', $from);
        }
        if ($to) {
            $q->where('slot_start', '<=', $to);
        }

        return $q->get()->map(fn ($s) => self::format($s))->all();
    }

    public static function create(int $sellerProfileId, array $data): MarketplacePickupSlot
    {
        $tenantId = TenantContext::getId();

        $slot = new MarketplacePickupSlot();
        $slot->tenant_id = $tenantId;
        $slot->seller_id = $sellerProfileId;
        $slot->slot_start = $data['slot_start'];
        $slot->slot_end = $data['slot_end'];
        $slot->capacity = max(1, (int) ($data['capacity'] ?? 1));
        $slot->booked_count = 0;
        $slot->is_recurring = (bool) ($data['is_recurring'] ?? false);
        $slot->recurring_pattern = $data['recurring_pattern'] ?? null;
        $slot->is_active = (bool) ($data['is_active'] ?? true);
        $slot->save();

        return $slot;
    }

    public static function update(MarketplacePickupSlot $slot, array $data): MarketplacePickupSlot
    {
        foreach (['slot_start', 'slot_end', 'capacity', 'is_recurring', 'recurring_pattern', 'is_active'] as $f) {
            if (array_key_exists($f, $data)) {
                $slot->{$f} = $data[$f];
            }
        }
        $slot->save();
        return $slot;
    }

    public static function delete(MarketplacePickupSlot $slot): void
    {
        $slot->delete();
    }

    // -----------------------------------------------------------------
    //  Buyer — listing slots & reservations
    // -----------------------------------------------------------------

    /**
     * Available future slots with capacity remaining for a given listing's seller.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function listAvailableForListing(int $listingId, int $limit = 50): array
    {
        $tenantId = TenantContext::getId();
        $listing = MarketplaceListing::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $listingId)
            ->first();
        if (!$listing) {
            return [];
        }

        $sellerProfile = MarketplaceSellerProfile::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $listing->user_id)
            ->first();
        if (!$sellerProfile) {
            return [];
        }

        $slots = MarketplacePickupSlot::query()
            ->where('tenant_id', $tenantId)
            ->where('seller_id', $sellerProfile->id)
            ->where('is_active', true)
            ->where('slot_start', '>=', now())
            ->whereColumn('booked_count', '<', 'capacity')
            ->orderBy('slot_start', 'asc')
            ->limit($limit)
            ->get();

        return $slots->map(fn ($s) => self::format($s))->all();
    }

    /**
     * Atomically reserve a slot for an order. Increments booked_count under lock.
     *
     * @throws \DomainException SLOT_FULL | SLOT_INACTIVE | SLOT_PAST | DUPLICATE
     */
    public static function reserve(int $slotId, int $orderId, int $buyerUserId): MarketplacePickupReservation
    {
        $tenantId = TenantContext::getId();

        return DB::transaction(function () use ($slotId, $orderId, $buyerUserId, $tenantId) {
            $slot = MarketplacePickupSlot::query()
                ->where('id', $slotId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (!$slot) {
                throw new \DomainException('SLOT_NOT_FOUND');
            }
            if (!$slot->is_active) {
                throw new \DomainException('SLOT_INACTIVE');
            }
            if ($slot->slot_start && $slot->slot_start->isPast()) {
                throw new \DomainException('SLOT_PAST');
            }
            if ($slot->booked_count >= $slot->capacity) {
                throw new \DomainException('SLOT_FULL');
            }

            $order = MarketplaceOrder::query()
                ->where('id', $orderId)
                ->where('tenant_id', $tenantId)
                ->where('buyer_id', $buyerUserId)
                ->first();
            if (!$order) {
                throw new \DomainException('ORDER_NOT_FOUND');
            }

            $existing = MarketplacePickupReservation::query()
                ->where('tenant_id', $tenantId)
                ->where('order_id', $orderId)
                ->whereIn('status', ['reserved', 'picked_up'])
                ->first();
            if ($existing) {
                throw new \DomainException('DUPLICATE_RESERVATION');
            }

            $slot->booked_count = $slot->booked_count + 1;
            $slot->save();

            $reservation = new MarketplacePickupReservation();
            $reservation->tenant_id = $tenantId;
            $reservation->slot_id = $slot->id;
            $reservation->listing_id = (int) $order->marketplace_listing_id;
            $reservation->order_id = $order->id;
            $reservation->buyer_user_id = $buyerUserId;
            $reservation->qr_code = self::generateQrCode();
            $reservation->status = 'reserved';
            $reservation->reserved_at = now();
            $reservation->save();

            return $reservation;
        });
    }

    /**
     * Seller scans a buyer's QR — verifies and marks picked up.
     *
     * @throws \DomainException QR_NOT_FOUND | NOT_FOR_SELLER | ALREADY_PICKED_UP
     */
    public static function scanQr(string $qrCode, int $sellerUserId): MarketplacePickupReservation
    {
        $tenantId = TenantContext::getId();

        return DB::transaction(function () use ($qrCode, $sellerUserId, $tenantId) {
            $reservation = MarketplacePickupReservation::query()
                ->where('tenant_id', $tenantId)
                ->where('qr_code', $qrCode)
                ->lockForUpdate()
                ->first();

            if (!$reservation) {
                throw new \DomainException('QR_NOT_FOUND');
            }

            $order = MarketplaceOrder::query()
                ->where('id', $reservation->order_id)
                ->where('tenant_id', $tenantId)
                ->first();
            if (!$order || (int) $order->seller_id !== $sellerUserId) {
                throw new \DomainException('NOT_FOR_SELLER');
            }

            if ($reservation->status === 'picked_up') {
                throw new \DomainException('ALREADY_PICKED_UP');
            }
            if ($reservation->status === 'cancelled') {
                throw new \DomainException('RESERVATION_CANCELLED');
            }

            $reservation->status = 'picked_up';
            $reservation->picked_up_at = now();
            $reservation->save();

            return $reservation;
        });
    }

    /**
     * Reservations belonging to the authenticated buyer (upcoming first).
     */
    public static function listForBuyer(int $buyerUserId, int $limit = 50): array
    {
        $tenantId = TenantContext::getId();
        $rows = MarketplacePickupReservation::query()
            ->where('tenant_id', $tenantId)
            ->where('buyer_user_id', $buyerUserId)
            ->with(['slot', 'listing:id,title'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => self::formatReservation($r))->all();
    }

    // -----------------------------------------------------------------
    //  Formatting
    // -----------------------------------------------------------------

    /** @return array<string,mixed> */
    private static function format(MarketplacePickupSlot $s): array
    {
        return [
            'id' => $s->id,
            'seller_id' => $s->seller_id,
            'slot_start' => $s->slot_start?->toISOString(),
            'slot_end' => $s->slot_end?->toISOString(),
            'capacity' => $s->capacity,
            'booked_count' => $s->booked_count,
            'remaining' => max(0, $s->capacity - $s->booked_count),
            'is_recurring' => (bool) $s->is_recurring,
            'recurring_pattern' => $s->recurring_pattern,
            'is_active' => (bool) $s->is_active,
        ];
    }

    /** @return array<string,mixed> */
    private static function formatReservation(MarketplacePickupReservation $r): array
    {
        return [
            'id' => $r->id,
            'slot_id' => $r->slot_id,
            'order_id' => $r->order_id,
            'listing_id' => $r->listing_id,
            'listing_title' => $r->listing?->title,
            'qr_code' => $r->qr_code,
            'status' => $r->status,
            'reserved_at' => $r->reserved_at?->toISOString(),
            'picked_up_at' => $r->picked_up_at?->toISOString(),
            'slot' => $r->slot ? [
                'slot_start' => $r->slot->slot_start?->toISOString(),
                'slot_end' => $r->slot->slot_end?->toISOString(),
            ] : null,
        ];
    }

    private static function generateQrCode(): string
    {
        // ULID-style — sortable, unique, URL-safe.
        return (string) Str::ulid();
    }
}
