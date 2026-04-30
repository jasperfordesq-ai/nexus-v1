// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Card, CardBody, CardHeader, Button, Chip, Spinner, Divider } from '@heroui/react';
import Crown from 'lucide-react/icons/crown';
import ExternalLink from 'lucide-react/icons/external-link';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import api from '@/lib/api';

interface MeResponse {
  subscription: {
    id: number;
    tier_id: number;
    tier_name: string;
    tier_slug: string;
    status: string;
    billing_interval: 'monthly' | 'yearly';
    current_period_start: string | null;
    current_period_end: string | null;
    canceled_at: string | null;
    grace_period_ends_at: string | null;
    is_active: boolean;
  } | null;
  entitled_tier: {
    tier_id: number;
    tier_name: string;
    features: string[];
  } | null;
  unlocked_features: string[];
}

function statusChipColor(s: string): 'success' | 'warning' | 'danger' | 'default' {
  switch (s) {
    case 'active':
    case 'trialing':
      return 'success';
    case 'past_due':
    case 'grace':
    case 'incomplete':
      return 'warning';
    case 'canceled':
      return 'danger';
    default:
      return 'default';
  }
}

export function MySubscriptionPage() {
  const { t } = useTranslation('common');
  const { tenantPath } = useTenant();
  const { showToast } = useToast();
  usePageTitle(t('premium.manage_title', 'My Subscription'));

  const [data, setData] = useState<MeResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionBusy, setActionBusy] = useState<'portal' | 'cancel' | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<MeResponse>('/v2/member-premium/me');
      setData(res.data ?? null);
    } catch {
      setData({ subscription: null, entitled_tier: null, unlocked_features: [] });
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const openPortal = async () => {
    setActionBusy('portal');
    try {
      const res = await api.post<{ portal_url: string }>('/v2/member-premium/billing-portal', {
        return_url: window.location.origin + tenantPath('/premium/manage'),
      });
      if (res.data?.portal_url) {
        window.location.href = res.data.portal_url;
      } else {
        showToast(t('premium.portal_failed', 'Could not open billing portal'), 'error');
        setActionBusy(null);
      }
    } catch (err: unknown) {
      showToast(err instanceof Error ? err.message : t('premium.portal_failed', 'Could not open billing portal'), 'error');
      setActionBusy(null);
    }
  };

  const cancel = async () => {
    if (!window.confirm(t('premium.cancel_confirm', 'Cancel subscription at the end of the current billing period?'))) {
      return;
    }
    setActionBusy('cancel');
    try {
      await api.post('/v2/member-premium/cancel', {});
      showToast(t('premium.cancel_scheduled', 'Subscription will end at the period end'), 'success');
      await load();
    } catch (err: unknown) {
      showToast(err instanceof Error ? err.message : t('premium.cancel_failed', 'Cancel failed'), 'error');
    } finally {
      setActionBusy(null);
    }
  };

  if (loading) {
    return (
      <div className="flex justify-center py-16">
        <Spinner size="lg" />
      </div>
    );
  }

  const sub = data?.subscription;

  if (!sub) {
    return (
      <div className="max-w-xl mx-auto px-4 py-12">
        <Card>
          <CardBody className="text-center py-10 flex flex-col items-center gap-4">
            <Crown size={48} className="text-yellow-500" />
            <h1 className="text-xl font-semibold">
              {t('premium.no_subscription_title', 'No active subscription')}
            </h1>
            <p className="text-[var(--color-text-secondary)]">
              {t('premium.no_subscription_body', 'Browse premium tiers to find one that fits your needs.')}
            </p>
            <Button as={Link} to={tenantPath('/premium')} color="primary">
              {t('premium.view_pricing_cta', 'View Pricing')}
            </Button>
          </CardBody>
        </Card>
      </div>
    );
  }

  return (
    <div className="max-w-2xl mx-auto px-4 py-10">
      <Card>
        <CardHeader className="flex items-center gap-3">
          <Crown className="text-yellow-500" size={24} />
          <div>
            <h1 className="text-2xl font-semibold">{sub.tier_name}</h1>
            <p className="text-sm text-[var(--color-text-secondary)]">
              {sub.billing_interval === 'yearly'
                ? t('premium.billed_yearly', 'Billed yearly')
                : t('premium.billed_monthly', 'Billed monthly')}
            </p>
          </div>
        </CardHeader>
        <CardBody className="flex flex-col gap-5">
          <div className="flex items-center gap-2">
            <span className="text-sm text-[var(--color-text-secondary)]">
              {t('premium.status_label', 'Status')}:
            </span>
            <Chip color={statusChipColor(sub.status)} size="sm" variant="flat">
              {t(`premium.status.${sub.status}`, sub.status)}
            </Chip>
          </div>

          {sub.current_period_end && (
            <div className="text-sm">
              <span className="text-[var(--color-text-secondary)]">
                {sub.canceled_at
                  ? t('premium.ends_on', 'Ends on')
                  : t('premium.next_billing', 'Next billing')}
                :{' '}
              </span>
              <span className="font-medium">
                {new Date(sub.current_period_end).toLocaleDateString()}
              </span>
            </div>
          )}

          {sub.grace_period_ends_at && (
            <div className="text-sm rounded-md p-3 bg-yellow-50 dark:bg-yellow-900/20 text-yellow-900 dark:text-yellow-200">
              {t('premium.grace_period_notice', 'Payment failed — please update your card before {{date}} to keep premium active.', {
                date: new Date(sub.grace_period_ends_at).toLocaleDateString(),
              })}
            </div>
          )}

          <Divider />

          <div className="flex flex-col gap-2 sm:flex-row">
            <Button
              color="primary"
              variant="flat"
              startContent={<ExternalLink size={16} />}
              onPress={openPortal}
              isLoading={actionBusy === 'portal'}
              isDisabled={actionBusy !== null}
            >
              {t('premium.manage_in_stripe', 'Manage in Stripe')}
            </Button>
            {!sub.canceled_at && (
              <Button
                color="danger"
                variant="flat"
                onPress={cancel}
                isLoading={actionBusy === 'cancel'}
                isDisabled={actionBusy !== null}
              >
                {t('premium.cancel_subscription', 'Cancel Subscription')}
              </Button>
            )}
            <Button as={Link} to={tenantPath('/premium')} variant="light">
              {t('premium.change_plan', 'Change plan')}
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default MySubscriptionPage;
