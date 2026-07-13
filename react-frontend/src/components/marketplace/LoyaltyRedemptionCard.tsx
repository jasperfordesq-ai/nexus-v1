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
 * Lets the member preview and reserve a discount funded by their hours,
 * within the merchant's policy cap. The returned pending redemption is
 * attached to checkout and settled atomically when the order is created.
 */

import { useState, useEffect, useCallback, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Slider } from '@/components/ui/Slider';
import Coins from 'lucide-react/icons/coins';
import Sparkles from 'lucide-react/icons/sparkles';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';
import { GlassCard } from '@/components/ui/GlassCard';
import { useAuth, useTenant, useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { formatMarketplaceCurrency } from '@/lib/marketplaceNumbers';

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
  discount_chf: number;
  redemption_id: number;
  adjusted_total_chf?: number;
  new_wallet_balance?: number;
}

export interface LoyaltyRedemptionSelection {
  redemptionId: number;
  discountChf: number;
  adjustedTotalChf: number;
}

interface Props {
  sellerId: number;
  listingId: number;
  orderTotalChf: number;
  currency: string;
  onRedemptionChange?: (selection: LoyaltyRedemptionSelection | null) => void;
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function LoyaltyRedemptionCard({
  sellerId,
  listingId,
  orderTotalChf,
  currency,
  onRedemptionChange,
}: Props) {
  const { t } = useTranslation('common');
  const { user, isAuthenticated } = useAuth();
  const { hasFeature } = useTenant();
  const toast = useToast();

  const [quote, setQuote] = useState<LoyaltyQuote | null>(null);
  const [credits, setCredits] = useState<number>(0);
  const [loading, setLoading] = useState<boolean>(true);
  const [submitting, setSubmitting] = useState<boolean>(false);
  const [confirmed, setConfirmed] = useState<LoyaltyRedemptionSelection | null>(null);

  const featureOn = hasFeature('caring_community');
  const validInputs = isAuthenticated && featureOn && sellerId > 0 && orderTotalChf > 0 && user?.id !== sellerId;

  // Fetch quote
  useEffect(() => {
    if (!validInputs) {
      setConfirmed(null);
      onRedemptionChange?.(null);
      setLoading(false);
      return;
    }
    let cancelled = false;
    setConfirmed(null);
    onRedemptionChange?.(null);
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
  }, [sellerId, listingId, orderTotalChf, validInputs, onRedemptionChange]);

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
      const discountChf = Math.max(0, Number(res.data.discount_chf) || 0);
      const adjustedTotalChf = Math.max(
        0,
        Number(res.data.adjusted_total_chf ?? orderTotalChf - discountChf) || 0,
      );
      const selection: LoyaltyRedemptionSelection = {
        redemptionId: res.data.redemption_id,
        discountChf,
        adjustedTotalChf,
      };
      setConfirmed(selection);
      onRedemptionChange?.(selection);
      toast.success(
        t('loyalty.applied_success', {
          credits: credits.toFixed(2),
          discount: discountChf.toFixed(2),
        }),
      );
    } catch (err) {
      logError('LoyaltyRedemptionCard: redeem failed', err);
      toast.error(t('loyalty.errors.redemption_failed'));
    } finally {
      setSubmitting(false);
    }
  }, [quote, credits, sellerId, listingId, orderTotalChf, onRedemptionChange, t, toast]);

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
            <p className="text-xs text-muted mt-1">
              {formatMarketplaceCurrency(confirmed.adjustedTotalChf, currency)}
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

      <div className="space-y-1 text-xs text-muted">
        <p className="flex items-center gap-1.5">
          <Sparkles className="w-3.5 h-3.5 text-warning" />
          {t('loyalty.merchant_accepts')}
        </p>
        <p>{t('loyalty.available', { credits: quote.member_credits.toFixed(2) })}</p>
        <p>{t('loyalty.exchange_rate', { rate: quote.exchange_rate_chf.toFixed(2) })}</p>
        <p>{t('loyalty.max_discount', { percent: quote.max_discount_pct })}</p>
      </div>

      <div className="space-y-2">
        <label className="text-xs font-medium text-foreground block">
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
          <span className="text-muted">
            <strong>{t('hours_short', { count: Number(credits.toFixed(2)) })}</strong>
          </span>
          <span className="text-muted">
            {t('loyalty.discount_preview', { discount: previewDiscount.toFixed(2) })}
          </span>
        </div>
      </div>

      <div className="flex items-center justify-between pt-1 border-t border-separator">
        <Chip variant="soft" color="warning" size="sm">
          {formatMarketplaceCurrency(previewNewTotal, currency)}
        </Chip>
        <Button

          variant="secondary"
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
