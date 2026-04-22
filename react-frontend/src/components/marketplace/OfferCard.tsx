// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * OfferCard - Offer display card for marketplace offer lists
 *
 * Shows offer amount, status badge, message, listing thumbnail,
 * buyer/seller info, and context-dependent action buttons.
 */

import { Avatar, Button, Chip } from '@heroui/react';
import Check from 'lucide-react/icons/check';
import X from 'lucide-react/icons/x';
import RotateCcw from 'lucide-react/icons/rotate-ccw';
import Undo2 from 'lucide-react/icons/undo-2';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { MarketplaceOffer } from '@/types/marketplace';

interface OfferCardProps {
  offer: MarketplaceOffer;
  /** 'buyer' or 'seller' — determines which action buttons are shown */
  perspective?: 'buyer' | 'seller';
  onAccept?: (offerId: number) => void;
  onDecline?: (offerId: number) => void;
  onCounter?: (offerId: number) => void;
  onWithdraw?: (offerId: number) => void;
  onAcceptCounter?: (offerId: number) => void;
}

const STATUS_CONFIG: Record<string, { color: 'warning' | 'success' | 'danger' | 'secondary' | 'default'; label: string }> = {
  pending: { color: 'warning', label: 'Pending' },
  accepted: { color: 'success', label: 'Accepted' },
  declined: { color: 'danger', label: 'Declined' },
  countered: { color: 'secondary', label: 'Countered' },
  expired: { color: 'default', label: 'Expired' },
  withdrawn: { color: 'default', label: 'Withdrawn' },
};

function formatCurrency(amount: number, currency: string): string {
  try {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(amount);
  } catch {
    return `${currency} ${amount}`;
  }
}

function formatTimestamp(dateStr: string): string {
  try {
    return new Intl.DateTimeFormat(undefined, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(new Date(dateStr));
  } catch {
    return dateStr;
  }
}

export function OfferCard({
  offer,
  perspective = 'buyer',
  onAccept,
  onDecline,
  onCounter,
  onWithdraw,
  onAcceptCounter,
}: OfferCardProps) {
  const { t } = useTranslation('marketplace');
  const statusConfig = STATUS_CONFIG[offer.status] ?? { color: 'default' as const, label: 'Unknown' };
  const counterparty = perspective === 'buyer' ? offer.seller : offer.buyer;

  return (
    <GlassCard className="p-4">
      <div className="flex gap-3">
        {/* Listing thumbnail */}
        {offer.listing?.image?.url && (
          <div className="shrink-0 w-16 h-16 rounded-md overflow-hidden">
            <img
              src={offer.listing.image.thumbnail_url || offer.listing.image.url}
              alt={offer.listing.title}
              className="w-full h-full object-cover"
              loading="lazy"
            />
          </div>
        )}

        <div className="flex-1 min-w-0">
          {/* Listing title + status */}
          <div className="flex items-start justify-between gap-2 mb-1">
            <div className="min-w-0">
              {offer.listing && (
                <p className="text-sm font-semibold text-theme-primary truncate">
                  {offer.listing.title}
                </p>
              )}
            </div>
            <Chip
              color={statusConfig.color}
              variant="flat"
              size="sm"
              className="shrink-0"
            >
              {t(`offer.status.${offer.status}`, statusConfig.label)}
            </Chip>
          </div>

          {/* Amount */}
          <p className="text-lg font-bold text-theme-primary">
            {formatCurrency(offer.amount, offer.currency)}
          </p>

          {/* Counter offer amount */}
          {offer.status === 'countered' && offer.counter_amount != null && (
            <p className="text-sm text-theme-muted mt-0.5">
              {t('offer.counter_amount', 'Counter: {{amount}}', {
                amount: formatCurrency(offer.counter_amount, offer.currency),
              })}
            </p>
          )}

          {/* Message */}
          {offer.message && (
            <p className="text-xs text-theme-muted mt-1.5 line-clamp-2">
              {offer.message}
            </p>
          )}

          {/* Counter message */}
          {offer.counter_message && (
            <p className="text-xs text-theme-subtle mt-1 italic line-clamp-2">
              {offer.counter_message}
            </p>
          )}

          {/* Counterparty + timestamp */}
          <div className="flex items-center justify-between mt-2.5">
            {counterparty && (
              <div className="flex items-center gap-1.5">
                <Avatar
                  src={resolveAvatarUrl(counterparty.avatar_url)}
                  name={counterparty.name}
                  size="sm"
                  className="w-5 h-5"
                />
                <span className="text-xs text-theme-muted">{counterparty.name}</span>
              </div>
            )}
            <span className="text-xs text-theme-subtle">
              {formatTimestamp(offer.created_at)}
            </span>
          </div>

          {/* Action buttons */}
          {offer.status === 'pending' && (
            <div className="flex gap-2 mt-3">
              {perspective === 'seller' && (
                <>
                  <Button
                    size="sm"
                    color="success"
                    variant="flat"
                    startContent={<Check className="w-3.5 h-3.5" aria-hidden="true" />}
                    onPress={() => onAccept?.(offer.id)}
                  >
                    {t('offer.accept', 'Accept')}
                  </Button>
                  <Button
                    size="sm"
                    color="danger"
                    variant="flat"
                    startContent={<X className="w-3.5 h-3.5" aria-hidden="true" />}
                    onPress={() => onDecline?.(offer.id)}
                  >
                    {t('offer.decline', 'Decline')}
                  </Button>
                  <Button
                    size="sm"
                    color="secondary"
                    variant="flat"
                    startContent={<RotateCcw className="w-3.5 h-3.5" aria-hidden="true" />}
                    onPress={() => onCounter?.(offer.id)}
                  >
                    {t('offer.counter', 'Counter')}
                  </Button>
                </>
              )}
              {perspective === 'buyer' && (
                <Button
                  size="sm"
                  color="danger"
                  variant="flat"
                  startContent={<Undo2 className="w-3.5 h-3.5" aria-hidden="true" />}
                  onPress={() => onWithdraw?.(offer.id)}
                >
                  {t('offer.withdraw', 'Withdraw')}
                </Button>
              )}
            </div>
          )}

          {/* Accept counter button for buyer */}
          {offer.status === 'countered' && perspective === 'buyer' && (
            <div className="flex gap-2 mt-3">
              <Button
                size="sm"
                color="success"
                variant="flat"
                startContent={<Check className="w-3.5 h-3.5" aria-hidden="true" />}
                onPress={() => onAcceptCounter?.(offer.id)}
              >
                {t('offer.accept_counter', 'Accept Counter')}
              </Button>
              <Button
                size="sm"
                color="danger"
                variant="flat"
                startContent={<X className="w-3.5 h-3.5" aria-hidden="true" />}
                onPress={() => onDecline?.(offer.id)}
              >
                {t('offer.decline', 'Decline')}
              </Button>
            </div>
          )}
        </div>
      </div>
    </GlassCard>
  );
}

export default OfferCard;
