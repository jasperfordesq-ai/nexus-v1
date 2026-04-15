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

import { useState, useCallback } from 'react';
import { Button, useDisclosure } from '@heroui/react';
import { CreditCard } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useAuth, useToast } from '@/contexts';
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
  const { user } = useAuth();
  const toast = useToast();
  const checkoutModal = useDisclosure();

  const [isProcessing, setIsProcessing] = useState(false);
  const [clientSecret, setClientSecret] = useState<string | null>(null);

  const isOwnListing = user?.id === sellerId;

  const handleBuyNow = useCallback(async () => {
    if (isOwnListing || !user) return;

    setIsProcessing(true);
    try {
      // Step 1: Create the order
      const orderRes = await api.post<CreateOrderResponse>('/v2/marketplace/orders', {
        listing_id: listingId,
      });

      if (!orderRes.success || !orderRes.data?.id) {
        toast.error(orderRes.error || t('orders.buy.create_order_failed', 'Failed to create order'));
        return;
      }

      const orderId = orderRes.data.id;

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
  }, [listingId, isOwnListing, user, toast, onSuccess, checkoutModal, t])

  // Format price for button label
  const priceLabel = new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: currency || 'EUR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(price);

  return (
    <>
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
