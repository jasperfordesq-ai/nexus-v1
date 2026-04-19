// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Button } from '@heroui/react';
import {
  DollarSign,
  Calendar,
  CheckCircle,
  XCircle,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { formatDateValue } from '@/lib/helpers';
import type { InlineOffer } from './JobDetailTypes';

interface InlineOfferCardProps {
  pendingOffer: InlineOffer;
  isResponding: boolean;
  onAccept: () => void;
  onDeclineOpen: () => void;
}

export function InlineOfferCard({
  pendingOffer,
  isResponding,
  onAccept,
  onDeclineOpen,
}: InlineOfferCardProps) {
  const { t } = useTranslation('jobs');

  if (pendingOffer.status !== 'pending') return null;

  return (
    <GlassCard className="p-5 border-l-4 border-l-success bg-success/5">
      <div className="flex items-start gap-3">
        <div className="w-10 h-10 rounded-lg bg-success/20 flex items-center justify-center flex-shrink-0">
          <DollarSign className="w-5 h-5 text-success" aria-hidden="true" />
        </div>
        <div className="flex-1 space-y-3">
          <div>
            <h3 className="text-base font-semibold text-theme-primary">
              {t('inline_response.offer_pending', 'You have received an offer!')}
            </h3>
            <div className="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-sm text-theme-secondary">
              {pendingOffer.salary_offered && (
                <span className="flex items-center gap-1 font-medium">
                  <DollarSign className="w-3.5 h-3.5" aria-hidden="true" />
                  {t('inline_response.offer_salary', 'Salary Offered')}: {pendingOffer.salary_currency}{pendingOffer.salary_offered}
                  {pendingOffer.salary_type && ` / ${t(`salary.${pendingOffer.salary_type}`)}`}
                </span>
              )}
              {pendingOffer.start_date && (
                <span className="flex items-center gap-1">
                  <Calendar className="w-3.5 h-3.5" aria-hidden="true" />
                  {t('inline_response.offer_start_date', 'Start Date')}: {formatDateValue(pendingOffer.start_date)}
                </span>
              )}
            </div>
            {pendingOffer.message && (
              <p className="text-sm text-theme-muted mt-2 italic">
                &ldquo;{pendingOffer.message}&rdquo;
              </p>
            )}
          </div>
          <div className="flex gap-2">
            <Button
              color="success"
              size="sm"
              isLoading={isResponding}
              onPress={onAccept}
              startContent={<CheckCircle className="w-4 h-4" aria-hidden="true" />}
            >
              {t('inline_response.offer_accept', 'Accept Offer')}
            </Button>
            <Button
              color="danger"
              variant="flat"
              size="sm"
              isDisabled={isResponding}
              onPress={onDeclineOpen}
              startContent={<XCircle className="w-4 h-4" aria-hidden="true" />}
            >
              {t('inline_response.offer_decline', 'Decline Offer')}
            </Button>
          </div>
        </div>
      </div>
    </GlassCard>
  );
}
