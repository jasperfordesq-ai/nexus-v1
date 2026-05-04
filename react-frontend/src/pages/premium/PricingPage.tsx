// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Chip,
  Switch,
  Spinner,
} from '@heroui/react';
import Crown from 'lucide-react/icons/crown';
import CheckCircle2 from 'lucide-react/icons/check-circle-2';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useAuth, useTenant, useToast } from '@/contexts';
import api from '@/lib/api';

interface PremiumTier {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  monthly_price_cents: number;
  yearly_price_cents: number;
  features: string[];
  sort_order: number;
  is_active: boolean;
}

function formatPrice(cents: number, currency = 'EUR'): string {
  return new Intl.NumberFormat(undefined, {
    style: 'currency',
    currency,
    minimumFractionDigits: cents % 100 === 0 ? 0 : 2,
  }).format(cents / 100);
}

export function PricingPage() {
  const { t } = useTranslation('common');
  const { isAuthenticated } = useAuth();
  const { tenantPath, hasFeature } = useTenant();
  const navigate = useNavigate();
  const { showToast } = useToast();
  usePageTitle(t('premium.pricing_title', 'Premium'));

  const [tiers, setTiers] = useState<PremiumTier[]>([]);
  const [loading, setLoading] = useState(true);
  const [interval, setInterval] = useState<'monthly' | 'yearly'>('monthly');
  const [submittingTierId, setSubmittingTierId] = useState<number | null>(null);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const res = await api.get<{ tiers: PremiumTier[] }>('/v2/member-premium/tiers');
        if (!cancelled) {
          setTiers(res.data?.tiers ?? []);
        }
      } catch {
        if (!cancelled) {
          setTiers([]);
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  if (!hasFeature('member_premium')) {
    return (
      <div className="max-w-3xl mx-auto px-4 py-12 text-center">
        <h1 className="text-2xl font-semibold mb-2">
          {t('premium.unavailable_title', 'Premium tiers are not available in this community yet')}
        </h1>
      </div>
    );
  }

  const handleSubscribe = async (tier: PremiumTier) => {
    if (!isAuthenticated) {
      navigate(tenantPath('/login'), { state: { from: tenantPath('/premium') } });
      return;
    }
    setSubmittingTierId(tier.id);
    try {
      const returnUrl = window.location.origin + tenantPath('/premium/return');
      const res = await api.post<{ checkout_url: string; session_id: string }>(
        '/v2/member-premium/checkout',
        {
          tier_id: tier.id,
          interval,
          return_url: returnUrl,
        }
      );
      if (res.data?.checkout_url) {
        window.location.href = res.data.checkout_url;
      } else {
        showToast(t('premium.checkout_no_url', 'Checkout could not be started'), 'error');
        setSubmittingTierId(null);
      }
    } catch (err: unknown) {
      const msg = err instanceof Error ? err.message : t('premium.checkout_failed', 'Checkout failed');
      showToast(msg, 'error');
      setSubmittingTierId(null);
    }
  };

  return (
    <div className="max-w-6xl mx-auto px-4 py-10">
      <div className="text-center mb-10">
        <Crown className="mx-auto mb-3 text-yellow-500" size={48} />
        <h1 className="text-3xl font-bold mb-2">
          {t('premium.pricing_title', 'Premium')}
        </h1>
        <p className="text-[var(--color-text-secondary)] max-w-2xl mx-auto">
          {t(
            'premium.pricing_subtitle',
            'Unlock premium features to get more out of your community.'
          )}
        </p>

        <div className="flex items-center justify-center gap-3 mt-6">
          <span className={interval === 'monthly' ? 'font-semibold' : 'text-[var(--color-text-secondary)]'}>
            {t('premium.monthly', 'Monthly')}
          </span>
          <Switch
            isSelected={interval === 'yearly'}
            onValueChange={(v) => setInterval(v ? 'yearly' : 'monthly')}
            aria-label={t('premium.toggle_billing', 'Toggle billing interval')}
          />
          <span className={interval === 'yearly' ? 'font-semibold' : 'text-[var(--color-text-secondary)]'}>
            {t('premium.yearly', 'Yearly')}
          </span>
        </div>
      </div>

      {loading ? (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      ) : tiers.length === 0 ? (
        <Card>
          <CardBody className="text-center py-10 text-[var(--color-text-secondary)]">
            {t('premium.no_tiers', 'No premium tiers are available yet.')}
          </CardBody>
        </Card>
      ) : (
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {tiers.map((tier) => {
            const cents = interval === 'yearly' ? tier.yearly_price_cents : tier.monthly_price_cents;
            const isFree = cents === 0;
            return (
              <Card key={tier.id} className="flex flex-col" shadow="sm">
                <CardHeader className="flex flex-col items-start gap-2">
                  <div className="flex items-center gap-2">
                    <Crown size={20} className="text-yellow-500" />
                    <h2 className="text-xl font-semibold">{tier.name}</h2>
                  </div>
                  {tier.description && (
                    <p className="text-sm text-[var(--color-text-secondary)]">
                      {tier.description}
                    </p>
                  )}
                </CardHeader>
                <CardBody className="flex flex-col gap-4">
                  <div>
                    <div className="text-3xl font-bold">
                      {isFree ? t('premium.free', 'Free') : formatPrice(cents)}
                    </div>
                    {!isFree && (
                      <div className="text-sm text-[var(--color-text-secondary)]">
                        {interval === 'yearly'
                          ? t('premium.per_year', 'per year')
                          : t('premium.per_month', 'per month')}
                      </div>
                    )}
                  </div>

                  <ul className="space-y-2 flex-1">
                    {tier.features.length === 0 ? (
                      <li className="text-sm text-[var(--color-text-secondary)]">
                        {t('premium.no_features_listed', 'No features listed yet')}
                      </li>
                    ) : (
                      tier.features.map((f) => (
                        <li key={f} className="flex items-start gap-2 text-sm">
                          <CheckCircle2 size={16} className="text-green-500 mt-0.5 shrink-0" />
                          <span>{t(`premium.feature.${f}`, f)}</span>
                        </li>
                      ))
                    )}
                  </ul>

                  <Button
                    color="primary"
                    onPress={() => handleSubscribe(tier)}
                    isDisabled={submittingTierId !== null}
                    isLoading={submittingTierId === tier.id}
                  >
                    {t('premium.subscribe_cta', 'Subscribe')}
                  </Button>
                </CardBody>
              </Card>
            );
          })}
        </div>
      )}

      <div className="mt-10 text-center text-sm text-[var(--color-text-secondary)]">
        <Chip size="sm" variant="flat">
          {t('premium.cancel_anytime', 'Cancel anytime')}
        </Chip>
      </div>
    </div>
  );
}

export default PricingPage;
