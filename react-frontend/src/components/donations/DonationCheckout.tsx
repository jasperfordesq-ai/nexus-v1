// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * DonationCheckout - Multi-step modal for making a Stripe donation
 *
 * Step 1: Form (amount, currency, message, anonymous toggle)
 * Step 2: Stripe payment via StripePaymentForm
 * Step 3: Success confirmation with receipt link
 */

import { useState } from 'react';
import {
  Button,
  Input,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Select,
  SelectItem,
  Switch,
  Textarea,
} from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Banknote from 'lucide-react/icons/banknote';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import CreditCard from 'lucide-react/icons/credit-card';
import FileText from 'lucide-react/icons/file-text';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { detectTenantFromUrl, tenantPath } from '@/lib/tenant-routing';
import { useToast } from '@/contexts';
import { StripePaymentForm } from './StripePaymentForm';

/* ───────────────────────── Props ───────────────────────── */

interface DonationCheckoutProps {
  isOpen: boolean;
  onClose: () => void;
  givingDayId?: number;
  opportunityId?: number;
  onDonationComplete?: () => void;
}

/* ───────────────────────── Types ───────────────────────── */

type CheckoutStep = 'form' | 'payment' | 'success';

interface PaymentIntentResponse {
  client_secret: string;
  donation_id: number;
}

const CURRENCIES = [
  { key: 'EUR', labelKey: 'donations.currencies.EUR' },
  { key: 'GBP', labelKey: 'donations.currencies.GBP' },
  { key: 'USD', labelKey: 'donations.currencies.USD' },
];

/* ───────────────────────── Component ───────────────────────── */

export function DonationCheckout({
  isOpen,
  onClose,
  givingDayId,
  opportunityId,
  onDonationComplete,
}: DonationCheckoutProps) {
  const { t } = useTranslation('volunteering');
  const toast = useToast();

  // Form state
  const [amount, setAmount] = useState('');
  const [currency, setCurrency] = useState('EUR');
  const [message, setMessage] = useState('');
  const [isAnonymous, setIsAnonymous] = useState(false);

  // Checkout flow state
  const [step, setStep] = useState<CheckoutStep>('form');
  const [clientSecret, setClientSecret] = useState('');
  const [donationId, setDonationId] = useState<number | null>(null);
  const [isCreatingIntent, setIsCreatingIntent] = useState(false);

  const resetForm = () => {
    setAmount('');
    setCurrency('EUR');
    setMessage('');
    setIsAnonymous(false);
    setStep('form');
    setClientSecret('');
    setDonationId(null);
    setIsCreatingIntent(false);
  };

  const handleClose = () => {
    resetForm();
    onClose();
  };

  const handleContinueToPayment = async () => {
    const numAmount = parseFloat(amount);
    if (!amount || isNaN(numAmount) || numAmount < 0.5) {
      toast.error(t('donations.min_amount'));
      return;
    }

    try {
      setIsCreatingIntent(true);

      const response = await api.post<PaymentIntentResponse>('/v2/donations/payment-intent', {
        amount: numAmount,
        currency,
        giving_day_id: givingDayId ?? null,
        opportunity_id: opportunityId ?? null,
        message: message || null,
        is_anonymous: isAnonymous,
      });

      if (response.success && response.data) {
        setClientSecret(response.data.client_secret);
        setDonationId(response.data.donation_id);
        setStep('payment');
      } else {
        logError('Donation payment intent API error', response.error);
        toast.error(t('donations.intent_error'));
      }
    } catch (err) {
      logError('Failed to create payment intent', err);
      toast.error(t('donations.intent_error'));
    } finally {
      setIsCreatingIntent(false);
    }
  };

  const handlePaymentSuccess = () => {
    setStep('success');
    onDonationComplete?.();
  };

  const handlePaymentError = (error: string) => {
    logError('Donation payment failed', error);
    toast.error(t('donations.payment_error'));
  };

  const formattedAmount = amount
    ? parseFloat(amount).toLocaleString(undefined, {
        style: 'currency',
        currency,
        minimumFractionDigits: 2,
      })
    : '';

  return (
    <Modal
      isOpen={isOpen}
      onOpenChange={(open) => {
        if (!open) handleClose();
      }}
      size="lg"
      classNames={{
        base: 'bg-content1 border border-theme-default',
        header: 'border-b border-theme-default',
        footer: 'border-t border-theme-default',
      }}
      isDismissable={step !== 'payment'}
    >
      <ModalContent>
        {() => (
          <>
            <ModalHeader className="text-theme-primary flex items-center gap-2">
              {step === 'success' ? (
                <CheckCircle className="w-5 h-5 text-[var(--color-success)]" aria-hidden="true" />
              ) : (
                <CreditCard className="w-5 h-5" aria-hidden="true" />
              )}
              {step === 'success'
                ? t('donations.success_title')
                : t('donations.checkout_title')}
            </ModalHeader>

            <ModalBody className="gap-4 py-4">
              {/* ── Step 1: Form ── */}
              {step === 'form' && (
                <>
                  <Input
                    label={t('donations.amount_label')}
                    type="number"
                    min="0.50"
                    step="0.01"
                    variant="bordered"
                    value={amount}
                    onValueChange={setAmount}
                    startContent={<Banknote className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                    isRequired
                    placeholder={t('donations.placeholder_amount')}
                  />

                  <Select
                    label={t('donations.currency_label')}
                    variant="bordered"
                    selectedKeys={[currency]}
                    onSelectionChange={(keys) => {
                      const selected = Array.from(keys)[0] as string;
                      if (selected) setCurrency(selected);
                    }}
                  >
                    {CURRENCIES.map((c) => (
                      <SelectItem key={c.key}>{t(c.labelKey)}</SelectItem>
                    ))}
                  </Select>

                  <Textarea
                    label={t('donations.message_label')}
                    variant="bordered"
                    value={message}
                    onValueChange={setMessage}
                    maxRows={3}
                    placeholder={t('donations.message_placeholder')}
                  />

                  <Switch
                    isSelected={isAnonymous}
                    onValueChange={setIsAnonymous}
                    size="sm"
                    classNames={{
                      base: 'flex w-full max-w-none flex-row-reverse justify-between rounded-xl border border-theme-default bg-content2/60 p-3',
                      label: 'text-sm text-theme-secondary',
                    }}
                  >
                    {t('donations.anonymous_toggle')}
                  </Switch>
                </>
              )}

              {/* ── Step 2: Stripe Payment ── */}
              {step === 'payment' && clientSecret && (
                <div className="space-y-4">
                  <div className="text-center text-sm text-theme-muted mb-2">
                    {t('donations.paying_amount', { amount: formattedAmount })}
                  </div>
                  <StripePaymentForm
                    clientSecret={clientSecret}
                    onSuccess={handlePaymentSuccess}
                    onError={handlePaymentError}
                  />
                </div>
              )}

              {/* ── Step 3: Success ── */}
              {step === 'success' && (
                <div className="text-center py-6 space-y-4">
                  <CheckCircle className="w-16 h-16 text-[var(--color-success)] mx-auto" aria-hidden="true" />
                  <div>
                    <p className="text-lg font-semibold text-theme-primary">
                      {t('donations.success_title')}
                    </p>
                    <p className="text-sm text-theme-muted mt-1">
                      {t('donations.success_message', {
                        amount: formattedAmount,
                      })}
                    </p>
                  </div>
                  {donationId && (
                    <Button
                      variant="flat"
                      size="sm"
                      startContent={<FileText className="w-4 h-4" aria-hidden="true" />}
                      onPress={() => {
                        const detected = detectTenantFromUrl();
                        const receiptPath = detected.source === 'path'
                          ? tenantPath(`/donations/${donationId}/receipt`, detected.slug)
                          : `/donations/${donationId}/receipt`;
                        window.open(receiptPath, '_blank');
                      }}
                    >
                      {t('donations.view_receipt')}
                    </Button>
                  )}
                </div>
              )}
            </ModalBody>

            <ModalFooter>
              {step === 'form' && (
                <>
                  <Button variant="flat" onPress={handleClose}>
                    {t('donations.cancel')}
                  </Button>
                  <Button
                    color="primary"
                    onPress={handleContinueToPayment}
                    isLoading={isCreatingIntent}
                    isDisabled={!amount || parseFloat(amount) < 0.5}
                  >
                    {t('donations.continue_to_payment')}
                  </Button>
                </>
              )}

              {step === 'payment' && (
                <Button
                  variant="flat"
                  startContent={<ArrowLeft className="w-4 h-4" aria-hidden="true" />}
                  onPress={() => setStep('form')}
                >
                  {t('donations.back')}
                </Button>
              )}

              {step === 'success' && (
                <Button
                  color="primary"
                  onPress={handleClose}
                >
                  {t('donations.close')}
                </Button>
              )}
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
}

export default DonationCheckout;
