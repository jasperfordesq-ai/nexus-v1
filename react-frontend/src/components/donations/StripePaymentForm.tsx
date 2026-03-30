// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * StripePaymentForm - Stripe Elements payment form component
 *
 * Wraps Stripe PaymentElement in an Elements provider and handles
 * payment confirmation with loading/error states.
 */

import { useState } from 'react';
import { loadStripe } from '@stripe/stripe-js';
import {
  Elements,
  PaymentElement,
  useStripe,
  useElements,
} from '@stripe/react-stripe-js';
import { Button, Chip } from '@heroui/react';
import { CreditCard, AlertTriangle } from 'lucide-react';
import { useTranslation } from 'react-i18next';

/* ───────────────────────── Stripe init (module-level, called once) ───────────────────────── */

const stripeKey = import.meta.env.VITE_STRIPE_PUBLISHABLE_KEY || '';
const stripePromise = stripeKey ? loadStripe(stripeKey) : null;

/* ───────────────────────── Props ───────────────────────── */

interface StripePaymentFormProps {
  clientSecret: string;
  onSuccess: () => void;
  onError: (error: string) => void;
}

/* ───────────────────────── Inner form (must be inside Elements) ───────────────────────── */

function PaymentForm({ onSuccess, onError }: Omit<StripePaymentFormProps, 'clientSecret'>) {
  const { t } = useTranslation('volunteering');
  const stripe = useStripe();
  const elements = useElements();
  const [isProcessing, setIsProcessing] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!stripe || !elements) {
      return;
    }

    try {
      setIsProcessing(true);
      setErrorMessage(null);

      const { error, paymentIntent } = await stripe.confirmPayment({
        elements,
        confirmParams: {
          return_url: window.location.href,
        },
        redirect: 'if_required',
      });

      if (error) {
        const msg = error.message || t('donations.payment_failed', 'Payment failed. Please try again.');
        setErrorMessage(msg);
        onError(msg);
      } else if (paymentIntent && paymentIntent.status === 'succeeded') {
        onSuccess();
      } else if (paymentIntent && paymentIntent.status === 'requires_action') {
        // 3D Secure or other action — Stripe handles this automatically
        setErrorMessage(t('donations.payment_action_required', 'Additional authentication required.'));
      } else {
        const msg = t('donations.payment_unexpected', 'Unexpected payment status. Please contact support.');
        setErrorMessage(msg);
        onError(msg);
      }
    } catch (err) {
      const msg = t('donations.payment_error', 'An error occurred during payment.');
      setErrorMessage(msg);
      onError(msg);
    } finally {
      setIsProcessing(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <PaymentElement
        options={{
          layout: 'tabs',
        }}
      />

      {errorMessage && (
        <Chip
          color="danger"
          variant="flat"
          startContent={<AlertTriangle className="w-3 h-3" />}
          className="w-full max-w-full"
        >
          {errorMessage}
        </Chip>
      )}

      <Button
        type="submit"
        className="w-full bg-gradient-to-r from-rose-500 to-pink-600 text-white"
        isLoading={isProcessing}
        isDisabled={!stripe || !elements || isProcessing}
        startContent={!isProcessing ? <CreditCard className="w-4 h-4" aria-hidden="true" /> : undefined}
      >
        {isProcessing
          ? t('donations.processing', 'Processing...')
          : t('donations.pay_now', 'Pay Now')}
      </Button>
    </form>
  );
}

/* ───────────────────────── Exported wrapper ───────────────────────── */

export function StripePaymentForm({ clientSecret, onSuccess, onError }: StripePaymentFormProps) {
  return (
    <Elements
      stripe={stripePromise}
      options={{
        clientSecret,
        appearance: {
          theme: 'stripe',
          variables: {
            borderRadius: '8px',
          },
        },
      }}
    >
      <PaymentForm onSuccess={onSuccess} onError={onError} />
    </Elements>
  );
}

export default StripePaymentForm;
