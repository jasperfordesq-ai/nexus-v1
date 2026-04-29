// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Card, CardBody, Button, Spinner } from '@heroui/react';
import CheckCircle2 from 'lucide-react/icons/check-circle-2';
import XCircle from 'lucide-react/icons/x-circle';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant } from '@/contexts';
import api from '@/lib/api';

interface SubscriptionResponse {
  subscription: {
    id: number;
    tier_id: number;
    tier_name: string;
    status: string;
    is_active: boolean;
  } | null;
  entitled_tier: {
    tier_id: number;
    tier_name: string;
    features: string[];
  } | null;
  unlocked_features: string[];
}

const POLL_INTERVAL_MS = 1500;
const MAX_POLLS = 20;

export function SubscriptionReturnPage() {
  const { t } = useTranslation('common');
  const { tenantPath } = useTenant();
  const [search] = useSearchParams();
  const cancelled = search.get('cancelled') === '1';
  usePageTitle(t('premium.return_title', 'Activating Subscription'));

  const [status, setStatus] = useState<'pending' | 'success' | 'cancelled' | 'timeout'>(
    cancelled ? 'cancelled' : 'pending'
  );
  const [tierName, setTierName] = useState<string | null>(null);

  useEffect(() => {
    if (cancelled) return;
    let attempts = 0;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let mounted = true;

    const poll = async () => {
      attempts += 1;
      try {
        const res = await api.get<SubscriptionResponse>('/v2/member-premium/me');
        if (!mounted) return;
        if (res.subscription?.is_active && res.entitled_tier) {
          setTierName(res.entitled_tier.tier_name);
          setStatus('success');
          return;
        }
      } catch {
        // ignore — keep polling
      }
      if (attempts >= MAX_POLLS) {
        if (mounted) setStatus('timeout');
        return;
      }
      timer = setTimeout(poll, POLL_INTERVAL_MS);
    };

    poll();
    return () => {
      mounted = false;
      if (timer) clearTimeout(timer);
    };
  }, [cancelled]);

  return (
    <div className="max-w-xl mx-auto px-4 py-16">
      <Card>
        <CardBody className="text-center py-10 flex flex-col items-center gap-4">
          {status === 'pending' && (
            <>
              <Spinner size="lg" />
              <h1 className="text-xl font-semibold">
                {t('premium.return_pending', 'Activating your subscription…')}
              </h1>
              <p className="text-[var(--color-text-secondary)]">
                {t('premium.return_pending_body', 'This usually takes a few seconds.')}
              </p>
            </>
          )}

          {status === 'success' && (
            <>
              <CheckCircle2 size={56} className="text-green-500" />
              <h1 className="text-2xl font-semibold">
                {t('premium.return_success_title', 'You are all set!')}
              </h1>
              <p className="text-[var(--color-text-secondary)]">
                {tierName
                  ? t('premium.return_success_with_tier', { tier: tierName, defaultValue: 'You are now subscribed to {{tier}}.' })
                  : t('premium.return_success_body', 'Your subscription is now active.')}
              </p>
              <div className="flex gap-2 mt-2">
                <Button as={Link} to={tenantPath('/premium/manage')} color="primary">
                  {t('premium.manage_cta', 'Manage subscription')}
                </Button>
                <Button as={Link} to={tenantPath('/dashboard')} variant="flat">
                  {t('premium.go_to_dashboard', 'Go to dashboard')}
                </Button>
              </div>
            </>
          )}

          {status === 'cancelled' && (
            <>
              <XCircle size={48} className="text-[var(--color-text-secondary)]" />
              <h1 className="text-xl font-semibold">
                {t('premium.return_cancelled_title', 'Checkout cancelled')}
              </h1>
              <p className="text-[var(--color-text-secondary)]">
                {t('premium.return_cancelled_body', 'You can try again whenever you are ready.')}
              </p>
              <Button as={Link} to={tenantPath('/premium')} color="primary">
                {t('premium.back_to_pricing', 'Back to pricing')}
              </Button>
            </>
          )}

          {status === 'timeout' && (
            <>
              <h1 className="text-xl font-semibold">
                {t('premium.return_timeout_title', 'Still processing…')}
              </h1>
              <p className="text-[var(--color-text-secondary)]">
                {t(
                  'premium.return_timeout_body',
                  'Your payment is going through. It may take a moment for your subscription to appear.'
                )}
              </p>
              <Button as={Link} to={tenantPath('/premium/manage')} color="primary">
                {t('premium.check_status', 'Check status')}
              </Button>
            </>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

export default SubscriptionReturnPage;
