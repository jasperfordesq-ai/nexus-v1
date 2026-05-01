// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * LoyaltyHistoryPage — Member's history of time-credit redemptions
 * at participating local marketplace merchants.
 *
 * Renders the GET /v2/caring-community/loyalty/my-history payload.
 */

import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Skeleton, Chip } from '@heroui/react';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Coins from 'lucide-react/icons/coins';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { PageMeta } from '@/components/seo';
import { useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface LoyaltyRedemption {
  id: number;
  credits_used: number;
  exchange_rate_chf: number;
  discount_chf: number;
  order_total_chf: number;
  status: 'pending' | 'applied' | 'reversed';
  redeemed_at: string;
  merchant_id: number | null;
  merchant_name: string;
  marketplace_listing_id: number | null;
  listing_title: string | null;
}

interface HistoryResponse {
  items: LoyaltyRedemption[];
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function LoyaltyHistoryPage() {
  const { t } = useTranslation('common');
  const { tenantPath } = useTenant();
  const [items, setItems] = useState<LoyaltyRedemption[] | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  usePageTitle(t('loyalty.history.meta.title'));

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    api
      .get<HistoryResponse>('/v2/caring-community/loyalty/my-history')
      .then((res) => {
        if (cancelled) return;
        if (res.success && res.data) {
          setItems(res.data.items || []);
        } else {
          setError(t('loyalty.history.errors.load_failed'));
          setItems([]);
        }
      })
      .catch((err) => {
        logError('LoyaltyHistoryPage: fetch failed', err);
        if (!cancelled) {
          setError(t('loyalty.history.errors.load_failed'));
          setItems([]);
        }
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div className="container mx-auto max-w-4xl px-4 py-8">
      <PageMeta
        title={t('loyalty.history.meta.title')}
        description={t('loyalty.history.meta.description')}
      />

      <Link
        to={tenantPath('/caring-community')}
        className="inline-flex items-center gap-1 text-sm text-default-500 hover:text-primary mb-4"
      >
        <ArrowLeft className="w-4 h-4" />
        {t('loyalty.history.back')}
      </Link>

      <div className="flex items-center gap-3 mb-2">
        <Coins className="w-7 h-7 text-warning" />
        <h1 className="text-2xl font-semibold text-foreground">
          {t('loyalty.history.meta.title')}
        </h1>
      </div>
      <p className="text-sm text-default-500 mb-6">
        {t('loyalty.history.subtitle')}
      </p>

      {loading && (
        <div className="space-y-3">
          {[1, 2, 3].map((i) => (
            <Skeleton key={i} className="h-20 w-full rounded-xl" />
          ))}
        </div>
      )}

      {!loading && (items?.length ?? 0) === 0 && (
        <EmptyState
          title={t('loyalty.history.empty.title')}
          description={t('loyalty.history.empty.body')}
          icon={<Coins className="w-12 h-12 text-default-300" />}
        />
      )}

      {!loading && items && items.length > 0 && (
        <GlassCard className="overflow-hidden">
          <table className="w-full">
            <thead className="bg-default-100/50">
              <tr className="text-xs text-default-500 uppercase tracking-wide">
                <th className="text-left px-4 py-3">{t('loyalty.history.table.date')}</th>
                <th className="text-left px-4 py-3">{t('loyalty.history.table.merchant')}</th>
                <th className="text-left px-4 py-3 hidden sm:table-cell">
                  {t('loyalty.history.table.item')}
                </th>
                <th className="text-right px-4 py-3">{t('loyalty.history.table.credits')}</th>
                <th className="text-right px-4 py-3">{t('loyalty.history.table.discount')}</th>
              </tr>
            </thead>
            <tbody>
              {items.map((row) => (
                <tr key={row.id} className="border-t border-default-200 hover:bg-default-50/50">
                  <td className="px-4 py-3 text-sm text-default-600">
                    {new Date(row.redeemed_at).toLocaleDateString()}
                  </td>
                  <td className="px-4 py-3 text-sm text-foreground">
                    {row.merchant_name || (
                      <span aria-label={t('common.not_available')}>—</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-sm text-default-600 hidden sm:table-cell">
                    {row.marketplace_listing_id && row.listing_title ? (
                      <Link
                        to={tenantPath(`/marketplace/${row.marketplace_listing_id}`)}
                        className="text-primary hover:underline"
                      >
                        {row.listing_title}
                      </Link>
                    ) : (
                      <span aria-label={t('common.not_available')}>—</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-sm text-right tabular-nums">
                    {row.credits_used.toFixed(2)} h
                  </td>
                  <td className="px-4 py-3 text-sm text-right tabular-nums">
                    <Chip variant="flat" color="success" size="sm">
                      CHF {row.discount_chf.toFixed(2)}
                    </Chip>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </GlassCard>
      )}

      {error && !loading && (
        <p className="mt-4 text-sm text-danger">{error}</p>
      )}
    </div>
  );
}

export default LoyaltyHistoryPage;
