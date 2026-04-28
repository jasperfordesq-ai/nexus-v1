// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Progress, Skeleton } from '@heroui/react';
import AlertCircle from 'lucide-react/icons/alert-circle';
import ArrowLeft from 'lucide-react/icons/arrow-left';
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

interface MyTrustTierResponse {
  tier: 0 | 1 | 2 | 3 | 4;
  label: string;
  next_tier: string | null;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Map tier 0–4 to a rough "progress within tier" percentage for the bar */
function tierToPercent(tier: number): number {
  return Math.round((tier / 4) * 100);
}

// ---------------------------------------------------------------------------
// Page
// ---------------------------------------------------------------------------

export function MyTrustTierPage() {
  const { t } = useTranslation('caring_community');
  const { hasFeature, tenantPath } = useTenant();
  const navigate = useNavigate();
  usePageTitle(t('trust_tier.title'));

  const { data, isLoading, error } = useApi<MyTrustTierResponse>(
    '/v2/caring-community/my-trust-tier',
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
          {t('trust_tier.title')}
        </Link>

        {/* Page header */}
        <GlassCard className="p-6 sm:p-8">
          <div className="flex items-start gap-4">
            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/15">
              <ShieldCheck className="h-6 w-6 text-[var(--color-primary)]" aria-hidden="true" />
            </div>
            <div>
              <h1 className="text-2xl font-bold leading-tight text-theme-primary sm:text-3xl">
                {t('trust_tier.title')}
              </h1>
              <p className="mt-2 text-base leading-8 text-theme-muted">
                {t('trust_tier.subtitle')}
              </p>
            </div>
          </div>
        </GlassCard>

        {/* Error state */}
        {error && !isLoading && (
          <GlassCard className="p-6">
            <div className="flex items-center gap-3 text-danger">
              <AlertCircle className="h-5 w-5 shrink-0" aria-hidden="true" />
              <p className="font-medium">{t('trust_tier.title')}</p>
            </div>
          </GlassCard>
        )}

        {/* Loading skeleton */}
        {isLoading && (
          <GlassCard className="p-6 space-y-4">
            <Skeleton className="h-6 w-1/3 rounded-lg" />
            <Skeleton className="h-4 w-2/3 rounded-lg" />
            <Skeleton className="h-3 w-full rounded-lg" />
          </GlassCard>
        )}

        {/* Tier card */}
        {!isLoading && !error && data && (
          <GlassCard className="p-6 space-y-6">
            {/* Current tier */}
            <div className="flex flex-wrap items-center justify-between gap-4">
              <div>
                <p className="text-sm font-medium text-theme-muted mb-1">
                  {t('trust_tier.your_tier')}
                </p>
                <TrustTierBadge tier={data.tier} size="md" showLabel />
              </div>

              {data.next_tier && (
                <div className="text-right">
                  <p className="text-sm font-medium text-theme-muted mb-1">
                    {t('trust_tier.next_tier')}
                  </p>
                  <p className="text-sm font-semibold text-theme-primary capitalize">
                    {t(`trust_tier.tier_${data.next_tier}`)}
                  </p>
                </div>
              )}
            </div>

            {/* Progress bar */}
            <div>
              <p className="mb-2 text-sm text-theme-muted">
                {data.next_tier
                  ? t('trust_tier.progress_to_next', { tier: t(`trust_tier.tier_${data.next_tier}`) })
                  : t(`trust_tier.tier_${data.label}`)}
              </p>
              <Progress
                value={tierToPercent(data.tier)}
                color={
                  data.tier === 0 ? 'default'
                  : data.tier === 1 ? 'primary'
                  : data.tier === 2 ? 'success'
                  : data.tier === 3 ? 'secondary'
                  : 'warning'
                }
                className="max-w-md"
                aria-label={t('trust_tier.title')}
              />
              <p className="mt-1 text-xs text-theme-muted">{tierToPercent(data.tier)}%</p>
            </div>

            {/* Criteria list */}
            <div className="rounded-lg border border-theme-default bg-theme-elevated p-4 space-y-3">
              <p className="text-xs font-semibold uppercase tracking-wide text-theme-muted">
                {t('trust_tier.next_tier')}
              </p>

              <div className="grid gap-3 sm:grid-cols-3">
                <div className="flex flex-col gap-1">
                  <span className="text-xs text-theme-muted">{t('trust_tier.criteria_hours')}</span>
                  <span className="text-sm font-semibold text-theme-primary">
                    {t('trust_tier.criteria_hours')}
                  </span>
                </div>
                <div className="flex flex-col gap-1">
                  <span className="text-xs text-theme-muted">{t('trust_tier.criteria_reviews')}</span>
                  <span className="text-sm font-semibold text-theme-primary">
                    {t('trust_tier.criteria_reviews')}
                  </span>
                </div>
                <div className="flex flex-col gap-1">
                  <span className="text-xs text-theme-muted">{t('trust_tier.criteria_identity')}</span>
                  <span className="text-sm font-semibold text-theme-primary">
                    {t('trust_tier.criteria_identity')}
                  </span>
                </div>
              </div>
            </div>
          </GlassCard>
        )}
      </div>
    </>
  );
}

export default MyTrustTierPage;
