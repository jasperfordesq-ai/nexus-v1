// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * LoyaltyRedemptionCard — Time-credit ↔ marketplace bridge.
 *
 * Renders inline on a marketplace listing detail page when:
 *   - Caring Community feature is enabled for this tenant
 *   - The viewing member is authenticated
 *   - The seller has opted into accepting time credits
 *   - The member has a positive wallet balance
 *
 * Lets the member preview and apply a discount funded by their hours,
 * within the merchant's policy cap. On confirm, calls
 * POST /v2/caring-community/loyalty/redeem which atomically debits the
 * member's wallet and records a row in caring_loyalty_redemptions.
 *
 * The merchant honours the discount at the point of sale; the redemption
 * row is the audit trail.
 */

import { useState, useEffect, useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Slider, Chip } from '@heroui/react';
import Coins from 'lucide-react/icons/coins';
import Sparkles from 'lucide-react/icons/sparkles';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import { GlassCard } from '@/components/ui';
import { useAuth, useTenant, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface LoyaltyQuote {
  accepts: boolean;
  member_credits: number;
  exchange_rate_chf: number;
  max_discount_pct: number;
  max_credits_usable: number;
  max_discount_chf: number;
  reason?: string;
}

interface RedeemResponse {
  success: boolean;
  discount_chf: number;
  redemption_id: number;
  new_wallet_balance: number;
}

interface Props {
  sellerId: number;
  listingId: number;
  orderTotalChf: number;
  currency: string;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function LoyaltyRedemptionCard({ sellerId, listingId, orderTotalChf, currency }: Props) {
  const { t } = useTranslation('common');
  const { user, isAuthenticated } = useAuth();
  const { hasFeature } = useTenant();
  const toast = useToast();

  const [quote, setQuote] = useState<LoyaltyQuote | null>(null);
  const [credits, setCredits] = useState<number>(0);
  const [loading, setLoading] = useState<boolean>(true);
  const [submitting, setSubmitting] = useState<boolean>(false);
  const [confirmed, setConfirmed] = useState<{ discountChf: number; newBalance: number } | null>(null);

  const featureOn = hasFeature('caring_community');
  const validInputs = isAuthenticated && featureOn && sellerId > 0 && orderTotalChf > 0 && user?.id !== sellerId;

  // Fetch quote
  useEffect(() => {
    if (!validInputs) {
      setLoading(false);
      return;
    }
    let cancelled = false;
    setLoading(true);
    api
      .get<LoyaltyQuote>(
        `/v2/caring-community/loyalty/quote?seller_id=${sellerId}&listing_id=${listingId}&order_total_chf=${orderTotalChf}`,
      )
      .then((res) => {
        if (cancelled) return;
        if (res.success && res.data) {
          setQuote(res.data);
        } else {
          setQuote(null);
        }
      })
      .catch((err) => {
        logError('LoyaltyRedemptionCard: quote failed', err);
        if (!cancelled) setQuote(null);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [sellerId, listingId, orderTotalChf, validInputs]);

  const previewDiscount = useMemo(() => {
    if (!quote || !quote.accepts) return 0;
    return Number((credits * quote.exchange_rate_chf).toFixed(2));
  }, [credits, quote]);

  const previewNewTotal = useMemo(() => {
    return Math.max(0, Number((orderTotalChf - previewDiscount).toFixed(2)));
  }, [orderTotalChf, previewDiscount]);

  const handleApply = useCallback(async () => {
    if (!quote || credits <= 0) return;
    setSubmitting(true);
    try {
      const res = await api.post<RedeemResponse>('/v2/caring-community/loyalty/redeem', {
        seller_id: sellerId,
        listing_id: listingId,
        credits_to_use: credits,
        order_total_chf: orderTotalChf,
      });
      if (!res.success || !res.data) {
        const errMsg = res.error || t('loyalty.errors.redemption_failed');
        toast.error(errMsg);
        return;
      }
      setConfirmed({ discountChf: res.data.discount_chf, newBalance: res.data.new_wallet_balance });
      toast.success(
        t('loyalty.applied_success', {
          credits: credits.toFixed(2),
          discount: res.data.discount_chf.toFixed(2),
        }),
      );
    } catch (err) {
      logError('LoyaltyRedemptionCard: redeem failed', err);
      toast.error(t('loyalty.errors.redemption_failed'));
    } finally {
      setSubmitting(false);
    }
  }, [quote, credits, sellerId, listingId, orderTotalChf, t, toast]);

  // Hide entirely when not relevant
  if (!validInputs) return null;
  if (loading) return null;
  if (!quote) return null;
  if (!quote.accepts || quote.max_credits_usable <= 0) return null;

  // Confirmed view
  if (confirmed) {
    return (
      <GlassCard className="p-4 border border-success/20 bg-success/5 space-y-2">
        <div className="flex items-start gap-3">
          <CheckCircle2 className="w-5 h-5 text-success flex-shrink-0 mt-0.5" />
          <div className="flex-1 min-w-0 text-sm">
            <p className="font-semibold text-foreground">
              {t('loyalty.applied_success', {
                credits: credits.toFixed(2),
                discount: confirmed.discountChf.toFixed(2),
              })}
            </p>
            <p className="text-xs text-default-500 mt-1">
              {t('loyalty.history.subtitle')}
            </p>
          </div>
        </div>
      </GlassCard>
    );
  }

  return (
    <GlassCard className="p-4 space-y-3 border border-warning/20 bg-warning/5">
      <div className="flex items-center gap-2">
        <Coins className="w-5 h-5 text-warning" />
        <h3 className="text-sm font-semibold text-foreground">
          {t('loyalty.section_title')}
        </h3>
      </div>

      <div className="space-y-1 text-xs text-default-600">
        <p className="flex items-center gap-1.5">
          <Sparkles className="w-3.5 h-3.5 text-warning" />
          {t('loyalty.merchant_accepts')}
        </p>
        <p>{t('loyalty.available', { credits: quote.member_credits.toFixed(2) })}</p>
        <p>{t('loyalty.exchange_rate', { rate: quote.exchange_rate_chf.toFixed(2) })}</p>
        <p>{t('loyalty.max_discount', { percent: quote.max_discount_pct })}</p>
      </div>

      <div className="space-y-2">
        <label className="text-xs font-medium text-default-700 block">
          {t('loyalty.credits_to_use_label')}
        </label>
        <Slider
          aria-label={t('loyalty.credits_to_use_label')}
          size="sm"
          step={0.25}
          minValue={0}
          maxValue={quote.max_credits_usable}
          value={credits}
          onChange={(v) => setCredits(Array.isArray(v) ? (v[0] ?? 0) : v)}
          color="warning"
          showTooltip
        />
        <div className="flex items-center justify-between text-xs">
          <span className="text-default-500">
            <strong>{credits.toFixed(2)} h</strong>
          </span>
          <span className="text-default-500">
            {t('loyalty.discount_preview', { discount: previewDiscount.toFixed(2) })}
          </span>
        </div>
      </div>

      <div className="flex items-center justify-between pt-1 border-t border-default-200">
        <Chip variant="flat" color="warning" size="sm">
          {currency} {previewNewTotal.toFixed(2)}
        </Chip>
        <Button
          color="warning"
          variant="solid"
          size="sm"
          onPress={handleApply}
          isLoading={submitting}
          isDisabled={credits <= 0 || submitting}
        >
          {submitting ? t('loyalty.applying') : t('loyalty.apply')}
        </Button>
      </div>
    </GlassCard>
  );
}

export default LoyaltyRedemptionCard;
