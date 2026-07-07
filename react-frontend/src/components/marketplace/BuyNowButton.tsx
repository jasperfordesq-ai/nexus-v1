// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BuyNowButton — Creates a marketplace order and starts Stripe payment.
 */

import { lazy, Suspense, useState, useCallback, useEffect, useMemo } from 'react';
import CreditCard from 'lucide-react/icons/credit-card';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Select, SelectItem } from '@/components/ui/Select';
import { useDisclosure } from '@/components/ui/useDisclosure';
import { useAuth, useTenant, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { MarketplaceShippingOption } from '@/types/marketplace';

const StripeCheckoutModal = lazy(() =>
  import('./StripeCheckoutModal').then((module) => ({ default: module.StripeCheckoutModal })),
);

interface BuyNowButtonProps {
  listingId: number;
  offerId?: number;
  listingTitle?: string;
  price: number;
  currency: string;
  sellerId: number;
  onSuccess: () => void;
  className?: string;
  selectedShippingOption?: MarketplaceShippingOption | null;
  shippingRequired?: boolean;
  buttonLabelKey?: string;
  allowCoupons?: boolean;
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

export function BuyNowButton({
  listingId,
  offerId,
  listingTitle,
  price,
  currency,
  sellerId,
  onSuccess,
  className,
  selectedShippingOption,
  shippingRequired = false,
  buttonLabelKey = 'orders.buy.buy_now',
  allowCoupons = true,
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
  const [couponDiscountCents, setCouponDiscountCents] = useState(0);
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
        // Pickup is optional; the buy flow can continue without slots.
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [listingId]);

  const couponsEnabled = allowCoupons && hasFeature('merchant_coupons');
  const isOwnListing = user?.id === sellerId;
  const shippingCost = selectedShippingOption?.price ?? 0;
  const orderSubtotal = Math.max(0, price + shippingCost);
  const orderTotal = Math.max(0, orderSubtotal - couponDiscountCents / 100);
  const hasRequiredShipping = !shippingRequired || selectedShippingOption !== undefined;

  const priceFormatter = useMemo(
    () =>
      new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: currency || 'EUR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
      }),
    [currency]
  );
  const priceLabel = priceFormatter.format(orderTotal);
  const buttonAriaLabel = buttonLabelKey === 'orders.buy.buy_now'
    ? t('orders.buy.buy_now_aria', { price: priceLabel })
    : `${t(buttonLabelKey)} ${priceLabel}`;

  useEffect(() => {
    setCouponApplied(false);
    setCouponDiscountCents(0);
  }, [listingId, price, shippingCost]);

  const handleBuyNow = useCallback(async () => {
    if (isOwnListing || !user || !hasRequiredShipping) return;

    setIsProcessing(true);
    try {
      const orderPayload: Record<string, unknown> = { listing_id: listingId };
      if (offerId) {
        orderPayload.offer_id = offerId;
      }
      if (selectedShippingOption) {
        orderPayload.shipping_method = selectedShippingOption.courier_code || selectedShippingOption.courier_name;
        orderPayload.shipping_cost = selectedShippingOption.price;
      } else if (!shippingRequired) {
        orderPayload.shipping_method = 'pickup';
        orderPayload.shipping_cost = 0;
      }
      if (couponApplied && couponCode.trim()) {
        orderPayload.coupon_code = couponCode.trim().toUpperCase();
      }

      const orderRes = await api.post<CreateOrderResponse>('/v2/marketplace/orders', orderPayload);
      if (!orderRes.success || !orderRes.data?.id) {
        toast.error(orderRes.error || t('orders.buy.create_order_failed'));
        return;
      }

      const orderId = orderRes.data.id;
      if (selectedSlotId) {
        try {
          const reservationRes = await api.post(`/v2/marketplace/orders/${orderId}/pickup-reservation`, {
            slot_id: parseInt(selectedSlotId, 10),
          });
          if (!reservationRes.success) {
            toast.error(reservationRes.error || t('pickup.reservation_failed'));
            return;
          }
        } catch (err) {
          logError('BuyNowButton: pickup reservation failed', err);
          toast.error(t('pickup.reservation_failed'));
          return;
        }
      }

      const intentRes = await api.post<CreateIntentResponse>('/v2/marketplace/payments/create-intent', {
        order_id: orderId,
      });
      if (!intentRes.success || !intentRes.data) {
        toast.error(intentRes.error || t('orders.buy.payment_failed'));
        return;
      }

      if (intentRes.data.client_secret) {
        setClientSecret(intentRes.data.client_secret);
        checkoutModal.onOpen();
        return;
      }
      if (intentRes.data.checkout_url) {
        window.location.href = intentRes.data.checkout_url;
        return;
      }

      toast.info(t('orders.buy.contact_seller'));
      onSuccess();
    } catch (err) {
      logError('BuyNowButton: payment flow failed', err);
      toast.error(t('orders.buy.error'));
    } finally {
      setIsProcessing(false);
    }
  }, [
    listingId,
    offerId,
    isOwnListing,
    user,
    hasRequiredShipping,
    selectedShippingOption,
    shippingRequired,
    toast,
    onSuccess,
    checkoutModal,
    t,
    couponApplied,
    couponCode,
    selectedSlotId,
  ]);

  const handleApplyCoupon = async () => {
    if (!couponCode.trim()) return;
    try {
      const res = await api.post<{ discount_cents: number }>('/v2/coupons/validate', {
        code: couponCode.trim().toUpperCase(),
        order_total_cents: Math.round(orderSubtotal * 100),
        listing_id: listingId,
      });
      if (res.success) {
        setCouponDiscountCents(Math.max(0, res.data?.discount_cents ?? 0));
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
      return s.slot_start ? new Date(s.slot_start).toLocaleString() : t('pickup.slot_fallback', { id: s.id });
    } catch {
      return t('pickup.slot_fallback', { id: s.id });
    }
  };

  return (
    <>
      {pickupSlots.length > 0 && !isOwnListing && user && (
        <div className="mb-2">
          <Select
            size="sm"
            label={t('pickup.choose_slot')}
            placeholder={t('pickup.no_slot_selected')}
            selectedKeys={selectedSlotId ? [selectedSlotId] : []}
            onSelectionChange={(keys) => {
              const v = Array.from(keys)[0];
              setSelectedSlotId(v ? String(v) : '');
            }}
          >
            {pickupSlots.map((s) => (
              <SelectItem key={String(s.id)} id={String(s.id)} textValue={formatSlotLabel(s)}>
                {formatSlotLabel(s)} ({s.remaining} {t('pickup.left')})
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
            aria-label={tCommon('coupon.enter_code')}
            value={couponCode}
            onValueChange={(v) => {
              setCouponCode(v);
              if (couponApplied) {
                setCouponApplied(false);
                setCouponDiscountCents(0);
              }
            }}
            isDisabled={couponApplied}
          />
          <Button
            size="sm"
            variant="tertiary"
            onPress={handleApplyCoupon}
            isDisabled={!couponCode.trim() || couponApplied}
          >
            {couponApplied ? tCommon('coupon.applied') : tCommon('coupon.apply')}
          </Button>
        </div>
      )}
      {(shippingCost > 0 || couponDiscountCents > 0) && (
        <div className="mb-2 space-y-1 rounded-md border border-separator bg-surface-secondary p-3 text-sm">
          {shippingCost > 0 && (
            <div className="flex justify-between gap-3">
              <span className="text-muted">{t('checkout.shipping')}</span>
              <span className="font-medium text-foreground">{priceFormatter.format(shippingCost)}</span>
            </div>
          )}
          {couponDiscountCents > 0 && (
            <div className="flex justify-between gap-3">
              <span className="text-muted">{t('checkout.discount')}</span>
              <span className="font-medium text-success">-{priceFormatter.format(couponDiscountCents / 100)}</span>
            </div>
          )}
          <div className="flex justify-between gap-3 border-t border-separator pt-1">
            <span className="font-medium text-foreground">{t('checkout.total')}</span>
            <span className="font-semibold text-foreground">{priceLabel}</span>
          </div>
        </div>
      )}
      <Button
        variant="secondary"
        fullWidth
        startContent={!isProcessing ? <CreditCard className="w-4 h-4" /> : undefined}
        onPress={handleBuyNow}
        isLoading={isProcessing}
        isDisabled={isOwnListing || !user || !hasRequiredShipping}
        className={className}
        aria-label={buttonAriaLabel}
      >
        {t(buttonLabelKey)} {priceLabel}
      </Button>
      {!hasRequiredShipping && (
        <p className="text-xs text-danger text-center">{t('shipping.choose_required')}</p>
      )}

      {clientSecret && checkoutModal.isOpen && (
        <Suspense fallback={null}>
          <StripeCheckoutModal
            isOpen={checkoutModal.isOpen}
            clientSecret={clientSecret}
            amount={orderTotal}
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
        </Suspense>
      )}
    </>
  );
}

export default BuyNowButton;
