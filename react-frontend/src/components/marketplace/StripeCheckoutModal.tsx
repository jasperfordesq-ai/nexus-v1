// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * StripeCheckoutModal — Embedded Stripe Payment Element modal.
 *
 * Wraps Stripe's PaymentElement inside an Elements provider with a
 * client secret. Handles loading, error, and success states.
 *
 * If Stripe.js packages are unavailable (e.g. stripped for a build),
 * the component renders a graceful fallback message instead.
 */

import { useState, useCallback, useMemo } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Divider,
  Spinner,
} from '@heroui/react';
import CreditCard from 'lucide-react/icons/credit-card';
import ShieldCheck from 'lucide-react/icons/shield-check';
import AlertCircle from 'lucide-react/icons/circle-alert';
import { useTranslation } from 'react-i18next';

// ─────────────────────────────────────────────────────────────────────────────
// Stripe imports — packages are installed via @stripe/react-stripe-js
// ─────────────────────────────────────────────────────────────────────────────

import { loadStripe } from '@stripe/stripe-js';
import {
  Elements,
  PaymentElement,
  useStripe,
  useElements,
} from '@stripe/react-stripe-js';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface StripeCheckoutModalProps {
  isOpen: boolean;
  clientSecret: string;
  amount: number;
  currency: string;
  listingTitle?: string;
  shippingCost?: number;
  onSuccess: () => void;
  onClose: () => void;
}

// ─────────────────────────────────────────────────────────────────────────────
// Price formatter
// ─────────────────────────────────────────────────────────────────────────────

function formatAmount(amount: number, currency: string): string {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency: currency || 'EUR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(amount);
}

// ─────────────────────────────────────────────────────────────────────────────
// Inner form (rendered inside <Elements>)
// ─────────────────────────────────────────────────────────────────────────────

function CheckoutForm({
  amount,
  currency,
  listingTitle,
  shippingCost,
  onSuccess,
  onClose,
}: Omit<StripeCheckoutModalProps, 'isOpen' | 'clientSecret'>) {
  const { t } = useTranslation('marketplace');
  const stripe = useStripe();
  const elements = useElements();

  const [isProcessing, setIsProcessing] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [isReady, setIsReady] = useState(false);

  const totalAmount = amount + (shippingCost ?? 0);

  const handleSubmit = useCallback(async () => {
    if (!stripe || !elements) return;

    setIsProcessing(true);
    setErrorMessage(null);

    try {
      const { error, paymentIntent } = await stripe.confirmPayment({
        elements,
        confirmParams: {
          return_url: window.location.href,
        },
        redirect: 'if_required',
      });

      if (error) {
        setErrorMessage(error.message || t('checkout.payment_failed', 'Payment failed. Please try again.'));
      } else if (paymentIntent?.status === 'succeeded') {
        onSuccess();
      } else {
        // Payment requires additional action or is processing
        setErrorMessage(t('checkout.payment_processing', 'Payment is being processed. You will be notified when complete.'));
      }
    } catch {
      setErrorMessage(t('checkout.payment_error', 'An unexpected error occurred. Please try again.'));
    } finally {
      setIsProcessing(false);
    }
  }, [stripe, elements, onSuccess, t]);

  return (
    <>
      <ModalBody className="space-y-4">
        {/* Order summary */}
        <div className="bg-default-50 rounded-xl p-4 space-y-2">
          <h4 className="text-sm font-semibold text-default-500 uppercase tracking-wide">
            {t('checkout.order_summary', 'Order Summary')}
          </h4>
          {listingTitle && (
            <div className="flex justify-between text-sm">
              <span className="text-foreground truncate mr-2">{listingTitle}</span>
              <span className="font-medium text-foreground shrink-0">{formatAmount(amount, currency)}</span>
            </div>
          )}
          {shippingCost != null && shippingCost > 0 && (
            <div className="flex justify-between text-sm">
              <span className="text-default-500">{t('checkout.shipping', 'Shipping')}</span>
              <span className="text-foreground">{formatAmount(shippingCost, currency)}</span>
            </div>
          )}
          <Divider />
          <div className="flex justify-between">
            <span className="font-semibold text-foreground">{t('checkout.total', 'Total')}</span>
            <span className="font-bold text-lg text-primary">{formatAmount(totalAmount, currency)}</span>
          </div>
        </div>

        {/* Stripe Payment Element */}
        <div className="min-h-[200px]">
          {!isReady && (
            <div className="flex justify-center py-8">
              <Spinner size="lg" color="primary" />
            </div>
          )}
          {PaymentElement && (
            <PaymentElement
              onReady={() => setIsReady(true)}
              options={{
                layout: 'tabs',
              }}
            />
          )}
        </div>

        {/* Error message */}
        {errorMessage && (
          <div className="flex items-start gap-2 p-3 rounded-lg bg-danger-50 text-danger text-sm">
            <AlertCircle className="w-4 h-4 mt-0.5 shrink-0" />
            <span>{errorMessage}</span>
          </div>
        )}

        {/* Security notice */}
        <div className="flex items-center gap-2 text-xs text-default-400">
          <ShieldCheck className="w-4 h-4 shrink-0" />
          <span>{t('checkout.secure_notice', 'Payments are processed securely by Stripe. Your card details are never stored on our servers.')}</span>
        </div>
      </ModalBody>

      <ModalFooter>
        <Button variant="flat" onPress={onClose} isDisabled={isProcessing}>
          {t('checkout.cancel', 'Cancel')}
        </Button>
        <Button
          color="success"
          onPress={handleSubmit}
          isLoading={isProcessing}
          isDisabled={!stripe || !elements || !isReady}
          startContent={!isProcessing ? <CreditCard className="w-4 h-4" /> : undefined}
        >
          {t('checkout.pay_now', 'Pay {{amount}}', { amount: formatAmount(totalAmount, currency) })}
        </Button>
      </ModalFooter>
    </>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

const STRIPE_PK = import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY || '';

export function StripeCheckoutModal({
  isOpen,
  clientSecret,
  amount,
  currency,
  listingTitle,
  shippingCost,
  onSuccess,
  onClose,
}: StripeCheckoutModalProps) {
  const { t } = useTranslation('marketplace');

  // Memoize the Stripe promise so it is only created once
  const stripePromise = useMemo(() => {
    if (!loadStripe || !STRIPE_PK) return null;
    return loadStripe(STRIPE_PK);
  }, []);

  // Fallback UI when Stripe publishable key is not configured
  const stripeUnavailable = !STRIPE_PK;

  return (
    <Modal isOpen={isOpen} onOpenChange={(open) => { if (!open) onClose(); }} placement="center" size="lg">
      <ModalContent>
        {() => (
          <>
            <ModalHeader className="flex items-center gap-2">
              <CreditCard className="w-5 h-5 text-primary" />
              {t('checkout.title', 'Secure Checkout')}
            </ModalHeader>

            {stripeUnavailable ? (
              <>
                <ModalBody>
                  <div className="text-center py-6 space-y-3">
                    <AlertCircle className="w-12 h-12 text-warning mx-auto" />
                    <p className="text-foreground font-medium">
                      {t('checkout.not_available_title', 'Payment Processing Being Set Up')}
                    </p>
                    <p className="text-sm text-default-500">
                      {t(
                        'checkout.not_available_description',
                        'Payment processing is being set up. Please contact the seller to arrange payment.',
                      )}
                    </p>
                  </div>
                </ModalBody>
                <ModalFooter>
                  <Button variant="flat" onPress={onClose}>
                    {t('checkout.close', 'Close')}
                  </Button>
                </ModalFooter>
              </>
            ) : (
              <Elements
                stripe={stripePromise}
                options={{
                  clientSecret,
                  appearance: {
                    theme: 'stripe',
                    variables: {
                      colorPrimary: '#006FEE',
                      borderRadius: '12px',
                    },
                  },
                }}
              >
                <CheckoutForm
                  amount={amount}
                  currency={currency}
                  listingTitle={listingTitle}
                  shippingCost={shippingCost}
                  onSuccess={onSuccess}
                  onClose={onClose}
                />
              </Elements>
            )}
          </>
        )}
      </ModalContent>
    </Modal>
  );
}

export default StripeCheckoutModal;
