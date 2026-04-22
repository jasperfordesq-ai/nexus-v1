// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MakeOfferForm - Offer submission form for marketplace listings
 *
 * Renders inside a modal. Includes offer amount input, optional message,
 * price comparison, and submits via the marketplace offers API endpoint.
 */

import { useState, useCallback } from 'react';
import { Button, Input, Textarea } from '@heroui/react';
import Send from 'lucide-react/icons/send';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface MakeOfferFormProps {
  listingId: number;
  listingPrice: number | null;
  currency: string;
  onSuccess: () => void;
  onClose: () => void;
}

const MAX_MESSAGE_LENGTH = 500;

export function MakeOfferForm({
  listingId,
  listingPrice,
  currency,
  onSuccess,
  onClose,
}: MakeOfferFormProps) {
  const { t } = useTranslation('marketplace');
  const toast = useToast();
  const [amount, setAmount] = useState('');
  const [message, setMessage] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  const parsedAmount = parseFloat(amount);
  const isValidAmount = !isNaN(parsedAmount) && parsedAmount > 0;

  const handleSubmit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();

      if (!isValidAmount) return;

      try {
        setIsSubmitting(true);
        const response = await api.post(`/v2/marketplace/listings/${listingId}/offers`, {
          amount: parsedAmount,
          currency,
          message: message.trim() || undefined,
        });

        if (response.success) {
          toast.success(t('offer.sent_success', 'Your offer has been sent!'));
          onSuccess();
        } else {
          toast.error(
            (response as { message?: string }).message ||
              t('offer.sent_error', 'Failed to send offer. Please try again.'),
          );
        }
      } catch (err) {
        logError('Failed to submit marketplace offer', err);
        toast.error(t('offer.sent_error', 'Failed to send offer. Please try again.'));
      } finally {
        setIsSubmitting(false);
      }
    },
    [isValidAmount, parsedAmount, currency, message, listingId, toast, t, onSuccess],
  );

  const comparisonPercent =
    listingPrice && isValidAmount
      ? Math.round(((parsedAmount - listingPrice) / listingPrice) * 100)
      : null;

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {/* Offer amount */}
      <Input
        label={t('offer.your_offer', 'Your Offer')}
        type="number"
        min={0}
        step="0.01"
        value={amount}
        onValueChange={setAmount}
        startContent={
          <span className="text-sm text-theme-muted">{currency}</span>
        }
        variant="bordered"
        isRequired
        autoFocus
      />

      {/* Price comparison */}
      {listingPrice != null && isValidAmount && comparisonPercent != null && (
        <div className="text-xs text-theme-muted px-1">
          {comparisonPercent === 0
            ? t('offer.matches_price', 'Matches the listing price')
            : comparisonPercent > 0
              ? t('offer.above_price', '{{percent}}% above the listing price', {
                  percent: comparisonPercent,
                })
              : t('offer.below_price', '{{percent}}% below the listing price', {
                  percent: Math.abs(comparisonPercent),
                })}
        </div>
      )}

      {/* Optional message */}
      <Textarea
        label={t('offer.message_label', 'Message (optional)')}
        placeholder={t('offer.message_placeholder', 'Add a message to the seller...')}
        value={message}
        onValueChange={setMessage}
        maxLength={MAX_MESSAGE_LENGTH}
        variant="bordered"
        description={`${message.length}/${MAX_MESSAGE_LENGTH}`}
      />

      {/* Actions */}
      <div className="flex justify-end gap-2 pt-2">
        <Button
          variant="flat"
          onPress={onClose}
          isDisabled={isSubmitting}
        >
          {t('common:cancel', 'Cancel')}
        </Button>
        <Button
          type="submit"
          color="primary"
          isLoading={isSubmitting}
          isDisabled={!isValidAmount}
          startContent={!isSubmitting ? <Send className="w-4 h-4" aria-hidden="true" /> : undefined}
        >
          {t('offer.send', 'Send Offer')}
        </Button>
      </div>
    </form>
  );
}

export default MakeOfferForm;
