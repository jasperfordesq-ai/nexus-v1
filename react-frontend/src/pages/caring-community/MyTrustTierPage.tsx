// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button, Progress, Skeleton } from '@heroui/react';
import AlertCircle from 'lucide-react/icons/alert-circle';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import CheckCircle2 from 'lucide-react/icons/check-circle-2';
import Circle from 'lucide-react/icons/circle';
import ShieldCheck from 'lucide-react/icons/shield-check';
import { useTranslation } from 'react-i18next';
import { TrustTierBadge } from '@/components/caring-community/TrustTierBadge';
import { GlassCard } from '@/components/ui';
import { PageMeta } from '@/components/seo';
import { useTenant } from '@/contexts';
import { useApi } from '@/hooks/useApi';
import { usePageTitle } from '@/hooks';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

type TrustTier = 0 | 1 | 2 | 3 | 4;

interface TrustTierSignal {
  key: string;
  label_key: string;
  current: number;
  required: number;
  achieved: boolean;
  unit: string;
}

interface TrustTierBreakdown {
  tier: TrustTier;
  tier_label: string;
  next_tier_label: string | null;
  progress_pct: number;
  signals: TrustTierSignal[];
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function tierColor(
  tier: TrustTier,
): 'default' | 'primary' | 'success' | 'secondary' | 'warning' {
  switch (tier) {
    case 0:
      return 'default';
    case 1:
      return 'primary';
    case 2:
      return 'success';
    case 3:
      return 'secondary';
    case 4:
      return 'warning';
  }
}

function signalProgressPct(signal: TrustTierSignal): number {
  if (signal.required <= 0) {
    return signal.achieved ? 100 : 0;
  }
  const pct = (signal.current / signal.required) * 100;
  return Math.max(0, Math.min(100, Math.round(pct)));
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export function MyTrustTierPage() {
  const { t } = useTranslation('caring_community');
  const { hasFeature, tenantPath } = useTenant();
  const navigate = useNavigate();
  usePageTitle(t('trust_tier.title'));

  const { data, isLoading, error, refetch } = useApi<TrustTierBreakdown>(
    '/v2/caring-community/me/trust-tier/breakdown',
    { immediate: true },
  );

  // Redirect if feature is disabled
  useEffect(() => {
    if (!hasFeature('caring_community')) {
      void navigate(tenantPath('/'), { replace: true });
    }
  }, [hasFeature, navigate, tenantPath]);

  return (
    <>
      <PageMeta
        title={t('trust_tier.title')}
        description={t('trust_tier.subtitle')}
        noIndex
      />

      <div className="space-y-6">
        {/* Back link */}
        <Link
          to={tenantPath('/caring-community')}
          className="inline-flex items-center gap-1.5 text-sm font-medium text-[var(--color-primary)] hover:underline"
        >
          <ArrowLeft className="h-4 w-4" aria-hidden="true" />
          {t('trust_tier.back')}
        </Link>

        {/* Page header */}
        <GlassCard className="p-6 sm:p-8">
          <div className="flex items-start gap-4">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/15">
              <ShieldCheck
                className="h-6 w-6 text-[var(--color-primary)]"
                aria-hidden="true"
              />
            </div>
            <div>
              <h1 className="text-2xl font-bold leading-tight text-theme-primary sm:text-3xl">
                {t('trust_tier.breakdown.title')}
              </h1>
              <p className="mt-2 text-base leading-8 text-theme-muted">
                {t('trust_tier.breakdown.subtitle')}
              </p>
            </div>
          </div>
        </GlassCard>

        {/* Error state */}
        {error && !isLoading && (
          <GlassCard className="p-6">
            <div className="flex flex-wrap items-center justify-between gap-4">
              <div className="flex items-center gap-3 text-danger">
                <AlertCircle className="h-5 w-5 shrink-0" aria-hidden="true" />
                <p className="font-medium">{t('trust_tier.breakdown.error')}</p>
              </div>
              <Button
                size="sm"
                variant="flat"
                onPress={() => {
                  void refetch();
                }}
              >
                {t('trust_tier.breakdown.retry')}
              </Button>
            </div>
          </GlassCard>
        )}

        {/* Loading skeleton */}
        {isLoading && (
          <GlassCard className="p-6 space-y-4">
            <Skeleton className="h-6 w-1/3 rounded-lg" />
            <Skeleton className="h-4 w-2/3 rounded-lg" />
            <Skeleton className="h-3 w-full rounded-lg" />
            <Skeleton className="h-3 w-full rounded-lg" />
            <Skeleton className="h-3 w-full rounded-lg" />
          </GlassCard>
        )}

        {/* Tier card */}
        {!isLoading && !error && data && (
          <GlassCard className="p-6 space-y-6">
            {/* Current + next tier */}
            <div className="flex flex-wrap items-center justify-between gap-4">
              <div>
                <p className="text-sm font-medium text-theme-muted mb-1">
                  {t('trust_tier.your_tier')}
                </p>
                <TrustTierBadge tier={data.tier} size="md" showLabel />
              </div>

              {data.next_tier_label && (
                <div className="text-right">
                  <p className="text-sm font-medium text-theme-muted mb-1">
                    {t('trust_tier.next_tier')}
                  </p>
                  <p className="text-sm font-semibold text-theme-primary capitalize">
                    {t(`trust_tier.tier_${data.next_tier_label}`)}
                  </p>
                </div>
              )}
            </div>

            {/* Overall progress */}
            <div>
              <p className="mb-2 text-sm text-theme-muted">
                {data.next_tier_label
                  ? t('trust_tier.progress_to_next', {
                      tier: t(`trust_tier.tier_${data.next_tier_label}`),
                    })
                  : t(`trust_tier.tier_${data.tier_label}`)}
              </p>
              <Progress
                value={data.progress_pct}
                color={tierColor(data.tier)}
                className="max-w-md"
                aria-label={t('trust_tier.breakdown.title')}
              />
              <p className="mt-1 text-xs text-theme-muted">
                {Math.round(data.progress_pct)}%
              </p>
            </div>

            {/* Per-signal breakdown */}
            <div className="rounded-lg border border-theme-default bg-theme-elevated p-4 space-y-4">
              {data.signals.length === 0 && (
                <p className="text-sm text-theme-muted">
                  {t('trust_tier.breakdown.no_signals')}
                </p>
              )}

              {data.signals.map((signal) => {
                const Icon = signal.achieved ? CheckCircle2 : Circle;
                const iconColor = signal.achieved
                  ? 'text-success'
                  : 'text-theme-muted';
                return (
                  <div key={signal.key} className="space-y-1.5">
                    <div className="flex items-start justify-between gap-3">
                      <div className="flex items-start gap-2">
                        <Icon
                          className={`mt-0.5 h-5 w-5 shrink-0 ${iconColor}`}
                          aria-hidden="true"
                        />
                        <div>
                          <p className="text-sm font-semibold text-theme-primary">
                            {t(signal.label_key)}
                          </p>
                          <p className="text-xs text-theme-muted">
                            {signal.unit === 'boolean'
                              ? signal.achieved
                                ? t('trust_tier.breakdown.achieved')
                                : t('trust_tier.breakdown.in_progress')
                              : t('trust_tier.breakdown.signal_progress', {
                                  current: signal.current,
                                  required: signal.required,
                                })}
                          </p>
                        </div>
                      </div>
                      <span
                        className={`text-xs font-medium ${
                          signal.achieved ? 'text-success' : 'text-theme-muted'
                        }`}
                      >
                        {signal.achieved
                          ? t('trust_tier.breakdown.achieved')
                          : t('trust_tier.breakdown.in_progress')}
                      </span>
                    </div>
                    <Progress
                      value={signalProgressPct(signal)}
                      color={signal.achieved ? 'success' : 'primary'}
                      size="sm"
                      aria-label={t(signal.label_key)}
                    />
                  </div>
                );
              })}
            </div>

            {data.next_tier_label && (
              <p className="text-xs text-theme-muted">
                {t('trust_tier.breakdown.next_tier_hint')}
              </p>
            )}
          </GlassCard>
        )}
      </div>
    </>
  );
}

export default MyTrustTierPage;
