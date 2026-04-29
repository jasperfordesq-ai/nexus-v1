// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BuyNowButton — Creates a marketplace order + Stripe payment intent,
 * then redirects to Stripe Checkout (or shows fallback for non-Stripe tenants).
 *
 * Flow:
 *   1. POST /v2/marketplace/orders { listing_id } → order
 *   2. POST /v2/marketplace/payments/create-intent { order_id } → { checkout_url }
 *   3. Redirect to Stripe-hosted checkout page
 *
 * Fallback: If Stripe is unavailable (no checkout_url), shows a message
 * telling the buyer to contact the seller to arrange payment.
 */

import { useState, useCallback, useEffect } from 'react';
import { Button, Input, useDisclosure, Select, SelectItem } from '@heroui/react';
import CreditCard from 'lucide-react/icons/credit-card';
import { useTranslation } from 'react-i18next';
import { useAuth, useTenant, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { StripeCheckoutModal } from './StripeCheckoutModal';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface BuyNowButtonProps {
  listingId: number;
  listingTitle?: string;
  price: number;
  currency: string;
  sellerId: number;
  onSuccess: () => void;
  className?: string;
}

interface CreateOrderResponse {
  id: number;
  order_number: string;
  status: string;
}

interface CreateIntentResponse {
  checkout_url?: string;
  client_secret?: string;
}

interface PickupSlotOption {
  id: number;
  slot_start: string | null;
  slot_end: string | null;
  remaining: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function BuyNowButton({
  listingId,
  listingTitle,
  price,
  currency,
  sellerId,
  onSuccess,
  className,
}: BuyNowButtonProps) {
  const { t } = useTranslation('marketplace');
  const { t: tCommon } = useTranslation('common');
  const { user } = useAuth();
  const { hasFeature } = useTenant();
  const toast = useToast();
  const checkoutModal = useDisclosure();

  const [isProcessing, setIsProcessing] = useState(false);
  const [clientSecret, setClientSecret] = useState<string | null>(null);
  const [couponCode, setCouponCode] = useState('');
  const [couponApplied, setCouponApplied] = useState(false);

  // AG45 — pickup slots
  const [pickupSlots, setPickupSlots] = useState<PickupSlotOption[]>([]);
  const [selectedSlotId, setSelectedSlotId] = useState<string>('');

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const res = await api.get<PickupSlotOption[]>(
          `/v2/marketplace/listings/${listingId}/pickup-slots`,
        );
        if (!cancelled && res.success && Array.isArray(res.data)) {
          setPickupSlots(res.data);
        }
      } catch {
        /* non-fatal — pickup is optional */
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [listingId]);

  const couponsEnabled = hasFeature('merchant_coupons');

  const isOwnListing = user?.id === sellerId;

  const handleBuyNow = useCallback(async () => {
    if (isOwnListing || !user) return;

    setIsProcessing(true);
    try {
      // Step 1: Create the order (include coupon code if applied)
      const orderPayload: Record<string, unknown> = { listing_id: listingId };
      if (couponApplied && couponCode.trim()) {
        orderPayload.coupon_code = couponCode.trim().toUpperCase();
      }
      const orderRes = await api.post<CreateOrderResponse>('/v2/marketplace/orders', orderPayload);

      if (!orderRes.success || !orderRes.data?.id) {
        toast.error(orderRes.error || t('orders.buy.create_order_failed', 'Failed to create order'));
        return;
      }

      const orderId = orderRes.data.id;

      // AG45 — Reserve pickup slot if one was chosen
      if (selectedSlotId) {
        try {
          await api.post(`/v2/marketplace/orders/${orderId}/pickup-reservation`, {
            slot_id: parseInt(selectedSlotId, 10),
          });
        } catch (err) {
          logError('BuyNowButton: pickup reservation failed', err);
          // non-fatal — order proceeds, buyer can still arrange pickup later
        }
      }

      // Step 2: Create Stripe payment intent / checkout session
      const intentRes = await api.post<CreateIntentResponse>('/v2/marketplace/payments/create-intent', {
        order_id: orderId,
      });

      if (!intentRes.success || !intentRes.data) {
        toast.error(intentRes.error || t('orders.buy.payment_failed', 'Failed to create payment session'));
        return;
      }

      // Step 3a: If a client_secret is returned, open the embedded checkout modal
      if (intentRes.data.client_secret) {
        setClientSecret(intentRes.data.client_secret);
        checkoutModal.onOpen();
        return;
      }

      // Step 3b: Redirect to Stripe Checkout if available
      if (intentRes.data.checkout_url) {
        window.location.href = intentRes.data.checkout_url;
        return;
      }

      // Fallback: No Stripe checkout URL available
      toast.info(
        t('orders.buy.contact_seller', 'Order created. Please contact the seller to arrange payment.'),
      );
      onSuccess();
    } catch (err) {
      logError('BuyNowButton: payment flow failed', err);
      toast.error(t('orders.buy.error', 'Something went wrong. Please try again.'));
    } finally {
      setIsProcessing(false);
    }
  }, [listingId, isOwnListing, user, toast, onSuccess, checkoutModal, t, couponApplied, couponCode, selectedSlotId])

  // Format price for button label
  const priceLabel = new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: currency || 'EUR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(price);

  const handleApplyCoupon = async () => {
    if (!couponCode.trim()) return;
    try {
      const res = await api.post<{ discount_cents: number }>('/v2/coupons/validate', {
        code: couponCode.trim().toUpperCase(),
        order_total_cents: Math.round(price * 100),
        listing_id: listingId,
      });
      if (res.success) {
        setCouponApplied(true);
        toast.success(tCommon('coupon.applied'));
      } else {
        toast.error(tCommon('coupon.invalid_code'));
      }
    } catch {
      toast.error(tCommon('coupon.invalid_code'));
    }
  };

  const formatSlotLabel = (s: PickupSlotOption) => {
    try {
      return s.slot_start ? new Date(s.slot_start).toLocaleString() : `Slot #${s.id}`;
    } catch {
      return `Slot #${s.id}`;
    }
  };

  return (
    <>
      {pickupSlots.length > 0 && !isOwnListing && user && (
        <div className="mb-2">
          <Select
            size="sm"
            label={t('marketplace.pickup.choose_slot', 'Choose pickup slot')}
            placeholder={t('marketplace.pickup.no_slot_selected', 'No pickup slot')}
            selectedKeys={selectedSlotId ? [selectedSlotId] : []}
            onSelectionChange={(keys) => {
              const v = Array.from(keys)[0];
              setSelectedSlotId(v ? String(v) : '');
            }}
          >
            {pickupSlots.map((s) => (
              <SelectItem key={String(s.id)} textValue={formatSlotLabel(s)}>
                {formatSlotLabel(s)} ({s.remaining} {t('marketplace.pickup.left', 'left')})
              </SelectItem>
            ))}
          </Select>
        </div>
      )}
      {couponsEnabled && !isOwnListing && user && (
        <div className="flex gap-2 mb-2">
          <Input
            size="sm"
            placeholder={tCommon('coupon.enter_code')}
            value={couponCode}
            onValueChange={(v) => {
              setCouponCode(v);
              if (couponApplied) setCouponApplied(false);
            }}
            isDisabled={couponApplied}
          />
          <Button
            size="sm"
            variant="flat"
            onPress={handleApplyCoupon}
            isDisabled={!couponCode.trim() || couponApplied}
          >
            {couponApplied ? tCommon('coupon.applied') : tCommon('coupon.apply')}
          </Button>
        </div>
      )}
      <Button
        color="success"
        variant="solid"
        fullWidth
        startContent={!isProcessing ? <CreditCard className="w-4 h-4" /> : undefined}
        onPress={handleBuyNow}
        isLoading={isProcessing}
        isDisabled={isOwnListing || !user}
        className={className}
        aria-label={t('orders.buy.buy_now_aria', 'Buy now for {{price}}', { price: priceLabel })}
      >
        {t('orders.buy.buy_now', 'Buy Now')} {priceLabel}
      </Button>

      {/* Embedded Stripe Checkout Modal */}
      {clientSecret && (
        <StripeCheckoutModal
          isOpen={checkoutModal.isOpen}
          clientSecret={clientSecret}
          amount={price}
          currency={currency}
          listingTitle={listingTitle}
          onSuccess={() => {
            checkoutModal.onClose();
            setClientSecret(null);
            onSuccess();
          }}
          onClose={() => {
            checkoutModal.onClose();
            setClientSecret(null);
          }}
        />
      )}
    </>
  );
}

export default BuyNowButton;
